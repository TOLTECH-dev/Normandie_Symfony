$(document).ready(function(){
    var prefixPatnameBundle = $("#data-js-bundle").data("prefix-pathname-bundle");

    searchStructureRattachement();

    // Event on field codePostal
    $('#whitelabel_frontofficebundle_beneficiaire_codePostal').on('change blur keyup load', function () {
        searchStructureRattachement();
    });

    // Event on field ville
    $('#whitelabel_frontofficebundle_beneficiaire_ville').on('change blur keyup load', function () {
        searchStructureRattachement();
    });

    // Event on field nbPersFoyer
    $('#whitelabel_frontofficebundle_beneficiaire_nbPersFoyer').on('change blur keyup load', function () {
        searchStructureRattachement();
    });

    // Event on field revenuFiscalRef
    $('#whitelabel_frontofficebundle_beneficiaire_revenuFiscalRef').on('change blur keyup load', function () {
        searchStructureRattachement();
    });

    // Event on field structure_rattachement_id
    $('#whitelabel_frontofficebundle_beneficiaire_structure_rattachement_id').on('change', function () {
        $('#whitelabel_frontofficebundle_beneficiaire_conseiller_rattachement_id').val('');
    });


    function searchStructureRattachement() {
        var structureRattachementId = parseInt($('#whitelabel_frontofficebundle_beneficiaire_structure_rattachement_id').find(':selected').val(), 10);
        if (!structureRattachementId) {
            structureRattachementId = 0;
        }
        var nombrePersonneFoyer_ = $('#whitelabel_frontofficebundle_beneficiaire_nbPersFoyer').val();
        var revenuFiscalRef_ = $('#whitelabel_frontofficebundle_beneficiaire_revenuFiscalRef').val();
        var insee_ = $('#whitelabel_frontofficebundle_beneficiaire_INSEE').val();
        var type = 0;
        var beneficiaireId = 0;
        var logementId = 0;
        
        var nombrePersonneFoyer = '';
        if (nombrePersonneFoyer_.length != 0) nombrePersonneFoyer = parseInt(nombrePersonneFoyer_,10);
        else nombrePersonneFoyer = 0;

        var revenuFiscalRef = '';
        if (revenuFiscalRef_.length != 0) revenuFiscalRef = parseInt(revenuFiscalRef_,10);
        else revenuFiscalRef = 0;

        var insee = '';
        if (insee_.length != 0) insee = parseInt(insee_,10);
        else insee = 0;

        if (
            typeof logementId == 'number' &&
            typeof insee == 'number' &&
            typeof type == 'number' &&
            typeof nombrePersonneFoyer == 'number' &&
            typeof beneficiaireId == 'number' &&
            typeof revenuFiscalRef == 'number'
        ) {
            $.ajax({
                url: Routing.generate(prefixPatnameBundle + '_autocomplete_listStructure', {logementId: logementId, insee: insee, type: type, nombrePersonneFoyer: nombrePersonneFoyer, beneficiaireId: beneficiaireId, revenuFiscalRef: revenuFiscalRef,  structureId: structureRattachementId}),
                dataType: "json"
            }).done(function (data) {
                $('#whitelabel_frontofficebundle_beneficiaire_structure_rattachement_id').empty();
                $('#whitelabel_frontofficebundle_beneficiaire_structure_rattachement_id').append('<option value="" selected>-- Choisir une structure --</option>');

                /* liste des structures Recommandées */
                if (!$.isEmptyObject(data.advisedStructureList)) {
                    $('#whitelabel_frontofficebundle_beneficiaire_structure_rattachement_id').append('<optgroup label="Recommandées">');
                    $.each(data.advisedStructureList, function (key, value) {
                        var selected = '';
                        if (structureRattachementId == key) {
                            selected = ' selected="selected"';
                        }
                        $('#whitelabel_frontofficebundle_beneficiaire_structure_rattachement_id').append(
                            '<option value="' + key + '" ' + selected + '>' + value + '</option>'
                        );
                    });
                    $('#whitelabel_frontofficebundle_beneficiaire_structure_rattachement_id').append('</optgroup>');
                }

                /* liste des structures Autres */
                if (!$.isEmptyObject(data.otherStructureList)) {
                    $('#whitelabel_frontofficebundle_beneficiaire_structure_rattachement_id').append('<optgroup label="Autres">');
                    $.each(data.otherStructureList, function (key, value) {
                        var selected = '';
                        if (structureRattachementId == key) {
                            selected = ' selected="selected"';
                        }
                        $('#whitelabel_frontofficebundle_beneficiaire_structure_rattachement_id').append(
                            '<option value="' + key + '" ' + selected + '>' + value + '</option>'
                        );
                    });
                    $('#whitelabel_frontofficebundle_beneficiaire_structure_rattachement_id').append('</optgroup>');
                }
            });
        }
    }

    ////////////////////////////////////////////////////////////////////

    var structure_wrapper = $('#bloc-structureConseiller');
    var structure_checkbox = $('#checkboxForStructure');
    var structure_selectBloc = $('#bloc-structure');
    var structure_selectId = $('#whitelabel_frontofficebundle_beneficiaire_structure_id');
    displaySelect(structure_checkbox, structure_selectBloc, structure_selectId, structure_wrapper);

    var auditeur_checkbox = $('#checkboxForAuditeur');
    var auditeur_selectBloc = $('#bloc-auditeur');
    var auditeur_selectId = $('#whitelabel_frontofficebundle_beneficiaire_auditeur_id');
    displaySelect(auditeur_checkbox, auditeur_selectBloc, auditeur_selectId, null);

    var renovateur_checkbox = $('#checkboxForRenovateur');
    var renovateur_selectBloc = $('#bloc-renovateur');
    var renovateur_selectId = $('#whitelabel_frontofficebundle_beneficiaire_renovateur_id');
    displaySelect(renovateur_checkbox, renovateur_selectBloc, renovateur_selectId, null);


    var financeur_checkbox = $('#checkboxForFinanceur');
    var financeur_selectBloc = $('#bloc-financeur');
    var financeur_selectId = $('#whitelabel_frontofficebundle_beneficiaire_financeur_id');
    displaySelect(financeur_checkbox, financeur_selectBloc, financeur_selectId, null);

    function displaySelect(checkbox, selectBloc, selectId, option) {
        if (selectId.find(':selected').val()) {
            checkbox.prop('checked', true);
            if (option) option.removeClass('hidden');
            selectBloc.removeClass('hidden');
        }

        checkbox.on('click', function() {
            if (checkbox.is(':checked')) {
                if (option) option.removeClass('hidden');
                selectBloc.removeClass('hidden');
            } else {
                if (option) {
                    option.addClass('hidden');
                }
                selectBloc.addClass('hidden');
                selectId.val('');
            }
        });
    }
});
