$(document).ready(function() {
    $('#whitelabel_frontofficebundle_logement_codePostal').formatter(format_code_postal);
    $('#whitelabel_frontofficebundle_logement_numeroRue').formatter(format_numero_rue);

    ////////////////////////////////////////////////////////////////////

    /* Whitespace */
    $("#whitelabel_frontofficebundle_logement_nom, #nomRueNotFound, #whitelabel_frontofficebundle_logement_descriptionProjet").blur(function(){
        $(this).val($(this).val().replace(/^\s\s*/, '').replace(/\s\s*$/, ''));
    });
});
