/*
 * Changement de niveau d'aide : affiche le rénovateur selon le niveau choisi.
 *
 * La mise en place est nommée et exposée, car le panneau de consultation de la liste des dossiers
 * charge ce formulaire en Ajax, après le chargement de la page : il la relance à l'ouverture de sa
 * fenêtre (assets/js/backOffice/demande/listDemande/panneau.js). Sur la vue complète, rien ne
 * change : elle s'exécute au chargement, comme avant.
 */
function initialiserNiveauAide() {

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

        // Nom d'évènement propre : une remise en place ne cumule pas les branchements
        elementNiveau.off('change.niveauAide').on('change.niveauAide', function () {
            manageFormByNiveau($(this));
        });
    }
}

window.initialiserNiveauAide = initialiserNiveauAide;

$(document).ready(initialiserNiveauAide);
