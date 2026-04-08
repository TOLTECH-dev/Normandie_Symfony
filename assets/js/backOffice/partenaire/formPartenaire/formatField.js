$(document).ready(function() {
    $('#whitelabel_backofficebundle_partenaire__partenaire_adresse_codePostal').formatter(format_code_postal);
    $('#whitelabel_backofficebundle_partenaire__partenaire_adresse_telFixe').formatter(format_telephone);
    $('#whitelabel_backofficebundle_partenaire__partenaire_adresse_telMobile').formatter(format_telephone);
    $('.contact-telephone').formatter(format_telephone);
    $('.agence-telephone').formatter(format_telephone);
    $('.agence-codePostal').formatter(format_code_postal);
});
