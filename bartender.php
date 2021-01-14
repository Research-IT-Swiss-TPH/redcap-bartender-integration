<?php
// Set the namespace defined in your config file
namespace STPH\Bartender;

// The next 2 lines should always be included and be the same in every module
use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use REDCap;

/**
 * Class Bartender
 * @package STPH\Bartender
 */
class Bartender extends AbstractExternalModule {

    public function __construct()
    {
        parent::__construct();
        define("MODULE_DOCROOT_BARTENDER", $this->getModulePath());
        define("MODULE_INTEGRATION_MODE", $this->getSystemSetting("integration_mode"));
        //define("PRINT_JOBS", $this->getSubSettings("print_jobs", $_GET["pid"]));
    }

    //  Request Controllers
    public function testAsync(){

        header('Content-Type: application/json');
            echo json_encode(array('message' => 'Hello World'));
        exit;
    }

    public function triggerFileIntegration($requestData) {

        $params = (object) $requestData;

        $data = $this->getData( 
                        $params->job_id, 
                        $params->project_id, 
                        $params->record_id 
                );

        $this->getCSV(
            $data, 
            $params->job_id, 
            $params->printer_id, 
            $params->copies
        );

    }

    public function getData($job_id, $project_id, $record_id){        

        // Get Print Job Data
        $data = [];

        // Get current print job by $params->job id
        $print_job = $this->getSubSettings("print_jobs")[$job_id];

        // Loop over all print tasks and creata data array
        foreach ( $print_job["print_job_tasks"] as $t_id => $print_task) {

            $task = [];
            foreach (  $print_task["print_job_variables"] as $v_id => $p_var) {

                $field_value="";
                if(!empty($p_var["pj_var_field"])) {

                    $field_value=$this->getFieldValue(
                        $project_id, 
                        $record_id, 
                        $p_var["pj_var_field"]
                    );
                }

                $var_name = $p_var["pj_var_name"];
                $var_value = $p_var["pj_var_prefix"].$field_value.$p_var["pj_var_suffix"];

                $task[$var_name]  = $var_value;

            }
            array_push( $data, $task);

        }

        return $data;
    }

    public function getCSV($data, $job_id, $printer_id, $copies) {

        // set initial copy variable
        $copy = 1;

        // set meta data, i.e. document name, printer address
        $file_path = $this->getSystemSetting("file_path") . $this->getSubSettings("print_jobs")[$job_id]["pj_file"];
        $printer_url = $this->getProjectSetting("printer_url")[$printer_id];
        $meta_data = array($file_path, $printer_url);
        
        // create a file pointer connected to the output stream
        $output = fopen('php://output', 'w');

        // create table Header from data keys and add to csv
        $header = array_keys($data[0]);
        array_push($header, "document", "printer");
        fputcsv($output, $header);

        //  for each copy
        while ($copy <= $copies) {

            // loop over list to create table rows with variables in columns
            foreach($data as $fields) {

                // add each meta data field to each row
                foreach($meta_data as $meta) {
                    array_push($fields, $meta);
                }

                // add the rows to csv
                fputcsv($output, $fields);
            }

            $copy++;
        }

        fclose($output);
    }


    public function includeJsAndCss()
    {
    ?>

        <link href="<?= $this->getUrl("/style.css") ?>" rel="stylesheet">
        <script src="<?= $this->getUrl("/js/bartender.js") ?>"></script>
        <script>
            STPH_Bartender.requestHandlerUrl = "<?= $this->getUrl("requestHandler.php") ?>";
        </script>
    <?php
    }

    public function getFieldValue($project_id, $record_id, $field_name){  
        
        //  Check if record_id has been renamed and use correct field_name
        if($field_name == $this->getRecordIdField($project_id)) {
            $field_name = 'record_id';
        }

        $result = $this->query(
            '
              select value
              from redcap_data
              where
                project_id = ?
                and record = ?                
                and field_name = ?
            ',
            [
              $project_id,
              $record_id,
              $field_name
            ]
          );

         return db_fetch_assoc($result)["value"];         
    }

