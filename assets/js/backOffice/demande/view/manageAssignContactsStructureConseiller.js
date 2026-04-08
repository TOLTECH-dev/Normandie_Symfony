$(document).ready(function() {

    var prefixPatnameBundle = $("#data-js-bundle").data("prefix-pathname-bundle");
    var demandeConseillerId = $("#data-js-demande").data("demande-conseiller-id");

    if ($('#demandeNbPersFoyer').length > 0 && ($('#demandeRevenuFoyer').length > 0)) {

        // Loading page
        searchStructure();

        // Event on field structure_id
        $("#form_assign_contacts_structure_id").on('change', function () {
            searchConseiller();
        });
    }

    function searchStructure() {
        var structureId = parseInt($('#form_assign_contacts_structure_id').find(':selected').val(), 10);
        if (!structureId) {
            structureId = 0;
        }
        var nombrePersonneFoyer = $('#demandeNbPersFoyer').val();
        if (nombrePersonneFoyer.length != 0) nombrePersonneFoyer = parseInt(nombrePersonneFoyer,10);
        else nombrePersonneFoyer = 0;

        var revenuFiscalRef = $('#demandeRevenuFoyer').val();
        if (revenuFiscalRef.length != 0) revenuFiscalRef = parseInt(revenuFiscalRef,10);
        else revenuFiscalRef = 0;

        var insee = 0;
        var type = 2;
        var beneficiaireId = parseInt($('#beneficiaireId').val(),10);
        var logementId = parseInt($('#logementId').val(),10);

        var isCreation = '0';
        if (
            typeof logementId == 'number' &&
            typeof insee == 'number' &&
            typeof type == 'number' &&
            typeof nombrePersonneFoyer == 'number' &&
            typeof beneficiaireId == 'number' &&
            typeof revenuFiscalRef == 'number'
        ) {
            $.ajax({
                url: Routing.generate(prefixPatnameBundle + '_autocomplete_listStructure', {logementId: logementId, insee: insee, type: type, nombrePersonneFoyer: nombrePersonneFoyer, beneficiaireId: beneficiaireId, revenuFiscalRef: revenuFiscalRef,  structureId: structureId}),
                dataType: "json",
                data: {
                    'isCreation': isCreation
                }
            }).done(function (data) {
                $('#form_assign_contacts_structure_id').empty();
                $('#form_assign_contacts_structure_id').append('<option value="" selected>-- Choisir une structure --</option>');

                /* liste des structures Recommandées */
                if (!$.isEmptyObject(data.advisedStructureList)) {
                    $('#form_assign_contacts_structure_id').append('<optgroup label="Recommandées">');
                    $.each(data.advisedStructureList, function (key, value) {
                        var selected = '';
                        if (structureId == key) {
                            selected = ' selected="selected"';
                        }
                        $('#form_assign_contacts_structure_id').append(
                            '<option value="' + key + '" ' + selected + '>' + value + '</option>'
                        );
                    });
                    $('#form_assign_contacts_structure_id').append('</optgroup>');
                }

                /* liste des structures Autres */
                if (!$.isEmptyObject(data.otherStructureList)) {
                    $('#form_assign_contacts_structure_id').append('<optgroup label="Autres">');
                    $.each(data.otherStructureList, function (key, value) {
                        var selected = '';
                        if (structureId == key) {
                            selected = ' selected="selected"';
                        }
                        $('#form_assign_contacts_structure_id').append(
                            '<option value="' + key + '" ' + selected + '>' + value + '</option>'
                        );
                    });
                    $('#form_assign_contacts_structure_id').append('</optgroup>');
                }

                if (structureId) {
                    searchConseiller();
                }
            });
        }
    }

    function searchConseiller() {
        var structureId_ = $('#form_assign_contacts_structure_id').find(':selected').val();
        $(".select-conseiller").empty();
        $('#bloc-conseillerDisplay').addClass('hidden');

        if (structureId_) {
            var structureId = parseInt(structureId_, 10);

            if (typeof structureId == 'number') {
                $.ajax({
                    url: Routing.generate(prefixPatnameBundle + '_autocomplete_listConseiller', {structureId: structureId}),
                    dataType: "json",
                    beforeSend: function () {
                        $(".select-conseiller").empty();
                    }
                }).done(function (data) {
                    $.map(data, function (item) {
                        if (item.length > 0) {
                            $('#bloc-conseillerDisplay').removeClass('hidden');
                            $(".select-conseiller").append('<option value="">-- Choisir un conseiller --</option>');

                            $.each(item, function (index, row) {
                                let stringSelected = (demandeConseillerId && demandeConseillerId == row.id) ? ' selected' : '';
                                $(".select-conseiller")
                                    .append('<option value="' + row.id + '" ' + stringSelected + '>' + row.nom + ' ' + row.prenom + '</option>');
                            });
                        }
                    });
                });
            }
        }
    }
});
