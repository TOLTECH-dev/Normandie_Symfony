$(document).ready(function() {
    $('form#form_examineDevis, form#form_devis').bind('keypress', function(e) {
        if (e.keyCode == 13) {
            return false;
        }
    });
});
