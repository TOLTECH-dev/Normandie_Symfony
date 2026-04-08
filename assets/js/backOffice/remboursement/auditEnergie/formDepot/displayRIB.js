import { checkIBAN } from '../../checkIBAN';

$(document).ready(function()
{
    var rib = $("#whitelabel_backofficebundle_remboursement__remboursement_auditEnergie_instruction_rib");
    var iban = $("#whitelabel_backofficebundle_remboursement__remboursement_auditEnergie_instruction_iban");
    var bic = $("#whitelabel_backofficebundle_remboursement__remboursement_auditEnergie_instruction_bic");
    var domiciliationBancaire = $("#whitelabel_backofficebundle_remboursement__remboursement_auditEnergie_instruction_domiciliationBancaire");

    iban.val($("#auditeur_iban").val());
    bic.val($("#auditeur_bic").val());
    domiciliationBancaire.val($("#auditeur_domiciliationBancaire").val());

    if('1' == $("#is_auditeur").val()) {
        $("label[for='whitelabel_backofficebundle_remboursement__remboursement_auditEnergie_instruction_rib']").prop('for', '');
        rib.addClass('hidden');
    } else {
        checkIBAN(iban);
        iban.on('change blur keyup load', function () {
            checkIBAN(iban);
        });
    }
});
