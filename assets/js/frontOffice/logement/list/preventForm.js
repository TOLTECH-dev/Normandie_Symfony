$(document).ready(function() {
    var prefixPatnameBundle = $("#data-js-bundle").data("prefix-pathname-bundle");

    $("#button_addLogement1").on('click', function () {
        preventForm();
    });

    $("#button_addLogement2").on('click', function () {
        preventForm();
    });

    function preventForm() {
        $.ajax({
            url: Routing.generate(prefixPatnameBundle + '_security_form'),
            dataType: "json"
        });
    }
});
