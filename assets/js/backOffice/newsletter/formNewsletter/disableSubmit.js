$(document).ready(function(){
    $('form#form_addNewsletter').submit(function() {
        $('#whitelabel_backofficebundle_newsletter_envoyer').attr('disabled', true);
    });
});