    public function redcap_every_page_top($project_id) {

        $this->includeJsAndCss();

        if (PAGE === "DataEntry/record_home.php") {
            
            if( isset($_GET["id"]) && isset($_GET["pid"]) ) {

                //  
                $record_id = $_GET["id"];
                $project_id = $_GET["pid"];

                //  Get Project settings
                $print_jobs = $this->getSubSettings("print_jobs");
                $printers = $this->getSubSettings("printers");

                // Init arrays for POST request
                $variables = [];
                $task = [];
                $data = [];


                ?>
                    <div id="formSaveTip" style="position: fixed; left: 923px; display: block;">
						<div class="btn-group nowrap" style="display: block;">
                            <button type="button" class="btn btn btn-primaryrc btn-lg" data-toggle="modal" data-target="#printModal">
                                <span class="fas fa-print" aria-hidden="true"></span> Print Job
                            </button>
                        </div>
                    </div>
                    
                    <div id="printModal" class="modal  fade" tabindex="-1" data-backdrop="static">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content" style="min-height:500px;">
                                <div class="modal-header">
                                    <h5 class="modal-title"><span class="fas fa-print" aria-hidden="true"></span> Print Job</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="container-fluid">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="modal-step mt-3" id="modal-step-1">
                                                    <p class="h6">Print job:</p>
                                                    <select id="select-print-job" class="custom-select">
                                                        <option disabled selected value>Click here to select a print job..</option>
                                                    <?php foreach ($print_jobs as $key=>$print_job):?>
                                                        <option value="<?= $key ?>"><?= $print_job["pj_name"] ?></option>
                                                    <?php endforeach; ?>                        
                                                    </select>                        
                                                </div>

                                                <div class="modal-step mt-3" id="modal-step-2">
                                                    <p class="h6">Printer:</p>
                                                    <select id="select-printer" class="custom-select">
                                                        <option disabled selected value>Click here to select a printer..</option>
                                                        <?php 
                                                        foreach ($printers as $key=>$printer) {
                                                            echo '<option value="'.$key.'">'. $printer["printer_name"] .'</option>';
                                                            } 
                                                        ?>                            
                                                    </select>
                                                </div>

                                                <div class="modal-step mt-3" id="modal-step-3">
                                                    <p class="h6">Copies:</p>
                                                    <select name="copies" id="select-copies" class="custom-select">
                                                        <option selected>1</option>
                                                        <option>2</option>
                                                        <option>3</option>
                                                        <option>4</option>
                                                        <option>5</option>
                                                        <option>6</option>
                                                        <option>7</option>
                                                        <option>8</option>
                                                        <option>9</option>
                                                        <option>10</option>
                                                    </select>                                                    
                                                </div>

                                            </div>
                                            <div class="col-md-6">
                                                <div class="mt-3">
                                                    <p></p>
                                                    <?php foreach( $print_jobs as $j_id=>$print_job ): ?>
                                                    <div id="print-job-<?= $j_id ?>" class="mb-3 print-job-preview">
                                                        <div class="text-secondary">
                                                            <p><b>Name:</b><br><?= $print_job["pj_name"] ?></p>                                                           
                                                            <p><b>Description:</b><br><?= $print_job["pj_descr"] ?></p>
                                                            <p><b>File:</b><br><img class="file-icon" src="<?= $this->getUrl('img\bartender_icon.png') ?>"><span class="font-italic"><?= $print_job["pj_file"] ?></span></p>
                                                            <p><b>URL:</b><br><?= $print_job["pj_name"] ?></p>                                                           
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>                                               
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col">
                                                <table id="data-preview-table" class="table table-sm table-borderless">
                                                    <!-- data-preview-table -->                                                                                                                                                    
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">

                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <?php if(MODULE_INTEGRATION_MODE == "file"):  ?>
                                        <button id="button-submit-mode-file" type="button" class="btn btn-primary" >
                                        <i class="fas fa-file-download"></i> CSV</button>
                                    <?php elseif(MODULE_INTEGRATION_MODE == "web"):  ?>
                                        <button id="button-submit-mode-web" type="button" class="btn btn-primary" disabled>
                                        <i class="fas fa-print"></i> Print</button>
                                    <?php endif ; ?>

                                </div>
                            </div>
                        </div>
                    </div>

                <?php
            }
        }    
    }

}