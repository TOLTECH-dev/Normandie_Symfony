$(document).ready(function() {

    /**
     *
     */
    function manageBlocValoriserRenovationJustification(){
        if (elementFinChantierIsValoriserRenovation.is(":checked")) {
            blocValoriserRenovationJustification.show();
            elementFinChantierValoriserRenovationJustification.prop("required", "required");
        } else {
            blocValoriserRenovationJustification.hide();
            elementFinChantierValoriserRenovationJustification.prop("required", false);
            elementFinChantierValoriserRenovationJustification.val("");
        }
    }

    /**
     *
     */
    function manageBtnValiderFinChantier(){
        if (!elementFinChantierIsValeurQ4CalculeeConforme.is(":checked")
            || !elementFinChantierIsSystemeVentilationConforme.is(":checked")) {
            elementFinChantierInformationValidation.prop("checked", false);
            elementFinChantierInformationValidation.prop("disabled", "disabled");
        } else {
            elementFinChantierInformationValidation.prop("disabled", false);
        }
    }

    var elementFinChantierIsValoriserRenovation = $(".finChantier-is-valoriser-renovation");
    var elementFinChantierValoriserRenovationJustification = $(".finChantier-valoriser-renovation-justification");
    var blocValoriserRenovationJustification = $("#bloc-valoriser-renovation-justification");

    var elementFinChantierIsValeurQ4CalculeeConforme = $(".finChantier-is-valeur-Q4-calculee-conforme");
    var elementFinChantierIsSystemeVentilationConforme = $(".finChantier-is-systeme-ventilation-conforme");
    var elementFinChantierInformationValidation = $(".finChantier-informationValidation");

    manageBlocValoriserRenovationJustification();
    manageBtnValiderFinChantier();

    elementFinChantierIsValoriserRenovation.on("change", function() {
        manageBlocValoriserRenovationJustification();
    });

    elementFinChantierIsValeurQ4CalculeeConforme
        .add(elementFinChantierIsSystemeVentilationConforme)
        .on("change", function() {
            manageBtnValiderFinChantier();
    });
});
