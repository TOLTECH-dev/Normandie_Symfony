import { etiquetteStatut, cellule, dateCourte, filtreListe, brancherRecherches } from '../../common/cellules_tableau';

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
        "order": [[ 0, "desc" ]],
        "orderCellsTop": true,
        "scrollX": false,
        "stateSave": true,
        "stateDuration": -1,
        // La recherche générale part au serveur : on attend la fin de la frappe
        "searchDelay": 400,
        "processing": true,
        "serverSide": true,
        "ajax": {
            url: Routing.generate('remboursement_list_ajax'),
            dataType: 'JSON',
            type: 'POST'
        },
        /*
         * Colonnes regroupées : chaque cellule porte une information principale et, en dessous, ses
         * compléments. Le nom logique (« name ») est celui que le serveur reconnaît : une colonne
         * regroupée y cherche dans tous ses champs (cf. RemboursementListSql).
         * La thématique et le statut sont affichés dans la première colonne, mais restent déclarés
         * comme colonnes masquées, pour garder leur filtre propre.
         */
        "columns": [
            {"data": "demandeId", "name": "dossier", "class": "colonne-dossier"},
            {"data": "beneficiaire", "name": "beneficiaire", "class": "colonne-beneficiaire"},
            {"data": "structureConseiller", "name": "structureConseiller", "class": "colonne-accompagnement"},
            {"data": "partenaire", "name": "partenaire", "class": "colonne-professionnel"},
            {"data": "RMHDate", "name": "RMHDate", "class": "colonne-rmh"},
            {"data": "action", "name": "action", "class": "colonne-action", "searchable": false, "sortable": false},
            {"data": "demandeType", "name": "demandeType", "visible": false},
            {"data": "statut", "name": "statut", "visible": false}
        ],
        "columnDefs": [
            {
                /*
                 * Dossier : le numéro, le numéro de chèque et le statut forment une bande en tête de
                 * ligne, qui traverse le tableau ; la colonne elle-même porte la thématique.
                 */
                "targets": 0,
                "render": function (data, type, row) {
                    var numero = '<span class="cellule-numero">n° ' + row['demandeId'] + '</span>';
                    var cheque = row['numeroCheque'] ? ' - Chèque n° ' + row['numeroCheque'] : '';

                    var statutLibelle = row['remboursementId'] ? row['remboursementStatutSlug'] : row['demandeStatutSlug'];
                    var motif = row['remboursementId'] ? row['remboursementStatutDescriptionFormatted'] : null;
                    var statut = etiquetteStatut(statutLibelle, motif);

                    var thematique = arrayDemandeType[row['demandeTypeDetaille']] || '';

                    return '<div class="ligne-dossier">'
                        + numero + cheque
                        + (statut ? '<span class="ligne-dossier-separateur">-</span>' + statut : '')
                        + '</div>'
                        + cellule('', [thematique]);
                }
            },
            {
                // Bénéficiaire : identité, puis code postal et ville
                "targets": 1,
                "render": function (data, type, row) {
                    return cellule(row['beneficiaire'], [row['logement']]);
                }
            },
            {
                // Accompagnement : structure, puis conseiller
                "targets": 2,
                "render": function (data, type, row) {
                    var valeur = row['structureConseiller'] || '';
                    var separateur = valeur.indexOf(' - ');

                    if (separateur === -1) {
                        return cellule(valeur, []);
                    }

                    return cellule(valeur.slice(0, separateur), [valeur.slice(separateur + 3)]);
                }
            },
            {
                // Date de remise à l'huissier
                "targets": 4,
                "render": function (data, type, row) {
                    return cellule(dateCourte(row['RMHDate']), []);
                }
            },
{
                "targets": 5,
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
                                    var classLinkExamine = 'icone-gris';
                                    var titleLinkExamine = 'Ré-instruire un remboursement';
                                    var buttonExamine = 'edit';
                                } else {
                                    if (!pathNameExamine) {
                                        console.error('Aucune route examine définie pour ce type de remboursement', row);
                                        // Optionnel : afficher un bouton désactivé ou un message utilisateur
                                        var pathExamine = '#';
                                        var classLinkExamine = 'est-indisponible';
                                        var titleLinkExamine = 'Aucune route examine disponible';
                                        var buttonExamine = 'ban';
                                    } else {
                                        var pathExamine = Routing.generate(pathNameExamine, {titreId: row['titreId']});
                                        var classLinkExamine = 'icone-bleu';
                                        var titleLinkExamine = 'Instruire un remboursement';
                                        var buttonExamine = 'play';
                                    }
                                }

                                html += ''
                                    + '<a href="' + pathExamine + '"'
                                        + 'class="action-ligne ' + classLinkExamine + '"'
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
                                        var classLinkDepot = 'icone-vert';
                                        var titleLinkDepot = 'Modifier les pièces justificatives';
                                    } else {
                                        if (!pathNameAddDepot) {
                                            console.error('Aucune route depot définie pour ce type de remboursement', row);
                                            var pathDepot = '#';
                                            var classLinkDepot = 'est-indisponible';
                                            var titleLinkDepot = 'Aucune route dépôt disponible';
                                        } else {
                                            var pathDepot = Routing.generate(pathNameAddDepot, {demandeId: row['demandeId']});
                                            var classLinkDepot = 'icone-bleu';
                                            var titleLinkDepot = 'Importer les pièces justificatives';
                                        }
                                    }

                                    html += ''
                                        + '<a href="' + pathDepot + '"'
                                            + 'class="action-ligne ' + classLinkDepot + '"'
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
                                        var classLinkFicheTechnique = 'icone-orange';
                                        var titleLinkFicheTechnique = 'Reprendre la fiche technique';
                                    } else if ('1' == row['statutFicheTechnique'] && row['ficheTechniqueId']) {
                                        var pathFicheTechnique = Routing.generate(pathNameReexamineFicheTechnique, {remboursementId: row['remboursementId']});
                                        var classLinkFicheTechnique = 'icone-vert';
                                        var titleLinkFicheTechnique = 'Modifier la fiche technique';
                                    } else {
                                        var pathFicheTechnique = Routing.generate(pathNameExamineFicheTechnique, {titreId: row['titreId']});
                                        var classLinkFicheTechnique = 'icone-bleu';
                                        var titleLinkFicheTechnique = 'Renseigner la fiche technique';
                                    }

                                    html += ''
                                        + '<a href="' + pathFicheTechnique + '"'
                                            + 'class="action-ligne ' + classLinkFicheTechnique + '"'
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
                                if (row['countCommentaire']>0) buttonColor = "icone-rouge";
                                else buttonColor = "icone-bleu";

                                var htmlCommentaire = '';
                                htmlCommentaire +=
                                    '<button id="button_addCommentaire_' + row['demandeId'] + '"\n' +
                                        'class="tooltip-commentaire action-ligne button_addCommentaire ' + buttonColor + '"\n' +
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

                    // Les actions sont empilées, la première au niveau de la ligne du n° de dossier
                    // (mise en forme : assets/styles/backOffice/common/tableau.css)
                    return html ? '<div class="actions-ligne">' + html + '</div>' : '';
                }
            },
        ],
        "fnDrawCallback": function () {
            $('[data-toggle="tooltip"]').tooltip();
            // Motif du statut : bulle au survol de l'étiquette elle-même
            $('.etiquette-statut.a-un-motif').popover();
        },
        "initComplete": function () {
            var api = this.api();

            $('#table_list_remboursement_filter input')
                .attr('placeholder', 'Rechercher dans toutes les colonnes')
                .attr('aria-label', 'Rechercher dans toutes les colonnes');

            /*
             * Filtres à choix multiple. Le fichier de langue étant chargé par son adresse, le cadre
             * du tableau n'existe qu'une fois l'initialisation terminée : c'est donc ici, et par
             * l'interface de DataTables, qu'on va le chercher pour y ranger les filtres.
             */
            var filtres = $('#filtres-listes').prop('hidden', false);
            var menuThematiques = $('#menu_thematiques');
            $.each(arrayDemandeType, function (code, libelle) {
                if (libelle) {
                    menuThematiques.append(
                        $('<label class="filtre-liste-choix"></label>')
                            .append($('<input type="checkbox" />').attr('value', code))
                            .append(' ')
                            .append($('<span></span>').text(libelle))
                    );
                }
            });

            var cadre = $(api.table().container());
            var choixNombreDeLignes = cadre.find('.dataTables_length');

            if (choixNombreDeLignes.length) {
                filtres.appendTo(choixNombreDeLignes.parent());
            } else if (cadre.length) {
                cadre.prepend(filtres);
            }

            filtreListe(api, $('#bouton_thematiques'), menuThematiques, $('#filtre_demandeType'), 'thématique', 'Toutes les thématiques');
            filtreListe(api, $('#bouton_statuts'), $('#menu_statuts'), $('#filtre_statut'), 'statut', 'Tous les statuts');

            brancherRecherches(api);
        }
    });

    // Vider toutes les recherches, colonne par colonne et recherche générale comprise
    $('#clear_search_input').on('click', function () {
        $('#filterrow [data-colonne]').val('');
        $('#filtres-listes [data-colonne]').each(function () {
            var reinitialiser = $(this).data('reinitialiser');
            if (reinitialiser) {
                reinitialiser();
            }
        });

        table
            .search('')
            .columns()
            .search('')
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
