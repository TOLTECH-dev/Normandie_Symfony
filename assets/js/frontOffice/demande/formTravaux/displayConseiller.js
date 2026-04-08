$(document).ready(function()
{
    var prefixPatnameBundle = $("#data-js-bundle").data("prefix-pathname-bundle");
    var demandeConseillerId = $("#data-js-demande").data("demande-conseiller-id");

    // Loading page
    searchStructure();
    addCriteriaEvents();

    // Event on field structure_id
    $("#whitelabel_frontofficebundle_demande__demande_travaux_structure_id").on('change', function () {
        searchConseiller();
    });

    function addCriteriaEvents() {
        $("#whitelabel_frontofficebundle_demande__demande_travaux_nbPersFoyer, #whitelabel_frontofficebundle_demande__demande_travaux_revenu1, #whitelabel_frontofficebundle_demande__demande_travaux_revenu2").on("change blur keyup load", function () {
            searchStructure();
        });
    }

    function searchStructure() {
        var structureId = parseInt($('#whitelabel_frontofficebundle_demande__demande_travaux_structure_id').find(':selected').val(), 10);
        if (!structureId) {
            structureId = 0;
        }
        var nombrePersonneFoyer_ = $('#whitelabel_frontofficebundle_demande__demande_travaux_nbPersFoyer').val();
        var revenuFiscalRef_ = $('#whitelabel_frontofficebundle_demande__demande_travaux_revenu3').val();
        var insee = 0;
        var type = 1;
        var beneficiaireId = parseInt($('#beneficiaireId').val(),10);
        var logementId = parseInt($('#logementId').val(),10);

        var nombrePersonneFoyer = '';
        if (nombrePersonneFoyer_.length != 0) nombrePersonneFoyer = parseInt(nombrePersonneFoyer_,10);
        else nombrePersonneFoyer = 0;

        var revenuFiscalRef = '';
        if (revenuFiscalRef_.length != 0) revenuFiscalRef = parseInt(revenuFiscalRef_,10);
        else revenuFiscalRef = 0;

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
                $('#whitelabel_frontofficebundle_demande__demande_travaux_structure_id').empty();
                $('#whitelabel_frontofficebundle_demande__demande_travaux_structure_id').append('<option value="" selected>-- Choisir une structure --</option>');

                /* liste des structures Recommandées */
                if (!$.isEmptyObject(data.advisedStructureList)) {
                    $('#whitelabel_frontofficebundle_demande__demande_travaux_structure_id').append('<optgroup label="Recommandées">');
                    $.each(data.advisedStructureList, function (key, value) {
                        var selected = '';
                        if (structureId == key) {
                            selected = ' selected="selected"';
                        }
                        $('#whitelabel_frontofficebundle_demande__demande_travaux_structure_id').append(
                            '<option value="' + key + '" ' + selected + '>' + value + '</option>'
                        );
                    });
                    $('#whitelabel_frontofficebundle_demande__demande_travaux_structure_id').append('</optgroup>');
                }

                /* liste des structures Autres */
                if (!$.isEmptyObject(data.otherStructureList)) {
                    $('#whitelabel_frontofficebundle_demande__demande_travaux_structure_id').append('<optgroup label="Autres">');
                    $.each(data.otherStructureList, function (key, value) {
                        var selected = '';
                        if (structureId == key) {
                            selected = ' selected="selected"';
                        }
                        $('#whitelabel_frontofficebundle_demande__demande_travaux_structure_id').append(
                            '<option value="' + key + '" ' + selected + '>' + value + '</option>'
                        );
                    });
                    $('#whitelabel_frontofficebundle_demande__demande_travaux_structure_id').append('</optgroup>');
                }

                if (structureId) {
                    searchConseiller();
                }
            });
        }
    }

    function searchConseiller() {
        var structureId_ = $('#whitelabel_frontofficebundle_demande__demande_travaux_structure_id').find(':selected').val();
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
