$(document).ready(function() {

    /**
     *
     * @param number
     * @param decimals
     * @param dec_point
     * @param thousands_point
     */
    function number_format(number, decimals, dec_point, thousands_point) {
        if (number == null || !isFinite(number)) {
            throw new TypeError("Le nombre est invalide");
        }

        if (!decimals) {
            var len = number.toString().split('.').length;
            decimals = len > 1 ? len : 0;
        }

        if (!dec_point) {
            dec_point = '.';
        }

        if (!thousands_point) {
            thousands_point = ',';
        }

        number = (parseFloat(number).toFixed(decimals)).replace(".", dec_point);

        var splitNum = number.split(dec_point);
        splitNum[0] = splitNum[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousands_point);
        number = splitNum.join(dec_point);

        return number;
    }

    $('#button_popupValidation').on('click', function () {

        var valueNiveau = $('#whitelabel_frontofficebundle_demande_travaux_devis_niveau').val();
        var keyRenovationGlobaleBBC = $('#js-type-travaux-keys').data('key-renovation-globale-bbc');
        var informationANAH = $('#js-type-travaux-keys').data('information-anah');
        var revenuReferenceEntre2et4FoisANAHKey = $('#js-type-travaux-keys').data('revenu-reference-entre-2-et-4-fois-anah-key');

        var renovationGlobaleBBCMinDevisTotal = parseInt($('#js-type-travaux-keys').data('renovation-globale-bbc-min-devis-total'), 10);

        // Calcule le total des montants uploadés
        var totalMontantsDevisUpload = 0;
        $('.montantDevis').each(function () {
            let val = parseInt($(this).val(), 10);
            if (!isNaN(val) && val > 0) {
                totalMontantsDevisUpload += val;
            }
        });

        $('#whitelabel_frontofficebundle_demande_travaux_devis_valider').on('click', function () {
            $('#modalRenovationGlobaleBBCControleDevis').modal('hide');
            $('#modalDifferenceDevisEtPlanFinancement').modal('hide');
        });

        if (
            (
                (informationANAH == revenuReferenceEntre2et4FoisANAHKey)
                && (valueNiveau != keyRenovationGlobaleBBC || totalMontantsDevisUpload < renovationGlobaleBBCMinDevisTotal)
            )
            || (
                informationANAH != revenuReferenceEntre2et4FoisANAHKey
                && valueNiveau == keyRenovationGlobaleBBC
                && totalMontantsDevisUpload < renovationGlobaleBBCMinDevisTotal
            )
        ) {
            // ON AFFICHE LA MODAL
            $('#modalRenovationGlobaleBBCControleDevis').modal('show');
        } else {

            var valueFinancement = $('#whitelabel_frontofficebundle_demande_travaux_devis_totalPlan').val();
            var valueFinancementFormatted = (valueFinancement.length != 0) ? parseInt(valueFinancement, 10) : 0;

            if (totalMontantsDevisUpload == valueFinancementFormatted) {
                // ON SUBMIT LE FORM
                $('#whitelabel_frontofficebundle_demande_travaux_devis_valider').trigger('click');
            } else {
                // ON MET A JOUR LE CONTENU DE LA MODAL DEDIÉE ET ON L'AFFICHE
                $("#valueDevis").html("<span>" + number_format(totalMontantsDevisUpload, 0, ',', ' ') + "</span>");
                $("#valueFinancement").html("<span>" + number_format(valueFinancementFormatted, 0, ',', ' ') + "</span>");
                $('#modalDifferenceDevisEtPlanFinancement').modal('show');
            }
        }
    });
});
