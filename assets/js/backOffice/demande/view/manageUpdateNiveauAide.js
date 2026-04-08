$(document).ready(function() {

    var elementNiveau = $('#form_travaux_devis_update_niveau_aide_niveau');
    var elementRenovateurId = $('#box-valueRenovateur');
    if (elementNiveau.length > 0) {
        // On est dans le form changement de niveau d'aide
        var niveauEtape1BBCRenovateurValue = $('#data-js-demande-niveau').data('niveau-etape1-bbc-renovateur-value');
        var niveauRenovationGlobaleBBCValue = $('#data-js-demande-niveau').data('niveau-renovation-globale-bbc-value');
    }

    /**
     *
     */
    function showRenovateur() {
        $('#box-renovateur').removeClass('hidden');
        $("label[for='form_travaux_devis_update_niveau_aide_renovateur_id']").addClass('required');
        $('#form_travaux_devis_update_niveau_aide_renovateur_id').attr("required", true);
    }

    /**
     *
     */
    function hideRenovateur() {
        $('#box-renovateur').addClass('hidden');
        $("label[for='form_travaux_devis_update_niveau_aide_renovateur_id']").removeClass('required');
        $('#form_travaux_devis_update_niveau_aide_renovateur_id').attr("required", false);
        $('#form_travaux_devis_update_niveau_aide_renovateur_id').val('');
    }

    /**
     *
     * @param elementNiveauParam
     */
    function manageFormByNiveau(elementNiveauParam) {
        if (elementNiveauParam.find(':selected').val() == niveauEtape1BBCRenovateurValue
            || elementNiveauParam.find(':selected').val() == niveauRenovationGlobaleBBCValue
        ) {
            showRenovateur();
            if (elementRenovateurId.length > 0 && elementRenovateurId.val()) {
                $('#form_travaux_devis_update_niveau_aide_renovateur_id option[value=' + elementRenovateurId.val() + ']').attr("selected", "selected");
            }
        } else {
            hideRenovateur();
        }
    }

    if (elementNiveau.length > 0) {
        // On est dans le form changement de niveau d'aide
        manageFormByNiveau(elementNiveau);

        elementNiveau.on('change', function () {
            manageFormByNiveau($(this));
        });
    }
});
