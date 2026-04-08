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
    arrayDemandeType[331] = 'Travaux niveau 3 - Rénovation BBC (1/2)';
    arrayDemandeType[332] = 'Travaux niveau 3 - Rénovation BBC (2/2)';
    arrayDemandeType[341] = 'Travaux niveau 3 - Biosourcé (1/2)';
    arrayDemandeType[342] = 'Travaux niveau 3 - Biosourcé (2/2)';
    arrayDemandeType[36] = 'Travaux - Sortie de passoire';
    arrayDemandeType[37] = 'Travaux - Première étape BBC avec RGE';
    arrayDemandeType[381] = 'Travaux - Première étape BBC avec Rénovateur (1/2)';
    arrayDemandeType[382] = 'Travaux - Première étape BBC avec Rénovateur (2/2)';
    arrayDemandeType[391] = 'Travaux - Rénovation globale BBC (1/2)';
    arrayDemandeType[392] = 'Travaux - Rénovation globale BBC (2/2)';

    var arrayTypeAndNiveauForTravaux = ['3', '30', '31', '32', '33', '331', '332', '341', '342', '35', '36', '37', '381', '382', '391', '392'];


    var arrayRole = new Array();
    arrayRole['ROLE_AUDITEUR'] = $('#js-role').data('is-auditeur');
    arrayRole['ROLE_RENOVATEUR'] = $('#js-role').data('is-renovateur');
    arrayRole['ROLE_CONSEILLER'] = $('#js-role').data('is-conseiller');
    arrayRole['ROLE_INSTRUCTEUR'] = $('#js-role').data('is-instructeur');
    arrayRole['ROLE_INSTRUCTEUR_UP'] = $('#js-role').data('is-instructeur-up');
    arrayRole['ROLE_EPCI'] = $('#js-role').data('is-epci');
    arrayRole['ROLE_CLIENT'] = $('#js-role').data('is-client');
    arrayRole['ROLE_ADMIN'] = $('#js-role').data('is-admin');
    arrayRole['ROLE_TECHNIQUE'] = $('#js-role').data('is-technique');

    // Declare DataTable
    var table = $('#table_list_remboursement').DataTable({
        "language": {
            "url": "/build/json/datatable_french.json"
        },
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "pageLength": 25,
        "order": [[ 1, "desc" ]],
        "orderCellsTop": true,
        "scrollX": false,
        "stateSave": true,
        "stateDuration": -1,
        "processing": true,
        "serverSide": true,
        "ajax": {
            url: Routing.generate('remboursement_list_ajax'),
            dataType: 'JSON',
            type: 'POST'
        },
        "columns": [
            {"data": "demandeId", "class": "breakWord text-center"},
            {"data": "numeroCheque", "class": "breakWord text-center"},
            {"data": "beneficiaire", "class": "breakWord"},
            {"data": "logement", "class": "breakWord"},
            {"data": "statut", "class": "breakWord"},
            {"data": "structureConseiller", "class": "breakWord"},
            {"data": "partenaire", "class": "breakWord"},
            {"data": "demandeType", "class": "breakWord"},
            {"data": "RMHDate", "class": "breakWord text-center"},
            {"data": "action", "class": "breakWord text-center", "searchable": false, "sortable": false}
        ],
        "columnDefs": [
            {
                "targets": 4,
                "render": function(data, type, row) {
                    var html = '';

                    if (row['remboursementId'] !== null && row['remboursementId'] !== '') {
                        html += ''
                            + row['remboursementStatutSlug'];

                        if (row['remboursementStatutDescriptionFormatted']) {
                            html += '<span class="statut-tip">'
                                + '<a role="button"'
                                + 'tabIndex="0"'
                                + 'data-container="body"'
                                + 'data-toggle="popover"'
                                + 'data-trigger="focus"'
                                + 'data-placement="top"'
                                + 'data-content="' + replaceAll(row['remboursementStatutDescriptionFormatted'], '"', '&quot;') + '"'
                                + '>'
                                + '<i class="glyphicon glyphicon-question-sign"></i>'
                                + '</a>'
                                + '</span>';
                        }

                    } else {
                        // On affiche le demande statut slug (sans le detail)
                        html += ''
                            + row['demandeStatutSlug'];
                    }

                    return html;
                }
            },
            {
                "targets": 7,
                "render": function (data, type, row) {
                    var html = '';

                    if(arrayDemandeType[row['demandeTypeDetaille']]) {
                        html = ''
                            + arrayDemandeType[row['demandeTypeDetaille']];
                    }
                    return html;
                }
            },
            {
                "targets": 8,
                "render": function(data, type, row) {
                    var html = '';

                    if (row['RMHDate']) {
                        var date = new Date(row['RMHDate']);
                        var jour = ('0'+date.getDate()).slice(-2);
                        var mois = ('0'+(date.getMonth()+1)).slice(-2);
                        var annee = date.getFullYear();
                    }

                    if (
                        (1 === arrayRole['ROLE_CLIENT'] || 1 === arrayRole['ROLE_ADMIN'])
                        && ['14', '21'].includes(String(row['remboursementStatutId'])) === true
                        && ['22', '23', '24', '25', '26', '27'].includes(String(row['remboursementStatutId'])) === false
                    ) {

                        if (row['RMHDate'] && '21' == row['remboursementStatutId']) {
                            html += ''
                                + '<span class="hidden">' + annee + mois + jour + '</span>';
                            html += ''
                                + '<a href="761EB5BA7CC01262522890A0FF1437DF7E879CFBA3450C42B5323CABE9255D3A9D797A16430B828616BFF12ECDDC' + '1' + 'C6F1A5AD7'
                                    + row['logementId'] + '6258CA016' + row['beneficiaireId'] + '544F29CF7' + row['remboursementId'] + 'DC3585310">'
                                + jour
                                + '/'
                                + mois
                                + '/'
                                + annee
                                + '</a>';

                        } else if ('14' == row['remboursementStatutId']) {
                            html += ''
                                + '<a href="761EB5BA7CC01262522890A0FF1437DF7E879CFBA3450C42B5323CABE9255D3A9D797A16430B828616BFF12ECDDC' + '0' + 'C6F1A5AD7'
                                    + row['logementId'] + '6258CA016' + row['beneficiaireId'] + '544F29CF7' + row['remboursementId'] + 'DC3585310">'
                                    + 'Affecter une date'
                                + '</a>';
                        }

                    } else {
                        if (row['RMHDate']) {
                            html += ''
                                + '<span class="hidden">' + annee + mois + jour + '</span>'
                                + jour
                                + '/'
                                + mois
                                + '/'
                                + annee
                        }
                    }

                    return html;
                }
            },
            {
                "targets": 9,
                "render": function (data, type, row) {
                    var html = '';

                    if (!row['RMHDate']) {

                        var pathNameExamine = '';
                        var pathNameReexamine = '';
                        var pathNameAddDepot = '';
                        var pathNameEditDepot = '';
                        var pathNameExamineFicheTechnique = '';
                        var pathNameReexamineFicheTechnique = '';

                        if ('1' == row['demandeTypeDetaille'] || '4' == row['demandeTypeDetaille']) {
                            pathNameExamine = 'remboursement_auditEnergie_examine';
                            pathNameReexamine = 'remboursement_auditEnergie_reexamine';
                            pathNameAddDepot = 'remboursement_auditEnergie_addDepot';
                            pathNameEditDepot = 'remboursement_auditEnergie_editDepot';
                            pathNameExamineFicheTechnique = '';
                            pathNameReexamineFicheTechnique = '';
                        } else if ('2' == row['demandeTypeDetaille'] || '5' == row['demandeTypeDetaille']) {
                            pathNameExamine = 'remboursement_auditNumerique_examine';
                            pathNameReexamine = 'remboursement_auditNumerique_reexamine';
                            pathNameAddDepot = 'remboursement_auditNumerique_addDepot';
                            pathNameEditDepot = 'remboursement_auditNumerique_editDepot';
                            pathNameExamineFicheTechnique = '';
                            pathNameReexamineFicheTechnique = '';
                        } else if (arrayTypeAndNiveauForTravaux.includes(row['demandeTypeDetaille'])) {
                            pathNameExamine = 'remboursement_travaux_examine';
                            pathNameReexamine = 'remboursement_travaux_reexamine';
                            pathNameAddDepot = '';
                            pathNameEditDepot = '';
                            pathNameExamineFicheTechnique = 'remboursement_travaux_examineFicheTechnique';
                            pathNameReexamineFicheTechnique = 'remboursement_travaux_reexamineFicheTechnique';
                        }

                        if ('22' != row['remboursementStatutId']) {
                            // ---------- INSTRUCTION ----------
                            if (1 === arrayRole['ROLE_INSTRUCTEUR']
                                || 1 === arrayRole['ROLE_INSTRUCTEUR_UP']
                                || 1 === arrayRole['ROLE_CLIENT']
                                || 1 === arrayRole['ROLE_ADMIN']
                            ) {

                                if (row['instructionId'] && pathNameExamine && pathNameReexamine) {
                                    var pathExamine = Routing.generate(pathNameReexamine, {remboursementId: row['remboursementId']});
                                    var classLinkExamine = 'btn-default';
                                    var titleLinkExamine = 'Ré-instruire un remboursement';
                                    var buttonExamine = 'edit';
                                } else {
                                    if (!pathNameExamine) {
                                        console.error('Aucune route examine définie pour ce type de remboursement', row);
                                        // Optionnel : afficher un bouton désactivé ou un message utilisateur
                                        var pathExamine = '#';
                                        var classLinkExamine = 'btn-disabled';
                                        var titleLinkExamine = 'Aucune route examine disponible';
                                        var buttonExamine = 'ban';
                                    } else {
                                        var pathExamine = Routing.generate(pathNameExamine, {titreId: row['titreId']});
                                        var classLinkExamine = 'btn-primary';
                                        var titleLinkExamine = 'Instruire un remboursement';
                                        var buttonExamine = 'play';
                                    }
                                }

                                html += ''
                                    + '<a href="' + pathExamine + '"'
                                        + 'class="btn ' + classLinkExamine + ' btn-xs"'
                                        + 'role="button"'
                                        + 'data-toggle="tooltip"'
                                        + 'data-placement="bottom"'
                                        + 'data-container="body"'
                                        + 'title="' + titleLinkExamine + '"'
                                    + '>'
                                        + '<i class="glyphicon glyphicon-' + buttonExamine + '"></i>'
                                    + '</a>';
                            }
                        }
                        // ---------- FIN INSTRUCTION ----------

                        if (['20', '22'].includes(String(row['remboursementStatutId'])) === false) {

                            // ---------- DEPOT ----------
                            if (1 === arrayRole['ROLE_AUDITEUR']
                                || 1 === arrayRole['ROLE_CLIENT']
                                || 1 === arrayRole['ROLE_ADMIN']
                            ) {

                                // Si different de demande travaux
                                if (arrayTypeAndNiveauForTravaux.includes(row['demandeTypeDetaille']) === false) {
                                    if (row['depotId']) {
                                        var pathDepot = Routing.generate(pathNameEditDepot, {remboursementId: row['remboursementId']});
                                        var classLinkDepot = 'btn-success';
                                        var titleLinkDepot = 'Modifier les pièces justificatives';
                                    } else {
                                        if (!pathNameAddDepot) {
                                            console.error('Aucune route depot définie pour ce type de remboursement', row);
                                            var pathDepot = '#';
                                            var classLinkDepot = 'btn-disabled';
                                            var titleLinkDepot = 'Aucune route dépôt disponible';
                                        } else {
                                            var pathDepot = Routing.generate(pathNameAddDepot, {demandeId: row['demandeId']});
                                            var classLinkDepot = 'btn-primary';
                                            var titleLinkDepot = 'Importer les pièces justificatives';
                                        }
                                    }

                                    html += ''
                                        + '&nbsp;<a href="' + pathDepot + '"'
                                            + 'class="btn ' + classLinkDepot + ' btn-xs"'
                                            + 'role="button"'
                                            + 'data-toggle="tooltip"'
                                            + 'data-placement="bottom"'
                                            + 'data-container="body"'
                                            + 'title="' + titleLinkDepot + '"'
                                            + '>'
                                            + '<i class="glyphicon glyphicon-plus"></i>'
                                        + '</a>';
                                }
                            }
                            // ---------- FIN DEPOT ----------

                            // ---------- FICHE TECHNIQUE ----------
                            if (1 === arrayRole['ROLE_CONSEILLER']
                                || 1 === arrayRole['ROLE_AUDITEUR']
                                || 1 === arrayRole['ROLE_RENOVATEUR']
                                || 1 === arrayRole['ROLE_CLIENT']
                                || 1 === arrayRole['ROLE_ADMIN']
                            ) {
                                if (arrayTypeAndNiveauForTravaux.includes(row['demandeTypeDetaille']) === true
                                    && 1 == row['isTechnicalAccess']
                                    && pathNameExamineFicheTechnique
                                    && pathNameReexamineFicheTechnique
                                ) {

                                    if ('0' == row['statutFicheTechnique'] && row['ficheTechniqueId']) {
                                        var pathFicheTechnique = Routing.generate(pathNameReexamineFicheTechnique, {remboursementId: row['remboursementId']});
                                        var classLinkFicheTechnique = 'btn-warning';
                                        var titleLinkFicheTechnique = 'Reprendre la fiche technique';
                                    } else if ('1' == row['statutFicheTechnique'] && row['ficheTechniqueId']) {
                                        var pathFicheTechnique = Routing.generate(pathNameReexamineFicheTechnique, {remboursementId: row['remboursementId']});
                                        var classLinkFicheTechnique = 'btn-success';
                                        var titleLinkFicheTechnique = 'Modifier la fiche technique';
                                    } else {
                                        var pathFicheTechnique = Routing.generate(pathNameExamineFicheTechnique, {titreId: row['titreId']});
                                        var classLinkFicheTechnique = 'btn-primary';
                                        var titleLinkFicheTechnique = 'Renseigner la fiche technique';
                                    }

                                    html += ''
                                        + '&nbsp;<a href="' + pathFicheTechnique + '"'
                                            + 'class="btn ' + classLinkFicheTechnique + ' btn-xs"'
                                            + 'role="button"'
                                            + 'data-toggle="tooltip"'
                                            + 'data-placement="bottom"'
                                            + 'data-container="body"'
                                            + 'title="' + titleLinkFicheTechnique + '"'
                                            + '>'
                                            + '<i class="glyphicon glyphicon-list-alt"></i>'
                                        + '</a>';
                                }
                            }
                            // ---------- FIN FICHE TECHNIQUE ----------

                            // ---------- COMMENTAIRES ----------
                            if (1 === arrayRole['ROLE_CONSEILLER']
                                || 1 === arrayRole['ROLE_INSTRUCTEUR']
                                || 1 === arrayRole['ROLE_INSTRUCTEUR_UP']
                                || 1 === arrayRole['ROLE_AUDITEUR']
                                || 1 === arrayRole['ROLE_RENOVATEUR']
                                || 1 === arrayRole['ROLE_CLIENT']
                                || 1 === arrayRole['ROLE_ADMIN']
                            ) {
                                var buttonColor = '';
                                if (row['countCommentaire']>0) buttonColor = "btn-danger";
                                else buttonColor = "btn-primary";

                                var htmlCommentaire = '';
                                htmlCommentaire +=
                                    '&nbsp;<button id="button_addCommentaire_' + row['demandeId'] + '"\n' +
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

                                html += htmlCommentaire;
                            }
                            // ---------- FIN COMMENTAIRES ----------

                        }
                    }

                    return html;
                }
            },
        ],
        "fnDrawCallback": function() {
            $('[data-toggle="popover"]').popover(
                {html: true}
            );
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


    /**
     * Fonction pour sauvegarder le commentaire
     *
     * @param demandeUrl
     */
    function saveEvent_commentaire(demandeUrl) {
        $('#modal_formCommentaire .modal-body form').submit(function(e) {
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
    function createModalEvent_commentaire(button_addCommentaire) {
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
    function setModalContent_commentaire(demandeUrl, title) {
        $('.modal_formCommentaire .modal-body').load(demandeUrl, function() {
            $('.modal_formCommentaire #modal_formCommentaire-titleText').html(title);
            $('#modal_formCommentaire').modal({ show: true });

            saveEvent_commentaire(demandeUrl);
        });
    }

});
