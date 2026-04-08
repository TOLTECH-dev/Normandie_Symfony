$(document).ready(function()
{
    /* *****************************************************************
    ********************************************************************
                        MANAGE POPUP VALIDATION SHOW
    ********************************************************************
    *******************************************************************/
    if ($('#button_popupValidation')) {
        $('#button_popupValidation').on('click', function () {

            var elt_isFactureConforme = $('#whitelabel_backofficebundle_remboursement__remboursement_travaux_instruction_isFactureConforme');

            var value_isFactureConforme = (elt_isFactureConforme.find(':selected').val()).split(' | ');
            var value_montantFacture = $("#whitelabel_backofficebundle_remboursement__remboursement_travaux_instruction_montantFacture").val();
            var value_devis = $('#totalDevis').val();

            var value_devisFormatted = null;
            var value_montantFactureFormatted = null;

            if (value_devis.length != 0) value_devisFormatted = parseFloat(value_devis);
            else value_devisFormatted = 0.0;

            if (value_montantFacture.length != 0) value_montantFactureFormatted = parseFloat(value_montantFacture);
            else value_montantFactureFormatted = 0.0;

            var delta = parseFloat(value_devisFormatted*15/100);
            var deltaMoins = parseFloat(value_devisFormatted - delta);

            var value_montantFactureMatch = value_montantFacture.match(/\d+(?:\.\d+)?/);
            if (
                (value_montantFactureMatch == null || (value_montantFactureMatch[0] && (value_montantFactureMatch[0] != value_montantFacture)))
                || ('0' != value_isFactureConforme[0])
                || (value_montantFactureFormatted >= deltaMoins)
            ) {
                $('#button_popupValidation').removeAttr('data-toggle');
                $('#whitelabel_backofficebundle_remboursement__valider').trigger('click');
            } else {
                $('#button_popupValidation').attr('data-toggle', 'modal');
            }
        });
    }
});
