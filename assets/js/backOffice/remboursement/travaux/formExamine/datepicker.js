$(document).ready(function(){
    $.fn.datepicker.defaults.language = 'fr';

    var date_cheque = $('input[id="whitelabel_backofficebundle_remboursement__remboursement_travaux_instruction_dateCheque"]');
    var container = $('.bootstrap-iso form').length>0 ? $('.bootstrap-iso form').parent() : "body";

    date_cheque.datepicker({
        language: 'fr',
        format: 'dd/mm/yyyy',
        container: container,
        todayHighlight: true,
        autoclose: true,
        weekStart: 1
    });
});


