$(document).ready(function(){
    $.fn.datepicker.defaults.language = 'fr';

    var date_rattachement = $('input[id="dateRattachement"]');
    var container = $('.bootstrap-iso form').length>0 ? $('.bootstrap-iso form').parent() : "body";

    date_rattachement.datepicker({
        language: 'fr',
        format: 'dd/mm/yyyy',
        container: container,
        todayHighlight: true,
        autoclose: true,
        weekStart: 1
    });
});
