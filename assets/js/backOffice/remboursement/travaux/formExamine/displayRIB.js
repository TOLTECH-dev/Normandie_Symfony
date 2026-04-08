import { checkIBAN } from '../../checkIBAN';

$(document).ready(function()
{
    $("#box-ribAuditeur").removeClass('hidden');

    var data_form = new Array();
    data_form['iban'] = $('#whitelabel_backofficebundle_remboursement__remboursement_travaux_instruction_iban').val();
    data_form['bic'] = $('#whitelabel_backofficebundle_remboursement__remboursement_travaux_instruction_bic').val();
    data_form['domiciliationBancaire'] = $('#whitelabel_backofficebundle_remboursement__remboursement_travaux_instruction_domiciliationBancaire').val();

    var objectBeneficiaire = $('#whitelabel_backofficebundle_remboursement__remboursement_travaux_instruction_destinataire option[value="1 | beneficiaire"]');
    var objectRenovateur = $('#whitelabel_backofficebundle_remboursement__remboursement_travaux_instruction_destinataire option[value="2 | renovateur"]');

    var isBeneficiaireSelected = objectBeneficiaire.is(':selected');
    var isRenovateurSelected = objectRenovateur.is(':selected');

    var iban = $("#whitelabel_backofficebundle_remboursement__remboursement_travaux_instruction_iban");
    var bic = $("#whitelabel_backofficebundle_remboursement__remboursement_travaux_instruction_bic");
    var domiciliationBancaire = $("#whitelabel_backofficebundle_remboursement__remboursement_travaux_instruction_domiciliationBancaire");

    if (!isBeneficiaireSelected && !isRenovateurSelected) {
        objectBeneficiaire.prop('selected', true);
    }



    checkIBAN(iban);
    iban.on('change blur keyup load', function () {
        checkIBAN(iban);
    });

    isDocument();
    $('#whitelabel_backofficebundle_remboursement__remboursement_travaux_instruction_destinataire').on('change', function () {
        isSelected(data_form);
        isDocument();
    });

    function isSelected(data_form) {
        var isBeneficiaire = objectBeneficiaire.is(':selected');

        if (isBeneficiaire) {
            if (isBeneficiaireSelected) {
                iban.val(data_form['iban']);
                bic.val(data_form['bic']);
                domiciliationBancaire.val(data_form['domiciliationBancaire']);
            } else {
                iban.val('');
                bic.val('');
                domiciliationBancaire.val('');
            }
        } else {
            if (isRenovateurSelected) {
                iban.val(data_form['iban']);
                bic.val(data_form['bic']);
                domiciliationBancaire.val(data_form['domiciliationBancaire']);
            } else {
                iban.val('');
                bic.val('');
                domiciliationBancaire.val('');
            }
        }

        checkIBAN(iban);
    }

    function isDocument() {
        var ribRemboursement = $("#box-ribRemboursement");

        var isBeneficiaire = objectBeneficiaire.is(':selected');
        var isRenovateur = objectRenovateur.is(':selected');

        if (isBeneficiaireSelected && isBeneficiaire) {
            ribRemboursement.removeClass('hidden');
        } else if (isRenovateurSelected && isRenovateur) {
            ribRemboursement.removeClass('hidden');
        } else {
            ribRemboursement.addClass('hidden');
        }
    }
});
