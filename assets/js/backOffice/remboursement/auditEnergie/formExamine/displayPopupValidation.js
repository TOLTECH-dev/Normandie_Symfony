$(document).ready(function()
{
    $('#button_popupValidation').on('click', function () {
        var elt_isFactureConforme = $('#whitelabel_backofficebundle_remboursement__remboursement_auditEnergie_instruction_isFactureConforme');

        var value_isFactureConforme = (elt_isFactureConforme.find(':selected').val()).split(' | ');
        var value_montantFacture = $("#whitelabel_backofficebundle_remboursement__remboursement_auditEnergie_instruction_montantFacture").val();
        var value_montantFactureFormatted = null;

        var seuilCheckMontantFacture = $('#data-js').attr('data-seuil-check-montant-facture');
        var minFacture = parseFloat(seuilCheckMontantFacture);

        if (0 != value_montantFacture.length) value_montantFactureFormatted = parseFloat(value_montantFacture);
        else value_montantFactureFormatted = parseFloat(0);

        var value_montantFactureMatch = value_montantFacture.match(/\d+(?:\.\d+)?/);
        if (
            (value_montantFactureMatch == null || (value_montantFactureMatch[0] && (value_montantFactureMatch[0] != value_montantFacture)))
            || ('0' != value_isFactureConforme[0])
            || (value_montantFactureFormatted >= minFacture)
        ) {
            //   We not show modal popup
            $('#button_popupValidation').removeAttr('data-toggle');
            $('#whitelabel_backofficebundle_remboursement__valider').trigger('click');
        } else {
            $('#button_popupValidation').attr('data-toggle', 'modal');
        }
    });
});
