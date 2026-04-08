$(document).ready(function() {

    function initAndShowUbatBase() {
        var columnTypeToInit = ['BBC', 'prescription'];
        var elementCEPUbatBaseInitial = $(".initial-CEPUbatBase");

        elementCEPUbatBaseInitial.on('change blur keyup', function() {
            var valueCEPUbatBaseInitial = $(this).val();

            columnTypeToInit.forEach(function (itemValue) {
                $("." + itemValue + "-CEPUbatBase").val(valueCEPUbatBaseInitial);
            });
        });
    }

    initAndShowUbatBase();
});
