$(document).ready(function()
{
    var date = new Date();
    var jour = ('0'+date.getDate()).slice(-2);
    var mois = ('0'+(date.getMonth()+1)).slice(-2);
    var annee = date.getFullYear();

    var date_now = jour + "/" + mois + "/" + annee;

    //////////////////////////////ADD FORM//////////////////////////////
    var enabled_checkbox_js = document.getElementById('whitelabel_backofficebundle_datermh_enabled');
    var enabled_checkbox = $('#whitelabel_backofficebundle_datermh_enabled');
    var enabled_date = $('#whitelabel_backofficebundle_datermh_dateInactif');

    enabled_checkbox.on('click', function () {
        if (enabled_checkbox_js.checked) {
            enabled_date.attr('value', '');
        } else {
            enabled_date.attr('value', date_now);
        }
    });

    //////////////////////////////EDIT FORM//////////////////////////////
    var elt_enabledForm = document.querySelectorAll('.valueForm');

    $.each(elt_enabledForm, function () {
        var value = $(this).val().split(" | ");

        if ('on' == value[0]) {
            $('#formDateRMH_edit_' + value[1] + '_dateInactif').attr('value', '');
        }

        $('#formDateRMH_edit_' + value[1] + '_enabled').on('click', function () {
            if (document.getElementById('formDateRMH_edit_' + value[1] + '_enabled').checked) {
                $('#formDateRMH_edit_' + value[1] + '_dateInactif').attr('value', '');
            } else {
                $('#formDateRMH_edit_' + value[1] + '_dateInactif').attr('value', date_now);
            }
        });
    });
});
