$(document).ready(function()
{
    var elt_isDoublon = $('#box-isDoublon');
    var elt_formDisplayStructure = $('#box-formDisplayStructure');
    var elt_formDisplayConseiller = $('#box-formDisplayConseiller');
    var prefixPatnameBundle = $("#data-js-bundle").data("prefix-pathname-bundle");

    if (true != elt_isDoublon.val()) {
        document.getElementById('box-structure').innerHTML = ""
            + "<input id='input-structure' class='form-control' readonly='readonly' name='input-structure' placeholder='Structure retenue' value='"
            + elt_formDisplayStructure.val()
            + "' type='text' required='required' />"
        ;

        $('#bloc-conseillerDisplay').removeClass('hidden');
        document.getElementById('box-conseiller').innerHTML = ""
            + "<input id='input-conseiller' class='form-control' readonly='readonly' name='input-conseiller' placeholder='Conseiller retenu' value='"
            + elt_formDisplayConseiller.val()
            + "' type='text' required='required' />"
        ;
    } else {
        searchStructure();
    }

    function searchStructure() {
        var structureId = parseInt($('#whitelabel_frontofficebundle_demande__demande_auditNumerique_structure_id').val(), 10);
        if (!structureId) {
            structureId = 0;
        }
        var nombrePersonneFoyer = 0;
        var revenuFiscalRef = 0;
        var insee = 0;
        var type = 1;
        var beneficiaireId = parseInt($('#beneficiaireId').val(),10);
        var logementId = parseInt($('#logementId').val(),10);

        var isCreation = $('#isCreation').val();

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
                document.getElementById('box-structure').innerHTML = ""
                    + "<div class='custom-select'>"
                    + "<select id='input-structure' class='form-control' required='required'>"
                    + "<option value='' selected='selected'>-- Choisir une structure --</option>"
                    + "</select>"
                    + "</div>"
                ;

                /* liste des structures Recommandées */
                if (!$.isEmptyObject(data.advisedStructureList)) {
                    $('#input-structure').append('<optgroup label="Recommandées">');
                    $.each(data.advisedStructureList, function (key, value) {
                        var selected = '';
                        if (structureId == key) {
                            selected = ' selected="selected"';
                        }
                        $('#input-structure').append(
                            '<option value="' + key + '" ' + selected + '>' + value + '</option>'
                        );
                    });
                    $('#input-structure').append('</optgroup>');
                }

                /* liste des structures Autres */
                if (!$.isEmptyObject(data.otherStructureList)) {
                    $('#input-structure').append('<optgroup label="Autres">');
                    $.each(data.otherStructureList, function (key, value) {
                        var selected = '';
                        if (structureId == key) {
                            selected = ' selected="selected"';
                        }
                        $('#input-structure').append(
                            '<option value="' + key + '" ' + selected + '>' + value + '</option>'
                        );
                    });
                    $('#input-structure').append('</optgroup>');
                }

                $('#input-structure').on('click', function () {
                    var valueStructure = $(this).val();
                    $('#whitelabel_frontofficebundle_demande__demande_auditNumerique_structure_id').prop('value', valueStructure);
                    searchConseiller();
                });

                if (structureId) {
                    searchConseiller();
                }
            });
        }
    }

    function searchConseiller() {
        var structureId_ = $('#input-structure').find(':selected').val();

        if (structureId_) {
            var structureId = parseInt(structureId_, 10);

            if (typeof structureId == 'number') {
                $.ajax({
                    url: Routing.generate(prefixPatnameBundle + '_autocomplete_listConseiller', {structureId: structureId}),
                    dataType: "json",
                    beforeSend: function () {
                        $("#input-conseiller").empty();
                    }
                }).done(function (data) {
                    $.map(data, function (item) {
                        if (item.length > 0) {
                            $('#bloc-conseillerDisplay').removeClass('hidden');
                            document.getElementById('box-conseiller').innerHTML = ""
                                + "<div class='custom-select'>"
                                + "<select id='input-conseiller' class='form-control' required='required'>"
                                + "<option value='' selected='selected'>-- Choisir un conseiller --</option>"
                                + "</select>"
                                + "</div>"
                            ;

                            $.each(item, function (index, row) {
                                if ($('#whitelabel_frontofficebundle_demande__demande_auditNumerique_conseiller_id').val() != null &&
                                    $('#whitelabel_frontofficebundle_demande__demande_auditNumerique_conseiller_id').val() == row.id) {
                                    $('#input-conseiller')
                                        .append('<option value="' + row.id + '" selected>' + row.nom + ' ' + row.prenom + '</option>');
                                } else {
                                    $('#input-conseiller')
                                        .append('<option value="' + row.id + '">' + row.nom + ' ' + row.prenom + '</option>');
                                }
                            });

                            $('#input-conseiller').on('click change', function () {
                                var valueConseiller = $(this).val();
                                $('#whitelabel_frontofficebundle_demande__demande_auditNumerique_conseiller_id').prop('value', valueConseiller);
                            });
                        } else {
                            $("#input-conseiller").empty();
                            $('#bloc-conseillerDisplay').addClass('hidden');
                            $('#whitelabel_frontofficebundle_demande__demande_auditNumerique_conseiller_id').val('');
                        }
                    });
                });
            } else {
                $("#input-conseiller").empty();
                $('#bloc-conseillerDisplay').addClass('hidden');
                $('#whitelabel_frontofficebundle_demande__demande_auditNumerique_conseiller_id').val('');
            }
        } else {
            $("#input-conseiller").empty();
            $('#bloc-conseillerDisplay').addClass('hidden');
            $('#whitelabel_frontofficebundle_demande__demande_auditNumerique_conseiller_id').val('');
        }
    }
});
