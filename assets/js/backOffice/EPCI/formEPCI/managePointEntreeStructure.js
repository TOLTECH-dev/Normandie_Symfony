$(document).ready(function() {

    var nomAffichageRow = $('#nom-affichage-row');

    manageNomAffichageRow();

    $('#whitelabel_backofficebundle_epci__pointEntreeStructure').on('click', function () {
        manageNomAffichageRow();
    });

    function manageNomAffichageRow() {
        if ($('#whitelabel_backofficebundle_epci__pointEntreeStructure').is(':checked')) {
            showNomAffichageRow();
        } else {
            HideNomAffichageRow();
        }
    }

    function showNomAffichageRow() {
        nomAffichageRow.removeClass('hidden');
    }

    function HideNomAffichageRow() {
        nomAffichageRow.addClass('hidden');
        $('#whitelabel_backofficebundle_epci__nomAffichage').val('');
    }
});




