$(document).ready(function() {
    var date = new Date();
    var jour = ('0'+date.getDate()).slice(-2);
    var mois = ('0'+(date.getMonth()+1)).slice(-2);
    var annee = date.getFullYear();

    var display_date = jour + "/" + mois + "/" + annee;

    /* *****************************************************************
    ********************************************************************
                            S T R U C T U R E
    ********************************************************************
    *******************************************************************/
    var enabled_id = document.getElementById('whitelabel_backofficebundle_structure__structure_statut_enabled');
    var elt_enabled = $('#enabledStructure');
    var enabled = elt_enabled.text();

    if ('0' == enabled) {
        $(enabled_id).attr("checked", false);
        $(enabled_id).val("0");
    } else if ('1' == enabled) {
        $(enabled_id).attr("checked", true);
        $(enabled_id).val("1");
        $('#structure_dateInactif').attr('value', '');
    }

    $(enabled_id).on('click', function () {
        if (enabled_id.checked) {
            $('#structure_dateInactif').attr('value', '');
            $(enabled_id).val("1");
        } else {
            $('#structure_dateInactif').attr('value', display_date);
            $(enabled_id).val("0");
        }
    });
});
