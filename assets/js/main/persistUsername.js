$(document).ready(function() {
    var email = document.getElementById("email");
    var username = document.getElementById("username");

    $('#email').on('onchange blur keyup load', function () {
        var username_ = $(this).val();
        $('#username').val(username_);
    });
});
