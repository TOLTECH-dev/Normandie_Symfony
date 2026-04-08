$(document).ready(function() {

  window.checkDataInputSurfaceHabitableByColumn = function (columnType, numericFieldsToControl) {
    Object.entries(numericFieldsToControl).forEach(function([fieldCode, fieldIsRequired]) {
      var field = $("." + columnType + "-" + fieldCode);

      // Event
      field.on("blur keyup", function() {
        let elementValue = $(this).val();
        elementValue = elementValue.replace(",", ".");

        // Vider le UL généré par JS
        $("ul#error-" + columnType + "-" + fieldCode).html("");

        // Vider aussi le UL généré par Symfony côté serveur
        $(this).siblings("ul.list_error").html("");

        $("button.valider-fiche-technique").removeAttr("disabled");

        if ('1' == fieldIsRequired && !elementValue) {
            $("ul#error-" + columnType + "-" + fieldCode).html("<li>La saisie est obligatoire</li>");
            $("button.valider-fiche-technique").attr("disabled", "disabled");
        }

        if (elementValue && isNaN(elementValue)) {
          $("ul#error-" + columnType + "-" + fieldCode).html('<li>La valeur saisie doit être numérique</li>');
          $("button.valider-fiche-technique").attr('disabled', 'disabled');
        }

      });
    });

  };
});


