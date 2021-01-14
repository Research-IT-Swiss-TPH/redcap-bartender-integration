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
        //define("PRINT_JOBS", $this->getSubSettings("print_jobs", $_GET["pid"]));
    }

    //  Request Controllers
    public function testAsync(){

        header('Content-Type: application/json');
            echo json_encode(array('message' => 'Hello World'));
        exit;
    }

    public function createCSV($list, $meta) {
        // create a file pointer connected to the output stream
        $output = fopen('php://output', 'w');

        // Create Table Header

        $header = array("number", "type", "barcode", "document", "printer");
        fputcsv($output, $header);

        foreach($list as $fields) {

            foreach($meta as $metafield) {
                array_push($fields, $metafield);
            }
            // output the column headings
            fputcsv($output,$fields);

        }

        fclose($output);

    }

    public function getPrintJobData( $project_id, $record_id, $job_id, $printer_id, $copies){

        // Get Print Job Data
        $data = [];
        $print_jobs = $this->getSubSettings("print_jobs");
        $printers = $this->getProjectSetting("printer_url");


        $print_job = $print_jobs[$job_id];
        $print_tasks = $print_job["print_job_tasks"];

        foreach ( $print_tasks as $t_id => $print_task) {

            $current_task = [];
            foreach (  $print_task["print_job_variables"] as $v_id => $p_var) {

                $field_value="";
                if(!empty($p_var["pj_var_field"])) {
                    $field_value=$this->getFieldValue($project_id, $record_id, $p_var["pj_var_field"]);
                }
                $var_name = $p_var["pj_var_name"];
                $var_value = $p_var["pj_var_prefix"].$field_value.$p_var["pj_var_suffix"];
                $current_task[$var_name]  = $var_value;

            }
            array_push( $data, $current_task);

        }

        //  Response Array
        $res = [];
        array_push(
            $res, 
            $data
        );

        //header('Content-Type: application/json');
        //echo json_encode($res);
        //exit;

        //  Document Name, Printer Name/URL, Copies
        $meta_file = $this->getSystemSetting("file_path") .  $print_job["pj_file"];
        $meta_printer_url = $printers[$printer_id];
        //$meta_copies = $copies;

        $meta = array($meta_file, $meta_printer_url);
        $csv = $this->createCSV($data, $meta);

        echo $csv;
        exit;

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
                                                <p class="h6">Job Summary:</p>
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
                                                <!-- <pre><?php // json_encode($data) ?></pre> -->                                                    
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col">
                                                <table id="data-preview-table" class="table table-sm table-borderless">
                                                  
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button id="button-submit-print" type="button" class="btn btn-primary" disabled>Print</button>
                                    <button id="test" type="button" class="btn btn-success" >Test</button>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php
            }
        }    
    }

}