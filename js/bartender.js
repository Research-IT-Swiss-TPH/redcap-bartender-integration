$(function() {
    'use strict';

    var body = $('body');
    var select_print_job = $('#select-print-job');
    var request_params = [];

    function onPrintJobChange(e){

        var value = e.target.value;
        request_params["job"] = value;

        $('.print-job-preview').hide();
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


    //  Event Listeners
    body.on('change', '#select-print-job', onPrintJobChange);
    body.on('change', '#select-printer', onPrinterChange);

});