$(document).ready(function() {
    var elt_type = $('#typePartenaire');
    var type = elt_type.text();

    /* *****************************************************************
                On initialise les options correspondantes
    *******************************************************************/
    if ('0' == type) {
        $('#bloc-optionAuditeur').removeClass('hidden');
        $('#bloc-optionRenovateur').addClass('hidden');

        $('#whitelabel_backofficebundle_partenaire__partenaire_optionAuditeur_domicileBancaire').attr("required", true);
        $('#whitelabel_backofficebundle_partenaire__partenaire_optionAuditeur_titulaire').attr("required", true);
        $('#whitelabel_backofficebundle_partenaire__partenaire_optionAuditeur_iban').attr("required", true);
        $('#whitelabel_backofficebundle_partenaire__partenaire_optionAuditeur_bic').attr("required", true);

        $('#whitelabel_backofficebundle_partenaire__partenaire_optionRenovateur_typeActeur').attr("required", false);

        $("label[for='whitelabel_backofficebundle_partenaire__partenaire_optionAuditeur_domicileBancaire']").addClass('required');
        $("label[for='whitelabel_backofficebundle_partenaire__partenaire_optionAuditeur_titulaire']").addClass('required');
        $("label[for='whitelabel_backofficebundle_partenaire__partenaire_optionAuditeur_iban']").addClass('required');
        $("label[for='whitelabel_backofficebundle_partenaire__partenaire_optionAuditeur_bic']").addClass('required');

        $("label[for='whitelabel_backofficebundle_partenaire__partenaire_optionRenovateur_typeActeur']").removeClass('required');
    } else if ('1' == type) {
        $('#bloc-optionAuditeur').addClass('hidden');
        $('#bloc-optionRenovateur').removeClass('hidden');

        $('#whitelabel_backofficebundle_partenaire__partenaire_optionAuditeur_domicileBancaire').attr("required", false);
        $('#whitelabel_backofficebundle_partenaire__partenaire_optionAuditeur_titulaire').attr("required", false);
        $('#whitelabel_backofficebundle_partenaire__partenaire_optionAuditeur_iban').attr("required", false);
        $('#whitelabel_backofficebundle_partenaire__partenaire_optionAuditeur_bic').attr("required", false);

        $('#whitelabel_backofficebundle_partenaire__partenaire_optionRenovateur_typeActeur').attr("required", true);

        $("label[for='whitelabel_backofficebundle_partenaire__partenaire_optionAuditeur_domicileBancaire']").removeClass('required');
        $("label[for='whitelabel_backofficebundle_partenaire__partenaire_optionAuditeur_titulaire']").removeClass('required');
        $("label[for='whitelabel_backofficebundle_partenaire__partenaire_optionAuditeur_iban']").removeClass('required');
        $("label[for='whitelabel_backofficebundle_partenaire__partenaire_optionAuditeur_bic']").removeClass('required');

        $("label[for='whitelabel_backofficebundle_partenaire__partenaire_optionRenovateur_typeActeur']").addClass('required');
    }
});
