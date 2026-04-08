import { checkIBAN } from '../../checkIBAN';

$(document).ready(function()
{
    var data_form = new Array();
    data_form['iban'] = $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_iban').val();
    data_form['bic'] = $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_bic').val();
    data_form['domiciliationBancaire'] = $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_domiciliationBancaire').val();

    var data_auditeur = new Array();
    data_auditeur['iban'] = $("#auditeur_iban").val();
    data_auditeur['bic'] = $("#auditeur_bic").val();
    data_auditeur['domiciliationBancaire'] = $("#auditeur_domiciliationBancaire").val();

    var objectAuditeur = $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_destinataire option[value="0 | auditeur"]');
    var objectBeneficiaire = $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_destinataire option[value="1 | beneficiaire"]');

    var isAuditeurSelected = objectAuditeur.is(':selected');
    var isBeneficiaireSelected = objectBeneficiaire.is(':selected');

    var iban = $("#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_iban");
    var bic = $("#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_bic");
    var domiciliationBancaire = $("#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_domiciliationBancaire");

    if (!isBeneficiaireSelected) {
        objectAuditeur.prop('selected', true);
        iban.val(data_auditeur['iban']);
        bic.val(data_auditeur['bic']);
        domiciliationBancaire.val(data_auditeur['domiciliationBancaire']);
    }



    checkIBAN(iban);
    iban.on('change blur keyup load', function () {
        checkIBAN(iban);
    });

    isDocument(true);
    $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_destinataire').on('change', function () {
        isSelected(data_form, data_auditeur);
        isDocument(false);
    });

    function isSelected(data_form, data_auditeur) {
        var isAuditeur = objectAuditeur.is(':selected');

        if (isAuditeur) {
            iban.val(data_auditeur['iban']);
            bic.val(data_auditeur['bic']);
            domiciliationBancaire.val(data_auditeur['domiciliationBancaire']);
        } else {
            if (isBeneficiaireSelected) {
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

    function isDocument(flag) {
        var ribAuditeur = $("#box-ribAuditeur");
        var ribRemboursement = $("#box-ribRemboursement");

        var isAuditeur = objectAuditeur.is(':selected');
        var isBeneficiaire = objectBeneficiaire.is(':selected');

        if (true == flag) {
            if (isAuditeurSelected) {
                ribAuditeur.removeClass('hidden');
                ribRemboursement.addClass('hidden');
            } else if (isBeneficiaireSelected) {
                ribAuditeur.addClass('hidden');
                ribRemboursement.removeClass('hidden');
            }
        } else {
            if (isAuditeur) {
                ribAuditeur.removeClass('hidden');
                ribRemboursement.addClass('hidden');
            } else if (isBeneficiaire) {
                ribAuditeur.addClass('hidden');
                ribRemboursement.removeClass('hidden');
            }
        }
    }
});
