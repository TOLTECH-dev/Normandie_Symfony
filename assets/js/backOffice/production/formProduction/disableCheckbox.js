$( document ).ready(function() {
    $('#checkAll').click(function() {
        $("input:checkbox").prop('checked', $(this).prop("checked"));
    });

    $('input:checkbox').click(function() {
        if (($('input:checkbox').is(':checked')) == true) {
            $('#whitelabel_backofficebundle_production__valider').prop('disabled', false);
        } else {
            $('#whitelabel_backofficebundle_production__valider').prop('disabled', true);
        }
    });

    var countData = $('input.count_data').data('value');

    // Vérifier que countData existe et est un tableau
    if (!countData || !Array.isArray(countData)) {
        return;
    }

    var isDisabledBtnValider = true;
    countData.forEach(function (totalItem) {
        if (totalItem != 0) {
            isDisabledBtnValider = false;
        }
    });

    if (isDisabledBtnValider) {
        $('#whitelabel_backofficebundle_production__valider').prop('disabled', true);
    }

    var nombreProductionType = 4;
    var cptAll = 0;

    for (cpt = 0; cpt < nombreProductionType; cpt++) {
        if (countData[cpt] == 0) {
            $('#whitelabel_backofficebundle_production__type_' + cpt).prop('disabled', true);
            $('#checkAll').prop('disabled', true);
        }
        cptAll++;
    }

    var cptNiveau = 0;
    for(cpt = cptAll; cpt < countData.length; cpt++) {
        if (countData[cptAll] == 0) {
            $('#whitelabel_backofficebundle_production__niveau_' + cptNiveau).prop('disabled', true);
            $('#checkAll').prop('disabled', true);
        }
        cptAll++;
        cptNiveau++;
    }
});
