$(document).ready(function() {
    $('#whitelabel_frontofficebundle_beneficiaire_codePostal').formatter(format_code_postal);
    $('#whitelabel_frontofficebundle_beneficiaire_numeroRue').formatter(format_numero_rue);
    $('#whitelabel_frontofficebundle_beneficiaire_tel1').formatter(format_telephone);
    $('#whitelabel_frontofficebundle_beneficiaire_tel2').formatter(format_telephone);
    $('#whitelabel_frontofficebundle_beneficiaire_nbPersFoyer').formatter(format_nb_personne);
    $('#whitelabel_frontofficebundle_beneficiaire_revenuFiscalRef').formatter(format_montant);

    ////////////////////////////////////////////////////////////////////

    /* Whitespace */
    $("#whitelabel_frontofficebundle_beneficiaire_nom, #whitelabel_frontofficebundle_beneficiaire_prenom, #nomRueNotFound, #whitelabel_frontofficebundle_beneficiaire_email, #whitelabel_frontofficebundle_beneficiaire_nomConjoint, #whitelabel_frontofficebundle_beneficiaire_prenomConjoint").on("blur", function() {
        $(this).val($(this).val().replace(/^\s\s*/, '').replace(/\s\s*$/, ''));
    });

    ////////////////////////////////////////////////////////////////////

    /* Disable starting by 0 */
    $("#whitelabel_frontofficebundle_beneficiaire_nbPersFoyer").on('keyup', function() {
        var nbPersFoyer = $(this).val();
        if (nbPersFoyer == 0){
            $(this).val("");
        } else {
            $(this).val(parseInt(nbPersFoyer));
        }
    });
});
