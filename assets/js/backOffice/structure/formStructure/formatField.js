$(document).ready(function() {
    $('#whitelabel_backofficebundle_structure__structure_adresse_codePostal').formatter(format_code_postal);
    $('#whitelabel_backofficebundle_structure__structure_adresse_telephone').formatter(format_telephone);
    $('.contact-telephone').formatter(format_telephone);
    $('.conseiller-telephone').formatter(format_telephone);
    $('.permanence-telephone').formatter(format_telephone);
    $('.permanence-codePostal').formatter(format_code_postal);
});
