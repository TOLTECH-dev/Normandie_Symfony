$(document).ready(function()
{
    var arrayDemandeType =  new Array();
    arrayDemandeType[1] = 'Audit énergétique et scénarios';
    arrayDemandeType[2] = 'Audit numérique';
    arrayDemandeType[3] = 'Travaux à définir';
    arrayDemandeType[4] = 'Audit énergétique Région Normandie';
    arrayDemandeType[5] = 'Mise à jour Audit énergétique et scénarios';
    arrayDemandeType[30] = 'Travaux niveau 1';
    arrayDemandeType[31] = 'Travaux niveau 2';
    arrayDemandeType[32] = 'Travaux niveau 2 - Rénovateur BBC';
    arrayDemandeType[33] = 'Travaux niveau 3 - Rénovation BBC';
    arrayDemandeType[34] = 'Travaux niveau 3 – Biosourcé';
    arrayDemandeType[36] = 'Travaux - Sortie de passoire';
    arrayDemandeType[37] = 'Travaux - Première étape BBC avec RGE';
    arrayDemandeType[38] = 'Travaux - Première étape BBC avec Rénovateur';
    arrayDemandeType[39] = 'Travaux - Rénovation globale BBC';

    var arrayCommissionDateStatut = ['8', '11'];

    var arrayRole = new Array();
    arrayRole['ROLE_AUDITEUR'] = $('#js-role').data('is-auditeur');
    arrayRole['ROLE_RENOVATEUR'] = $('#js-role').data('is-renovateur');
    arrayRole['ROLE_CONSEILLER'] = $('#js-role').data('is-conseiller');
    arrayRole['ROLE_INSTRUCTEUR'] = $('#js-role').data('is-instructeur');
    arrayRole['ROLE_EPCI'] = $('#js-role').data('is-epci');
    arrayRole['ROLE_CLIENT'] = $('#js-role').data('is-client');
    arrayRole['ROLE_ADMIN'] = $('#js-role').data('is-admin');
    arrayRole['ROLE_TECHNIQUE'] = $('#js-role').data('is-technique');

    // Declare DataTable
    var table = $('#table_list_demande').DataTable({
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
            url: 'DD2D8051DB8125BC7F19769BA283322B04C696F31CF4930971E731E0BBE9EDD71830B50847AB8C31B6A81E936F882FBE9EF28DC48D2B531959C9A606F7A1DA75',
            dataType: 'JSON',
            type: 'POST'
        },
        "columns": [
            {"data": "demandeId", "class": "breakWord text-center"},
            {"data": "demandeType", "class": "breakWord"},
            {"data": "beneficiaireIdentifiant", "class": "breakWord"},
            {"data": "logement", "class": "breakWord"},
            {"data": "demandeDateCreation", "class": "breakWord text-center"},
            {"data": "demandeStatutSlug", "class": "breakWord"},
            {"data": "structureConseiller", "class": "breakWord"},
            {"data": "auditEId", "class": "breakWord text-center", "searchable": false},
            {"data": "partenaire", "class": "breakWord"},
            {"data": "commissionDate", "class": "breakWord text-center"},
            {"data": "remboursementDate", "class": "breakWord text-center"},
            {"data": "action", "class": "breakWord text-center", "searchable": false, "sortable": false}
        ],
        "columnDefs": [
            {
                "targets": 1,
                "render": function(data, type, row) {
                    var typeDisplay = '';

                    if(arrayDemandeType[row['demandeType']]) {
                        typeDisplay = arrayDemandeType[row['demandeType']];
                    }
                    if (row['demandeTravauxDevisIsBonificationAide'] == 1) {
                        typeDisplay += ' + Bonification';
                    }

                    if (1 === arrayRole['ROLE_CONSEILLER']
                        || 1 === arrayRole['ROLE_INSTRUCTEUR']
                        || 1 === arrayRole['ROLE_AUDITEUR']
                        || 1 === arrayRole['ROLE_RENOVATEUR']
                        || 1 === arrayRole['ROLE_EPCI']
                        || 1 === arrayRole['ROLE_CLIENT']
                        || 1 === arrayRole['ROLE_ADMIN']
                        || 1 === arrayRole['ROLE_TECHNIQUE']
                    ) {
                        return '' +
                            '<a href="7F256'+row['demandeId']+'36CDEC912AB0BE1F57442E7D2B6B975594DE56F1548CC3C121FBB8E176DFDBDA2A6E50306E505BAB7F9FA5FBEEA129B0915CBD6869B3FE4E0C4375FDF6C' + '"\n' +
                            'class="tooltip-consultation"\n' +
                            'data-toggle="tooltip"\n' +
                            'data-placement="bottom"\n' +
                            'data-container="body"\n' +
                            'title="Consulter la Demande">' +
                            typeDisplay +
                            '</a>'
                            ;
                    } else {
                        return typeDisplay;
                    }
                }
            },
            {
                "targets": 4,
                "render": function(data, type, row) {
                    var dateCreationDisplay = '';
                    if (row['demandeDateCreation']) {
                        //var explode_dateCreation = row['demandeDateCreation'].split('##');

                        var date = new Date(row['demandeDateCreation']);
                        var jour = ('0'+date.getDate()).slice(-2);
                        var mois = ('0'+(date.getMonth()+1)).slice(-2);
                        var annee = date.getFullYear();

                        dateCreationDisplay = ''
                            + jour
                            + '/'
                            + mois
                            + '/'
                            + annee
                        ;
                    }

                    return dateCreationDisplay;
                }
            },
            {
                "targets": 5,
                "render": function(data, type, row) {
                    let statutSlug = (row['remboursementId']) ? row['remboursementStatutSlug'] : row['demandeStatutSlug'];
                    let statutDescriptionFormatted = (row['remboursementId']) ? row['remboursementStatutDescriptionFormatted'] : row['demandeStatutDescriptionFormatted'];
                    var html = '';

                    html += statutSlug +
                        '<span class="marginLeft5">' +
                        '<a class="popover-statutMotif"\n' +
                        'role="button"\n' +
                        'tabindex="0"\n' +
                        'data-container="body"\n' +
                        'data-html="true"\n' +
                        'data-toggle="popover"\n' +
                        'data-trigger="focus"\n' +
                        'data-placement="top"\n' +
                        'data-content="' + replaceAll(statutDescriptionFormatted, '"', '&quot;') + '"' +
                        '>' +
                        '<i class="glyphicon glyphicon-question-sign"></i>' +
                        '</a>' +
                        '</span>'
                    ;

                    return html;
                }
            },
            {
                "targets": 7,
                "render": function(data, type, row) {
                    var auditEDisplay = '';

                    // Si type de demande est numerique ou travaux
                    if (1 !== row['demandeType'] && 4 !== row['demandeType']) {
                        if (row['auditEId']) {
                            auditEDisplay = 'Audit n&deg;' + row['auditEId'];
                        } else {
                            if (row['audit'] && 1 !== row['audit']) {
                                auditEDisplay = 'Sans Audit';
                            } else {
                                auditEDisplay = 'Ancien audit';
                            }
                        }
                    }

                    return auditEDisplay;
                }
            },
            {
                "targets": 9,
                "render": function(data, type, row) {
                    if (row['commissionDate']) {
                        //var explode_dateCommission = row['commissionDate'].split('##');

                        var date = new Date(row['commissionDate']);
                        var jour = ('0'+date.getDate()).slice(-2);
                        var mois = ('0'+(date.getMonth()+1)).slice(-2);
                        var annee = date.getFullYear();
                    }

                    var commissionDateDisplay = '';
                    if ((1 === arrayRole['ROLE_CLIENT'] || 1 === arrayRole['ROLE_ADMIN'])
                        && true === arrayCommissionDateStatut.includes(String(row['demandeStatutId']))
                    ) {
                        if (row['commissionDate']) {
                            commissionDateDisplay = ''
                                + '<a href="99051237B5F7DC1D1625BA56FB65B91BC54B1DA31482A19EE70F1B3DFA8E9030C3D002E5DAE225EC716ACF97782415F1F469DE'+row['logementId']+'807DB67AC'+row['beneficiaireId']+'D3639C106'+row['demandeId']+'25E7FED61">'
                                + jour
                                + '/'
                                + mois
                                + '/'
                                + annee
                                + '</a>'
                            ;
                        } else {
                            commissionDateDisplay = '' +
                                '<a href="99051237B5F7DC1D1625BA56FB65B91BC54B1DA31482A19EE70F1B3DFA8E9030C3D002E5DAE225EC716ACF97782405F1F469DE'+row['logementId']+'807DB67AC'+row['beneficiaireId']+'D3639C106'+row['demandeId']+'25E7FED61">' +
                                'Affecter une date' +
                                '</a>'
                            ;
                        }
                    } else {
                        if (row['commissionDate']) {
                            commissionDateDisplay = ''
                                + jour
                                + '/'
                                + mois
                                + '/'
                                + annee
                            ;
                        }
                    }

                    return commissionDateDisplay;
                }
            },
            {
                "targets": 10,
                "render": function(data, type, row) {
                    var dateRemboursementDisplay = '';
                    if (row['remboursementDate']) {
                        var date = new Date(row['remboursementDate']);
                        var jour = ('0'+date.getDate()).slice(-2);
                        var mois = ('0'+(date.getMonth()+1)).slice(-2);
                        var annee = date.getFullYear();

                        dateRemboursementDisplay = ''
                            + jour
                            + '/'
                            + mois
                            + '/'
                            + annee
                        ;
                    }

                    return dateRemboursementDisplay;
                }
            },
            {
                "targets": 11,
                "render": function(data, type, row) {

                    if (1 === arrayRole['ROLE_AUDITEUR']
                        || 1 === arrayRole['ROLE_RENOVATEUR']
                        || 1 === arrayRole['ROLE_EPCI']
                        || 1 === arrayRole['ROLE_CONSEILLER']
                        || 1 === arrayRole['ROLE_INSTRUCTEUR']
                        || 1 === arrayRole['ROLE_CLIENT']
                        || 1 === arrayRole['ROLE_ADMIN']
                        || 1 === arrayRole['ROLE_TECHNIQUE']
                    ) {

                        var htmlHistorique = '';
                        htmlHistorique +=
                            '<a href="' + Routing.generate('demande_historique', {demandeId: row['demandeId'], redirectRoute: 'demande_list_all'}) + '"\n' +
                            'class="tooltip-historique btn btn-primary btn-xs"\n' +
                            'role="button"\n' +
                            'data-toggle="tooltip"\n' +
                            'data-placement="bottom"\n' +
                            'data-container="body"\n' +
                            'title="Afficher son historique"' +
                            '>' +
                            '<i class="glyphicon glyphicon-time"></i>' +
                            '</a>'
                        ;

                        var buttonColor = '';
                        if (row['countCommentaire']>0) buttonColor = "btn-danger";
                        else buttonColor = "btn-primary";

                        var htmlCommentaire = '';
                        htmlCommentaire +=
                            '<button id="button_addCommentaire_' + row['demandeId'] + '"\n' +
                            'class="tooltip-commentaire btn ' + buttonColor + ' btn-xs button_addCommentaire"\n' +
                            'type="button"\n' +
                            'title="Commenter la Demande"\n' +
                            'data-toggle="tooltip"\n' +
                            'data-placement="bottom"\n' +
                            'data-container="body"\n' +
                            'data-demandeId="' + row['demandeId'] + '"\n' +
                            'data-demandeUrl="' + Routing.generate('demande_create_commentaire', {demandeId: row['demandeId']}) + '"\n' +
                            'data-original-title="Commenter la Demande"' +
                            '>' +
                            '<i class="glyphicon glyphicon-comment"></i>' +
                            '</button>'
                        ;

                        saveEvent_commentaire(null);
                        createModalEvent_commentaire('button_addCommentaire_' + row['demandeId']);

                        return htmlHistorique + '&nbsp;' + htmlCommentaire;
                    } else {
                        return '';
                    }
                }
            }
        ],
        "fnDrawCallback": function() {
            $('.tooltip-consultation').tooltip();
            $('.tooltip-historique').tooltip();
            $('.tooltip-commentaire').tooltip();
            $('.popover-statutMotif').popover();
        },
        "initComplete": function() {
            var api = this.api();

            // Apply SEARCH filter
            api.columns('.with_search').every(function() {
                var that = this;
                var title = $(that.footer()).text();
                var id = $(that.footer()).prop('id');
                $(that.footer()).html('<input type="text" name="' + id + '" placeholder="' + title + '" />');

                var search_thread = null;
                $('input', this.footer()).on('keyup change', function() {
                    var this_val = $(this).val();

                    if (that.search() !== this_val) {
                        clearTimeout(search_thread);
                        search_thread = setTimeout(function () {
                        that
                            .search($.fn.dataTable.util.escapeRegex(this_val))
                            .draw();
                        }, 1000);
                    }
                });
            });

            // Apply SELECT filter
            api.columns('.with_select').every(function () {
                var column = this;
                var id = $(this.footer()).prop('id');

                var select = $('<select name="' + id + '"><option value=""></option></select>')
                    .appendTo($(column.footer()).empty())
                    .on('change', function () {
                        var val = $.fn.dataTable.util.escapeRegex(
                            $(this).val()
                        );
                        column
                            .search( val ? val : '', true, false)
                            .draw();
                    });

                $.each(arrayDemandeType, function( k, v ) {
                    if (v) {
                        select.append("<option value=\"" + k + "\">" + v + "</option>");
                    }
                });
            });

            // Restore state
            var state = table.state.loaded();
            if (state) {
                table.columns().eq(0).each(function (colIdx) {
                    var colSearch = state.columns[colIdx].search;
                    var indexChild = parseInt(colIdx+1);

                    $('tr#filterrow th:nth-child( ' + indexChild + ') input').val(colSearch.search);
                    $('tr#filterrow th:nth-child( ' + indexChild + ') select').val(colSearch.search);
                });
            }
        }
    });

    // Clear all filter
    $('#clear_search_input').on('click', function () {

        $('tr#filterrow th input').val('');
        $('tr#filterrow th select').val('');

        table
            .columns()
            .search( '' )
            .columns( '.with_search .with_select' )
            .search( '' )
            .draw();
    });

    // Export Demandes
    $('#exporter_demandes').on('click', function () {
        var urlDemandeExport = '6D5F241CC65A855A9D05EC30CCAB02C7BC21A6566F69AF1A1837D2A5F411ECB2B93B1950F85E43025A9BA96A9508F41243FE62FF83836EBA8E56DA8121610306';
        $("form#formDemandeListAll").prop('action', urlDemandeExport);
    });

    $('form#formDemandeListAll').bind('keypress', function(e) {
        if(e.keyCode == 13) {
            return false;
        }
    });

    /**
     * Fonction pour sauvegarder le commentaire
     *
     * @param demandeUrl
     */
    function saveEvent_commentaire(demandeUrl)
    {
        $('#modal_formCommentaire .modal-body form').submit(function(e)
        {
            e.preventDefault();

            if ($('#whitelabel_backofficebundle_historique_email_enregistrer').hasClass('disabled')) {
                return false;
            }
            $('#whitelabel_backofficebundle_historique_email_enregistrer').addClass('disabled');

            $.ajax({
                type: $(this).attr('method'),
                url: demandeUrl,
                data: $(this).serialize()
            }).done(function (data) {
                location.reload();
            }).fail(function (jqXHR, textStatus, errorThrown) {
                if ('undefined' !== typeof jqXHR.responseJSON && jqXHR.responseJSON.hasOwnProperty('form')) {
                    $('#modal_formCommentaire .modal-body').html(jqXHR.responseJSON.form);
                    saveEvent_commentaire(demandeUrl, demandeId);
                }
            });
        });
    }

    /**
     * Fonction pour ouvrir la modal d'ajout de commentaire
     */
    function createModalEvent_commentaire(button_addCommentaire)
    {
        $(document).on("click", '#'+button_addCommentaire, function(e)
        {
            var demandeId = $(this).attr('data-demandeId');
            var demandeUrl = $(this).attr('data-demandeUrl');
            var title = "Demande " + demandeId;

            setModalContent_commentaire(demandeUrl, title.fontcolor("#77b636"));

            e.preventDefault();
        });
    }

    /**
     * Fonction pour charger le contenu de la modal
     *
     * @param demandeUrl
     * @param title
     */
    function setModalContent_commentaire(demandeUrl, title)
    {
        $('.modal_formCommentaire .modal-body').load(demandeUrl, function()
        {
            $('.modal_formCommentaire #modal_formCommentaire-titleText').html(title);
            $('#modal_formCommentaire').modal({ show: true });

            saveEvent_commentaire(demandeUrl);
        });
    }

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
});
