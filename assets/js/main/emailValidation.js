$(document).ready(function() {
    var email = $("#email");
    var confirm_email = $("#email_repeat");

    $('#email_repeat').bind('paste', function (e) {
        e.preventDefault();
    });

    function validateEmail() {
        if(email.val() !== confirm_email.val()) {
            confirm_email[0].setCustomValidity("Les adresses email ne sont pas identiques.");
        } else {
            confirm_email[0].setCustomValidity('');
        }
    }

    email.on("change", function () {
        validateEmail();
    });
    confirm_email.on("keyup", function () {
        validateEmail();
    });
});
