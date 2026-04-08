$(document).ready(function() {
    var date = new Date();
    var jour = ('0'+date.getDate()).slice(-2);
    var mois = ('0'+(date.getMonth()+1)).slice(-2);
    var annee = date.getFullYear();

    var date_now = jour + "/" + mois + "/" + annee;

    /* *****************************************************************
     ********************************************************************
                    A U D I T E U R / R E N O V A T E U R
     ********************************************************************
     *******************************************************************/
    var enabled_id = document.getElementById('whitelabel_backofficebundle_epci__enabled');
    var elt_enabled = $('#enabledEPCI');
    var enabled = elt_enabled.text();

    if ('0' == enabled) {
        $(enabled_id).attr("checked", false);
        $(enabled_id).val("0");
    } else if ('1' == enabled) {
        $(enabled_id).attr("checked", true);
        $(enabled_id).val("1");
        $('#whitelabel_backofficebundle_epci__dateInactif').attr('value', '');
    }

    $(enabled_id).on('click', function () {
        if (enabled_id.checked) {
            $('#whitelabel_backofficebundle_epci__dateInactif').attr('value', '');
            $(enabled_id).val("1");
        } else {
            $('#whitelabel_backofficebundle_epci__dateInactif').attr('value', date_now);
            $(enabled_id).val("0");
        }
    });
});
