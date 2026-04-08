$(document).ready(function() {

    /**
     *
     */
    function manageBlocValidationConseiller (){
        if (elementInitialValidationFicheTechnique.is(':checked')
            &&  elementBBCValidationFicheTechnique.is(':checked')
            && elementPrescriptionValidationFicheTechnique.is(':checked')
        ) {
            blocValidationConseiller.show();
        } else {
            blocValidationConseiller.hide();
        }
    }

    /**
     *
     */
    function initValidationFicheTechniqueEvents() {
        elementInitialValidationFicheTechnique
            .add(elementBBCValidationFicheTechnique)
            .add(elementPrescriptionValidationFicheTechnique)
            .on('change', function() {
                manageBlocValidationConseiller();
            });
    }

    // Validation fiche technique
    var elementInitialValidationFicheTechnique = $(".initial-validation-fiche-technique");
    var elementBBCValidationFicheTechnique = $(".BBC-validation-fiche-technique");
    var elementPrescriptionValidationFicheTechnique = $(".prescription-validation-fiche-technique");

    // Validation conseiller
    var blocValidationConseiller = $("#bloc-validation-conseiller");
    if (blocValidationConseiller.length) {
        manageBlocValidationConseiller();
        initValidationFicheTechniqueEvents();
    }
});
