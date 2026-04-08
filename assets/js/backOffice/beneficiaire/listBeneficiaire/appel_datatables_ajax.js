$(document).ready(function()
{

    // Declare DataTable
    var table = $('#table_list_beneficiaire').DataTable({
        "language": {
            "url": "/build/json/datatable_french.json"
        },
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "pageLength": 25,
        "order": [[ 0, "desc" ]],
        "orderCellsTop": true,
        "scrollX": false,
        "stateSave": true,
        "stateDuration": -1,
        "processing": true,
        "serverSide": true,
        "ajax": {
            url: 'FC402C3B1CCBD9E9E914C9004CAE113C05957FAFCC6B0A3404D5A661307839CDA951C7695E6BA474A3DFC75F7D03769815E9BC5DF5329E5A96B41B0B5701CB22',
            dataType: 'JSON',
            type: 'POST'
        },
        "columns": [
            {"data": "beneficiaireNomPrenom", "class": "breakWord"},
            {"data": "beneficiaireType", "class": "breakWord"},
            {"data": "beneficiaireCodePostalVille", "class": "breakWord"},
            {"data": "beneficiaireEmail", "class": "breakWord"},
            {"data": "beneficiaireStructureConseiller", "class": "breakWord"},
            {"data": "nombreLogement", "class": "breakWord"}
        ],
        "columnDefs": [
            {
                "targets": 0,
                "render": function(data, type, row) {
                    return row['beneficiaireNomPrenom'];
                }
            },
            {
                "targets": 1,
                "render": function(data, type, row) {
                    return row['beneficiaireType'];
                }
            },
            {
                "targets": 2,
                "render": function(data, type, row) {
                    return row['beneficiaireCodePostalVille'];
                }
            },
            {
                "targets": 3,
                "render": function(data, type, row) {
                    return row['beneficiaireEmail'];
                }
            },
            {
                "targets": 4,
                "render": function(data, type, row) {
                    var htmlAssignStructure = '';

                    if (row['structureIdentificationRattachementNom']) {
                        var conseillerRattachement = '';
                        if (row['structureConseillerRattachementNom']) {
                            if (row['structureConseillerRattachementPrenom']) {
                                conseillerRattachement = ' - '
                                        + row['structureConseillerRattachementNom']
                                        + ' '
                                        + row['structureConseillerRattachementPrenom'];
                            } else {
                                conseillerRattachement = ' - ' + row['structureConseillerRattachementNom'];
                            }
                        }
                        var displayStructureConseiller = row['structureIdentificationRattachementNom'] + conseillerRattachement;
                        var displayModalTitle = 'Modifier une structure et un conseiller';

                    } else {
                        var displayStructureConseiller = '';
                        var displayModalTitle = 'Ajouter une structure et un conseiller';
                    }

                    htmlAssignStructure += '<span class="button-assignStructure"' +
                          'data-toggle="modal"' +
                          'data-target="#modal-assignStructure"' +
                          'data-structureRattachementId="' + row['structureRattachementId'] + '"' +
                          'data-structureConseillerRattachementId="' + row['structureConseillerRattachementId'] + '"' +
                          'data-beneficiaireId="' + row['beneficiaireId'] + '"' +
                          'data-beneficiaireNbPersFoyer="' + row['beneficiaireNbPersFoyer'] + '"' +
                          'data-beneficiaireRevenuFiscalRef="' + row['beneficiaireRevenuFiscalRef'] + '"' +
                          'data-beneficiaireINSEE="' + row['beneficiaireINSEE'] + '"' +
                    '>' +
                        '<a href="#"' +
                              'data-toggle="tooltip"' +
                              'data-placement="bottom"' +
                              'data-container="body"' +
                              'data-original-title="' + displayModalTitle + '"' +
                              'title="' + displayModalTitle + '"' +
                        '>' +
                            displayStructureConseiller +
                        '</a>'+
                    '</span>';

                    return htmlAssignStructure;
                }
            },
            {
                "targets": 5,
                "render": function(data, type, row) {
                    return row['nombreLogement'];
                }
            }
        ],
        "fnDrawCallback": function() {
            initAssignStructureEvent();
        },
        "initComplete": function() {
            // var api = this.api();

            // Apply SEARCH filter
        }
    });

    /**
     *
     * @param string
     * @param search
     * @param replace
     * @returns {string}
     */
    function replaceAll(string, search, replace) {
        return string.split(search).join(replace);
    }

    function initAssignStructureEvent() {
        $(".button-assignStructure").on('click', function () {
            $("#nomConseillerBeneficiaire").empty();
            $('#bloc-conseillerDisplay').addClass('hidden');

            var structureRattachementId = $(this).attr("data-structureRattachementId");
            var structureConseillerRattachementId = $(this).attr("data-structureConseillerRattachementId");
            var beneficiaireId = $(this).attr("data-beneficiaireId");
            var beneficiaireNbPersFoyer = $(this).attr("data-beneficiaireNbPersFoyer");
            var beneficiaireRevenuFiscalRef = $(this).attr("data-beneficiaireRevenuFiscalRef");
            var beneficiaireINSEE = $(this).attr("data-beneficiaireINSEE");

            $('#form_conseiller_rattachement_id').val(structureConseillerRattachementId);
            $('#form_beneficiaire_id').val(beneficiaireId);
            $('#form_nbPersFoyer').val(beneficiaireNbPersFoyer);
            $('#form_revenuFiscalRef').val(beneficiaireRevenuFiscalRef);
            $('#form_INSEE').val(beneficiaireINSEE);

            searchStructureRattachement(structureRattachementId);

            $("#form_structure_rattachement_id").on('change', function () {
                $("#nomConseillerBeneficiaire").empty();
                searchConseiller($(this).val());
            });
        });
    }

    function searchStructureRattachement(structureId) {
        var structureId = parseInt(structureId, 10);

        if (!structureId) {
            structureId = 0;
        }
        var nombrePersonneFoyer_ = $('#form_nbPersFoyer').val();
        var revenuFiscalRef_ = $('#form_revenuFiscalRef').val();
        var insee_ = $('#form_INSEE').val();
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
                url: "561DDA4215C9DCEE"+logementId+"A1236D2D960C9906"+insee+"1FAA17C53D0D6487"+type+"82558D619828088E"+nombrePersonneFoyer+"0AF6E0D01D7B556343C3088CDB4922D3"+beneficiaireId+"0A6E3897676FF65A"+revenuFiscalRef+"2D71123869EEF2D3"+structureId,
                dataType: "json"
                /*
                data: {
                    codePostal: codePostal,
                    ville: ville,
                    nombrePersonneFoyer: nombrePersonneFoyer,
                    revenuFiscalRef: revenuFiscalRef
                }
                */
            }).done(function (data) {
                $('#form_structure_rattachement_id').empty();
                $('#form_structure_rattachement_id').append('<option value="" selected>-- Choisir une structure --</option>');

                /* liste des structures Recommandées */
                if (!$.isEmptyObject(data.advisedStructureList)) {
                    $('#form_structure_rattachement_id').append('<optgroup label="Recommandées">');
                    $.each(data.advisedStructureList, function (key, value) {
                        var selected = '';
                        if (structureId == key) {
                            selected = ' selected="selected"';
                        }
                        $('#form_structure_rattachement_id').append(
                            '<option value="' + key + '" ' + selected + '>' + value + '</option>'
                        );
                    });
                    $('#form_structure_rattachement_id').append('</optgroup>');
                }

                /* liste des structures Autres */
                if (!$.isEmptyObject(data.otherStructureList)) {
                    $('#form_structure_rattachement_id').append('<optgroup label="Autres">');
                    $.each(data.otherStructureList, function (key, value) {
                        var selected = '';
                        if (structureId == key) {
                            selected = ' selected="selected"';
                        }
                        $('#form_structure_rattachement_id').append(
                            '<option value="' + key + '" ' + selected + '>' + value + '</option>'
                        );
                    });
                    $('#form_structure_rattachement_id').append('</optgroup>');
                }

                if (structureId) {
                    searchConseiller(structureId);
                }
            });
        }
    }

    function searchConseiller(structureId) {
        var structureId = parseInt(structureId, 10);

        if (structureId) {

            if (typeof structureId == 'number') {
                $.ajax({
                    url: "EBA7ADD1D9B308631E24770C9D533E75513DFF37664D4AEE" + structureId + "ADA61CBBC323EB32EA6075C638773B18F18B28970B40D2AF4FFC08E1F9C6E7EA341B2759C857D720",
                    dataType: "json"
                }).done(function (data) {
                    $.map(data, function (item) {
                        $("#nomConseillerBeneficiaire").empty();

                        if (item.length > 0) {
                            $('#bloc-conseillerDisplay').removeClass('hidden');
                            $('#nomConseillerBeneficiaire').append('<option value="">-- Choisir un conseiller --</option>');

                            $.each(item, function (index, row) {
                                if ($('#form_conseiller_rattachement_id').val() == row.id) {
                                    $('#nomConseillerBeneficiaire')
                                        .append('<option value="' + row.id + '" selected>' + row.nom + ' ' + row.prenom + '</option>');
                                } else {
                                    $('#nomConseillerBeneficiaire')
                                        .append('<option value="' + row.id + '">' + row.nom + ' ' + row.prenom + '</option>');
                                }
                            });
                            $('#nomConseillerBeneficiaire').on('click', function () {
                                $('#form_conseiller_rattachement_id').val($(this).val());
                            });

                        } else {
                            $('#bloc-conseillerDisplay').addClass('hidden');
                            $('#form_conseiller_rattachement_id').val('');
                        }
                    });
                });
            } else {
                $("#nomConseillerBeneficiaire").empty();
                $('#bloc-conseillerDisplay').addClass('hidden');
                $('#form_conseiller_rattachement_id').val('');
            }
        } else {
            $("#nomConseillerBeneficiaire").empty();
            $('#bloc-conseillerDisplay').addClass('hidden');
            $('#form_conseiller_rattachement_id').val('');
        }
    }

});
