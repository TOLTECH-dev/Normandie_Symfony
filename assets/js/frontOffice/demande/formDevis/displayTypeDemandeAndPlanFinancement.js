$(document).ready(function() {

    /**
     *
     */
    function calculTotalPlan() {

        var valueRevenu1 = $('#whitelabel_frontofficebundle_demande_travaux_devis_aideRegion').val();
        var valueRevenu2 = $('#whitelabel_frontofficebundle_demande_travaux_devis_aideDepartement').val();
        var valueRevenu3 = $('#whitelabel_frontofficebundle_demande_travaux_devis_aideIntercommunalite').val();
        var valueRevenu4 = $('#whitelabel_frontofficebundle_demande_travaux_devis_creditImpot').val();
        // var valueRevenu5 = $('#whitelabel_frontofficebundle_demande_travaux_devis_aideHabiterMieux').val();
        var valueRevenu6 = $('#whitelabel_frontofficebundle_demande_travaux_devis_CEE').val();
        var valueRevenu7 = $('#whitelabel_frontofficebundle_demande_travaux_devis_EcoPTZ').val();
        var valueRevenu8 = $('#whitelabel_frontofficebundle_demande_travaux_devis_fondsPropres').val();
        var valueRevenu9 = $('#whitelabel_frontofficebundle_demande_travaux_devis_autrePret').val();
        var valueRevenu10 = $('#whitelabel_frontofficebundle_demande_travaux_devis_autreAide').val();

        var arrayRevenus = [
            valueRevenu1,
            valueRevenu2,
            valueRevenu3,
            valueRevenu4,
            // valueRevenu5,
            valueRevenu6,
            valueRevenu7,
            valueRevenu8,
            valueRevenu9,
            valueRevenu10
        ];

        var revenuTotal = 0;

        $.each(arrayRevenus, function( keyRevenu, valRevenu ) {
            if (valRevenu.length != 0) {
                revenuTotal += parseInt(valRevenu, 10);
            }
        });

        $('#whitelabel_frontofficebundle_demande_travaux_devis_totalPlan').attr('value', revenuTotal);
    }

    /**
     *
     * @param obj
     * @param selectTypeId
     */
    function managePlanFinancementType(obj, selectTypeId) {
        if ($.trim(obj.val() != '') && parseInt($.trim(obj.val())) > 0) {
            $("label[for='" + selectTypeId + "']").addClass('required');
            $("#" + selectTypeId).attr("required", true);
        } else {
            $("label[for='" + selectTypeId + "']").removeClass('required');
            $("#" + selectTypeId).attr("required", false);
        }
    }

    /**
     *
     * @param obj
     * @param objComplementId
     */
    function manageAideInput(obj, objComplementId) {
        if ($.trim(obj.val() != '') && parseInt($.trim(obj.val())) > 0) {
            $("label[for='" + objComplementId + "']").addClass('required');
            $("input#" + objComplementId).attr("required", true);
        } else {
            $("label[for='" + objComplementId + "']").removeClass('required');
            $("input#" + objComplementId).attr("required", false);
        }
    }

    /**
     *
     */
    function showRenovateur() {
        $('#box-renovateur').removeClass('hidden');
        $("label[for='whitelabel_frontofficebundle_demande_travaux_devis_renovateur_id']").addClass('required');
        $('#whitelabel_frontofficebundle_demande_travaux_devis_renovateur_id').attr("required", true);
    }

    /**
     *
     */
    function hideRenovateur() {
        $('#box-renovateur').addClass('hidden');
        $("label[for='whitelabel_frontofficebundle_demande_travaux_devis_renovateur_id']").removeClass('required');
        $('#whitelabel_frontofficebundle_demande_travaux_devis_renovateur_id').attr("required", false);
        $('#whitelabel_frontofficebundle_demande_travaux_devis_renovateur_id').val('');
    }

    /**
     *
     * @param elementNiveauParam
     * @param elementIsBonificationAideParam
     */
    function manageFormByNiveauAndBonification(elementNiveauParam, elementIsBonificationAideParam) {

        if (elementNiveauParam.find(':selected').val() == niveauEtape1BBCRenovateurValue
            || elementNiveauParam.find(':selected').val() == niveauRenovationGlobaleBBCValue
        ) {
            showRenovateur();
        } else {
            hideRenovateur();
        }

        // AIDE REGION
        var elementDevisAideRegion = $('#whitelabel_frontofficebundle_demande_travaux_devis_aideRegion');
        elementDevisAideRegion.val('');
        niveauAndMontantList.forEach(function (valueItem, indexItem) {
            let niveau = valueItem[0];
            let montant = valueItem[1];

            if (elementNiveauParam.find(':selected').val() == niveau) {
                elementDevisAideRegion.val(montant);
                return;
            }
        });

        // Mise à jour Montant Aide Region par rapport à la checkbox bonification Aide
        let montantBonificationAideRetenu = (elementIsBonificationAideParam.is(':checked')) ? montantBonificationAide : 0;
        if (montantBonificationAideRetenu > 0) {
            let aideRegion = parseInt(elementDevisAideRegion.val());
            aideRegion += montantBonificationAideRetenu;
            elementDevisAideRegion.val(aideRegion);
        }

        calculTotalPlan();
    }


    var elementDevisTotalPlan = $('#whitelabel_frontofficebundle_demande_travaux_devis_totalPlan');
    if (elementDevisTotalPlan.val())  {
        var revenuInit = elementDevisTotalPlan.val();
    } else {
        var revenuInit = 0;
    }
    elementDevisTotalPlan.attr('value', revenuInit);

    $("#bloc-plan input[type=number]").on('change blur keyup load', function () {
        calculTotalPlan();
    });

    /*
    MANAGE DES CHAMPS SELECT TYPE (MISE A OBLIGATOIRE OU NON)
     */
    let typeMaPrimeRenovInput = $("#whitelabel_frontofficebundle_demande_travaux_devis_creditImpot");
    let typeMaPrimeRenovNom = 'whitelabel_frontofficebundle_demande_travaux_devis_typeMaPrimeRenovNom';
    managePlanFinancementType(typeMaPrimeRenovInput, typeMaPrimeRenovNom);
    typeMaPrimeRenovInput.on('change blur keyup', function () {
        managePlanFinancementType($(this), typeMaPrimeRenovNom);
    });

    // let typeMaPrimeRenovSereniteInput = $("#whitelabel_frontofficebundle_demande_travaux_devis_aideHabiterMieux");
    // let typeMaPrimeRenovSereniteNom = 'whitelabel_frontofficebundle_demande_travaux_devis_typeMaPrimeRenovSereniteNom';
    // managePlanFinancementType(typeMaPrimeRenovSereniteInput, typeMaPrimeRenovSereniteNom);
    // typeMaPrimeRenovSereniteInput.on('change blur keyup', function () {
    //     managePlanFinancementType($(this), typeMaPrimeRenovSereniteNom);
    // });

    /*
    MANAGE CHAMP AIDE DEPARTEMENTALE (MISE A OBLIGATOIRE OU NON)
    */
    let aideDepartementInput = $("#whitelabel_frontofficebundle_demande_travaux_devis_aideDepartement");
    let aideDepartementOrigineInputId = 'whitelabel_frontofficebundle_demande_travaux_devis_aideDepartementOrigine';
    manageAideInput(aideDepartementInput, aideDepartementOrigineInputId);
    aideDepartementInput.on('change blur keyup', function () {
        manageAideInput($(this), aideDepartementOrigineInputId);
    });

    /*
    MANAGE CHAMP AIDE INTERCOMMUNALITÉ (MISE A OBLIGATOIRE OU NON)
    */
    let aideIntercommunaliteInput = $("#whitelabel_frontofficebundle_demande_travaux_devis_aideIntercommunalite");
    let aideIntercommunaliteOrigineInputId = 'whitelabel_frontofficebundle_demande_travaux_devis_aideIntercommunaliteOrigine';
    manageAideInput(aideIntercommunaliteInput, aideIntercommunaliteOrigineInputId);
    aideIntercommunaliteInput.on('change blur keyup', function () {
        manageAideInput($(this), aideIntercommunaliteOrigineInputId);
    });

    var elementNiveau = $('#whitelabel_frontofficebundle_demande_travaux_devis_niveau');
    var elementIsBonificationAide = $('#whitelabel_frontofficebundle_demande_travaux_devis_isBonificationAide');
    var value_renovateur = $('#box-valueRenovateur').val();

    var montantBonificationAide = parseInt($('#data-js-demande-montant').data('montant-bonification-aide'));

    var niveauSortiePassoireValue = $('#data-js-demande-niveau').data('niveau-sortie-passoire-value');
    var niveauEtape1BBCRGEValue = $('#data-js-demande-niveau').data('niveau-etape1-bbc-rge-value');
    var niveauEtape1BBCRenovateurValue = $('#data-js-demande-niveau').data('niveau-etape1-bbc-renovateur-value');
    var niveauRenovationGlobaleBBCValue = $('#data-js-demande-niveau').data('niveau-renovation-globale-bbc-value');

    var montantSortiePassoire = parseInt($('#data-js-demande-montant').data('montant-sortie-passoire'));
    var montantEtape1BBCRGE = parseInt($('#data-js-demande-montant').data('montant-etape1-bbc-rge'));
    var montantEtape1BBCRenovateur = parseInt($('#data-js-demande-montant').data('montant-etape1-bbc-renovateur'));
    var montantRenovationGlobaleBBC = parseInt($('#data-js-demande-montant').data('montant-renovation-globale-bbc'));

    var niveauAndMontantList = [
        [niveauSortiePassoireValue, montantSortiePassoire],
        [niveauEtape1BBCRGEValue, montantEtape1BBCRGE],
        [niveauEtape1BBCRenovateurValue, montantEtape1BBCRenovateur],
        [niveauRenovationGlobaleBBCValue, montantRenovationGlobaleBBC]
    ];

    if (value_renovateur) {
        showRenovateur();
        $('#whitelabel_frontofficebundle_demande_travaux_devis_renovateur_id option[value=' + value_renovateur + ']').attr("selected", "selected");
    }

    manageFormByNiveauAndBonification(elementNiveau, elementIsBonificationAide);

    elementNiveau.on('change', function () {
        manageFormByNiveauAndBonification($(this), elementIsBonificationAide);
    });

    elementIsBonificationAide.on('change', function () {
        manageFormByNiveauAndBonification(elementNiveau, $(this));
    });

    if ('1' == $('#disabled_button_popupValidation_niveau').val()) {
        $('#button_popupValidation').prop('disabled', true);
    }

});
