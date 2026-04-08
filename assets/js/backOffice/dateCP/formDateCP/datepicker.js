$(document).ready(function(){
    $.fn.datepicker.defaults.language = 'fr';
    
    var date_CP = $('input[id="whitelabel_backofficebundle_datecp_dateCP"]');
    var container = $('.bootstrap-iso form').length>0 ? $('.bootstrap-iso form').parent() : "body";

    date_CP.datepicker({
        language: 'fr',
        format: 'dd/mm/yyyy',
        container: container,
        todayHighlight: true,
        autoclose: true,
        weekStart: 1
    });

    $('.datepickerClass').datepicker({
        language: 'fr',
        format: 'dd/mm/yyyy',
        container: container,
        todayHighlight: true,
        autoclose: true,
        weekStart: 1
    });
});
