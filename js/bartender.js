$(function() {
    'use strict';


    // Initialize Module Object
    var UIOWA_AdminDash = {};

    var body = $('body');
    var select_print_job = $('#select-print-job');
    var request_params = [];

    function onPrintJobChange(e){

        var value = e.target.value;
        request_params["job"] = value;

        $('.print-job-preview').hide();
        $('.variable-content').hide();
        $('#variable-content-'+value).show();
        $('#print-job-'+value).fadeIn();
        validate();
    }

    function onPrinterChange(e){
        var value = e.target.value;
        request_params["printer"] = value;        

        validate();
    }

    function validate() {
        var isValid = true;
        if(request_params["job"] == null ){
            isValid=false;
        }
        if(request_params["printer"] == null) {
            isValid=false;
        }
        console.log(isValid);
        console.log(request_params);
        checkButton(isValid);
    }

    function checkButton(isValid) {

        if(isValid){
            $('#button-submit-print').disabled = false;
        } else {
            $('#button-submit-print').disabled = true;
        }
    }

    function onTest(){
        $.ajax({
            method: 'POST',
            url: STPH_Bartender.requestHandlerUrl + '&type=getPrintJobData',
            data: {
                project_id: 14,
                record_id: 1,
                job_id: 0,
                printer_id: 2,
                copies: 1
            },
            success: function(data) {

                var blob=new Blob([data]);
                var link=document.createElement('a');
                link.href=window.URL.createObjectURL(blob);
                link.download="test.csv";
                link.click();

                console.log(data[0])
                //renderDataPreview(data[0])
            },
            error: function(data) {
                var response = data['responseJSON'];
    
                if (response['error']) {
                    console.log(response['error']);
                }
                else {
                    console.log('An unknown error occurred.');
                }
            }            
        });
    }


    function onDownloadCSV(){
        fetch(STPH_Bartender.requestHandlerUrl + '&type=downloadCSV', {
            method: 'POST',
            body: {
                project_id: 14,
                record_id: 1,
                job_id: 0
            },
            headers: {
                'Content-Type': 'text/csv'
                // 'Content-Type': 'application/x-www-form-urlencoded',
              },
        })
        .then(resp => resp.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            // the filename you want
            a.download = 'todo-1.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            //alert('your file has downloaded!'); // or you know, something with better UX...
        })
        .catch(() => alert('oh no!'));
    }


    function renderDataPreview(data){

        var tableHeader = 
                        '<thead>' + 
                            '<tr>' +
                                '<th scope="col">Task #</th>' +
                                '<th scope="col">Variable</th>' +
                                '<th scope="col">Value</th>' +
                            '</tr>' +
                        '</thead>';

        var tableBody = '<tbody>';

        $.each(data, function( t_index, task ) {

            var rows = '';
            
            $.each(task, function( key, value) {

                rows += 
                    '<tr>' + 
                        '<td>' + key + '</td>' + 
                        '<td>' + value + '</td>' +
                        '<td scope="row"></td>' +
                    '<tr>';

            });

            var task_id = t_index + 1;
           
            tableBody += '<td colspan="3" class="table-info" scope="row">'+ task_id +'</td>' + rows;
                        
        });

        tableBody += '</tbody>';
        
        var table = 
                tableHeader + 
                tableBody;

        $('#data-preview-table').append(table);
                
    }


    //  Event Listeners
    body.on('change', '#select-print-job', onPrintJobChange);
    body.on('change', '#select-printer', onPrinterChange);

    body.on('click', '#test', onTest);

});

var STPH_Bartender = {};