$(document).ready(function() {
    var date = new Date();
    var jour = ('0'+date.getDate()).slice(-2);
    var mois = ('0'+(date.getMonth()+1)).slice(-2);
    var annee = date.getFullYear();

    var date_now = jour + "/" + mois + "/" + annee;

    /* *****************************************************************
    ********************************************************************
                                B A N Q U E
    ********************************************************************
    *******************************************************************/
    /*
    var enabled_id = document.getElementById('whitelabel_backofficebundle_banque__banque_statut_enabled');
    var elt_enabled = $('#enabledBanque');
    var enabled = elt_enabled.text();

    if ('0' == enabled) {
        $(enabled_id).attr("checked", false);
        $(enabled_id).val("0");
    } else if ('1' == enabled) {
        $(enabled_id).attr("checked", true);
        $(enabled_id).val("1");
        $('#banque_dateInactif').attr('value', '');
    }

    $(enabled_id).on('click', function () {
        if (enabled_id.checked) {
            $('#banque_dateInactif').attr('value', '');
            $(enabled_id).val("1");
        } else {
            $('#banque_dateInactif').attr('value', display_date);
            $(enabled_id).val("0");
        }
    });
    */

    //////////////////////////////ADD FORM//////////////////////////////
    var enabled_checkbox_js = document.getElementById('whitelabel_backofficebundle_banque__banque_statut_enabled');
    var enabled_checkbox = $('#whitelabel_backofficebundle_banque__banque_statut_enabled');
    var enabled_date = $('#whitelabel_backofficebundle_banque__banque_statut_dateInactif');


    var elt_enabledForm = $('#valueForm').val();
    if ('on' == elt_enabledForm) {
        enabled_date.attr('value', '');
    }

    enabled_checkbox.on('click', function () {
        if (enabled_checkbox_js.checked) {
            enabled_date.attr('value', '');
        } else {
            enabled_date.attr('value', date_now);
        }
    });
});
