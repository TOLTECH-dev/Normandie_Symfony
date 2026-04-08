$(document).ready(function() {
    var prefixPatnameBundle = $("#data-js-bundle").data("prefix-pathname-bundle");
    var elt_sci = $('#whitelabel_frontofficebundle_beneficiaire_type');

    if (elt_sci.find(':checked').val() == '1 | sci') {
        manageFieldsForSCI();
    } else {
        manageFieldsForParticulier();
    }

    var val_isFranceConnect = $('#isFranceConnect').val();
    if (val_isFranceConnect == '1') {
        $('#whitelabel_frontofficebundle_beneficiaire_type_1').prop("disabled", true);
    }

    /* *****************************************************************
                On initialise les options correspondantes
    *******************************************************************/
    elt_sci.on('click', function () {
        if ($(this).find(':checked').val() == '1 | sci') {
            manageFieldsForSCI();
        } else {
            manageFieldsForParticulier();
        }
    });

    ////////////////////////////////////////////////////////////////////

    var elt_situationFamille = $('#whitelabel_frontofficebundle_beneficiaire_situationFamille');

    if ($('#whitelabel_frontofficebundle_beneficiaire_situationFamille').val() == '1 | marie'
        || $('#whitelabel_frontofficebundle_beneficiaire_situationFamille').val() == '2 | union_libre'
        || $('#whitelabel_frontofficebundle_beneficiaire_situationFamille').val() == '4 | pacse'
    ) {
        displayConjoint();
    } else {
        hideConjoint();
    }

    /* *****************************************************************
                On initialise les options correspondantes
    *******************************************************************/
    elt_situationFamille.on('click', function () {
        if ($(this).find(':selected').val() == '1 | marie'
            || $(this).find(':selected').val() == '2 | union_libre'
            || $(this).find(':selected').val() == '4 | pacse'
        ) {
            displayConjoint();
        } else {
            hideConjoint();
        }
    });

    function displayConjoint() {
        $('.bloc-nomConjoint').removeClass('hidden');
        $('.bloc-nomConjoint label').addClass('required');
        $('#whitelabel_frontofficebundle_beneficiaire_nomConjoint').prop("required", true);

        $('.bloc-prenomConjoint').removeClass('hidden');
        $('.bloc-prenomConjoint label').addClass('required');
        $('#whitelabel_frontofficebundle_beneficiaire_prenomConjoint').prop("required", true);

        var elt_nomConjointForm = $('#box-nomConjoint').val();
        if (elt_nomConjointForm) $('#whitelabel_frontofficebundle_beneficiaire_nomConjoint').prop('value', elt_nomConjointForm);

        var elt_prenomConjointForm = $('#box-prenomConjoint').val();
        if (elt_prenomConjointForm) $('#whitelabel_frontofficebundle_beneficiaire_prenomConjoint').prop('value', elt_prenomConjointForm);
    }

    function hideConjoint() {
        $('.bloc-nomConjoint').addClass('hidden');
        $('.bloc-nomConjoint label').removeClass('required');
        $('#whitelabel_frontofficebundle_beneficiaire_nomConjoint').prop("required", false);
        $('#whitelabel_frontofficebundle_beneficiaire_nomConjoint').val('');

        $('.bloc-prenomConjoint').addClass('hidden');
        $('.bloc-prenomConjoint label').removeClass('required');
        $('#whitelabel_frontofficebundle_beneficiaire_prenomConjoint').prop("required", false);
        $('#whitelabel_frontofficebundle_beneficiaire_prenomConjoint').val('');
    }

    /**
     *
     */
    function manageFieldsForParticulier() {
        $('#bloc-nomSCI').addClass('hidden');
        $('#box_information').addClass('hidden');
        $("#bloc-situationFamille").removeClass('hidden');

        $("#labelCiviliteBeneficiaire").html('Civilité');
        $("#labelNomBeneficiaire").html('Nom');
        $("#labelPrenomBeneficiaire").html('Prénom');

        $("#labelNbPersFoyer").html('Nombre de personnes constituant le foyer');
        $("#labelRevenuFiscalRef").html('Revenu fiscal de référence du foyer (n-1 ou n-2)');

        $('#whitelabel_frontofficebundle_beneficiaire_nomSCI').val('');
        $('#whitelabel_frontofficebundle_beneficiaire_nomSCI').prop("required", false);
        $("label[for='whitelabel_frontofficebundle_beneficiaire_nomSCI']").removeClass('required');

        $('#whitelabel_frontofficebundle_beneficiaire_situationFamille').prop("required", true);
        $("label[for='whitelabel_frontofficebundle_beneficiaire_situationFamille']").addClass('required');
    }

    /**
     *
     */
    function manageFieldsForSCI() {
        $('#box_information').removeClass('hidden');
        $('#bloc-nomSCI').removeClass('hidden');
        $("#bloc-situationFamille").addClass('hidden');
        $('#whitelabel_frontofficebundle_beneficiaire_situationFamille').val('');

        $("#labelCiviliteBeneficiaire").html('Civilité gérant');
        $("#labelNomBeneficiaire").html('Nom gérant');
        $("#labelPrenomBeneficiaire").html('Prénom gérant');

        $("#labelNbPersFoyer").html('Nombre de personnes constituant la SCI (foyer fiscal)');
        $("#labelRevenuFiscalRef").html('Revenu fiscal de référence des personnes constituant la SCI (n-1 ou n-2)');

        $('#whitelabel_frontofficebundle_beneficiaire_nomSCI').prop("required", true);
        $("label[for='whitelabel_frontofficebundle_beneficiaire_nomSCI']").addClass('required');

        $('#whitelabel_frontofficebundle_beneficiaire_situationFamille').prop("required", false);
        $("label[for='whitelabel_frontofficebundle_beneficiaire_situationFamille']").removeClass('required');
        hideConjoint();
    }

    ////////////////////////////////////////////////////////////////////

    if ('fo' == prefixPatnameBundle) {
        // COTE FRONT OFFICE
        // ON PREREMPLIT LES VALEURS DU BENEFICIAIRE PAR CEUX DU USER (SI DONNÉES BENEF. VIDE)
        var elt_userNom = $('#userNom').val();
        var elt_beneficiaireNom = $('#beneficiaireNom').val();
        if (elt_userNom && !elt_beneficiaireNom) $('#whitelabel_frontofficebundle_beneficiaire_nom').prop('value', elt_userNom);

        var elt_userPrenom = $('#userPrenom').val();
        var elt_beneficiairePrenom = $('#beneficiairePrenom').val();
        if (elt_userPrenom && !elt_beneficiairePrenom) $('#whitelabel_frontofficebundle_beneficiaire_prenom').prop('value', elt_userPrenom);

        var elt_userEmail = $('#userEmail').val();
        var elt_beneficiaireEmail = $('#beneficiaireEmail').val();
        if (elt_userEmail && !elt_beneficiaireEmail) $('#whitelabel_frontofficebundle_beneficiaire_email').prop('value', elt_userEmail);
    } else {
        // COTE BACK OFFICE
        $("label[for='whitelabel_frontofficebundle_beneficiaire_email']").removeClass('required');
        $('#whitelabel_frontofficebundle_beneficiaire_email').prop('readonly', false);
        $('#whitelabel_frontofficebundle_beneficiaire_email').prop('required', false);
    }
});
