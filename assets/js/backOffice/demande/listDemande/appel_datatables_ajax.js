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

    /**
     * Étiquette de statut : la couleur est déduite du libellé, faute d'information de famille dans
     * les données renvoyées. Tout libellé non reconnu reste neutre (gris), jamais trompeur.
     * Le motif, quand il existe, s'affiche au survol de l'étiquette elle-même (et à la tabulation,
     * pour qui n'utilise pas la souris) : plus besoin d'un point d'interrogation à côté.
     * Mise en forme : assets/styles/backOffice/common/tableau.css
     */
    function etiquetteStatut(libelle, motif) {
        if (!libelle) {
            return '';
        }

        var texte = String(libelle);
        var sansAccent = texte.normalize ? texte.normalize('NFD').replace(/[̀-ͯ]/g, '') : texte;
        var compare = sansAccent.toLowerCase();
        var variante = '';

        if (compare.indexOf('refus') !== -1 || compare.indexOf('annul') !== -1 || compare.indexOf('abandon') !== -1) {
            variante = ' est-refuse';
        } else if (compare.indexOf('attente') !== -1 || compare.indexOf('cours') !== -1) {
            variante = ' est-en-attente';
        } else if (compare.indexOf('valid') !== -1 || compare.indexOf('accept') !== -1
            || compare.indexOf('rembours') !== -1 || compare.indexOf('paye') !== -1) {
            variante = ' est-valide';
        }

        if (!motif) {
            return '<span class="etiquette-statut' + variante + '">' + texte + '</span>';
        }

        return '<span class="etiquette-statut a-un-motif' + variante + '"'
            + ' role="button" tabindex="0" data-toggle="popover" data-trigger="hover focus"'
            + ' data-html="true" data-container="body" data-placement="top"'
            + ' data-content="' + replaceAll(String(motif), '"', '&quot;') + '">'
            + texte + '</span>';
    }

    /**
     * Cellule à deux niveaux : une information principale, puis ses compléments en dessous.
     * Mise en forme : assets/styles/backOffice/common/tableau.css
     */
    function cellule(principale, complements) {
        var html = '<div class="cellule">';

        if (principale) {
            html += '<div class="cellule-principale">' + principale + '</div>';
        }

        (complements || []).forEach(function (complement) {
            if (complement) {
                html += '<div class="cellule-secondaire">' + complement + '</div>';
            }
        });

        return html + '</div>';
    }

    /** Date au format jour/mois/année ; chaîne vide si la donnée est absente */
    function dateCourte(valeur) {
        if (!valeur) {
            return '';
        }

        var date = new Date(valeur);
        if (isNaN(date.getTime())) {
            return '';
        }

        return ('0' + date.getDate()).slice(-2)
            + '/' + ('0' + (date.getMonth() + 1)).slice(-2)
            + '/' + date.getFullYear();
    }

    /**
     * Filtre à choix multiple : un bouton, un menu de cases à cocher, et un champ caché qui porte la
     * sélection (valeurs séparées par « | »). Ce champ sert à la fois à la recherche de la colonne
     * masquée correspondante et, le formulaire l'englobant, aux critères de l'export.
     * Mise en forme : assets/styles/backOffice/common/tableau.css
     */
    function filtreListe(api, bouton, menu, champCache, nomSingulier, libelleParDefaut) {
        var nom = champCache.data('colonne');
        var colonne = api.column(nom + ':name');
        var cases = menu.find('input[type="checkbox"]');
        var texte = bouton.find('.filtre-liste-texte');

        function choisies() {
            return cases.filter(':checked').map(function () { return this.value; }).get();
        }

        function rafraichir(valeurs) {
            if (0 === valeurs.length) {
                texte.text(libelleParDefaut);
            } else if (1 === valeurs.length) {
                texte.text(cases.filter(':checked').closest('label').find('span').text());
            } else {
                texte.text(valeurs.length + ' ' + nomSingulier + 's');
            }
            bouton.toggleClass('a-une-selection', valeurs.length > 0);
        }

        function appliquer() {
            var valeurs = choisies();
            var valeur = valeurs.join('|');

            champCache.val(valeur);
            rafraichir(valeurs);

            if (colonne && colonne.length && colonne.search() !== valeur) {
                colonne.search(valeur).draw();
            }
        }

        function ouvrir(afficher) {
            menu.prop('hidden', !afficher);
            bouton.attr('aria-expanded', afficher ? 'true' : 'false').toggleClass('est-ouvert', afficher);
        }

        // Reprise de la sélection mémorisée d'une visite précédente
        var memorisee = (colonne && colonne.length) ? colonne.search() : '';
        if (memorisee) {
            var reprises = memorisee.split('|');
            cases.each(function () {
                this.checked = -1 !== reprises.indexOf(this.value);
            });
        }
        champCache.val(memorisee);
        rafraichir(choisies());

        menu.prepend($('<button type="button" class="filtre-liste-vider">Tout décocher</button>').on('click', function () {
            cases.prop('checked', false);
            appliquer();
        }));

        cases.on('change', appliquer);
        bouton.on('click', function () {
            ouvrir(menu.prop('hidden'));
        });

        // Fermeture : clic à l'extérieur, ou touche Échap qui rend la main au bouton
        $(document).on('click', function (evenement) {
            if (0 === $(evenement.target).closest(bouton.parent()).length) {
                ouvrir(false);
            }
        });
        bouton.parent().on('keydown', function (evenement) {
            if (27 === evenement.keyCode) {
                ouvrir(false);
                bouton.trigger('focus');
            }
        });

        // Remise à zéro déclenchée par le bouton « vider les recherches »
        champCache.data('reinitialiser', function () {
            cases.prop('checked', false);
            champCache.val('');
            rafraichir([]);
        });
    }

    /** Adresse de consultation d'un dossier, partagée par le numéro et le bouton « Consulter » */
    function urlConsultation(row) {
        return '7F256' + row['demandeId']
            + '36CDEC912AB0BE1F57442E7D2B6B975594DE56F1548CC3C121FBB8E176DFDBDA2A6E50306E505BAB7F9FA5FBEEA129B0915CBD6869B3FE4E0C4375FDF6C';
    }

    /** Profils autorisés à ouvrir un dossier depuis la liste */
    function peutConsulter() {
        return 1 === arrayRole['ROLE_CONSEILLER']
            || 1 === arrayRole['ROLE_INSTRUCTEUR']
            || 1 === arrayRole['ROLE_AUDITEUR']
            || 1 === arrayRole['ROLE_RENOVATEUR']
            || 1 === arrayRole['ROLE_EPCI']
            || 1 === arrayRole['ROLE_CLIENT']
            || 1 === arrayRole['ROLE_ADMIN']
            || 1 === arrayRole['ROLE_TECHNIQUE'];
    }

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
        // La recherche générale part au serveur : on attend la fin de la frappe
        "searchDelay": 400,
        "processing": true,
        "serverSide": true,
        "ajax": {
            url: 'DD2D8051DB8125BC7F19769BA283322B04C696F31CF4930971E731E0BBE9EDD71830B50847AB8C31B6A81E936F882FBE9EF28DC48D2B531959C9A606F7A1DA75',
            dataType: 'JSON',
            type: 'POST'
        },
        /*
         * Colonnes regroupées : chaque cellule porte une information principale et, en dessous, ses
         * compléments. Le nom logique (« name ») est celui que le serveur reconnaît : une colonne
         * regroupée y cherche dans tous ses champs (cf. DemandeListSql::LIKE_FILTERS).
         * La thématique et le statut sont réunis dans la première colonne ; ils restent déclarés
         * comme colonnes masquées, pour garder leur champ de recherche propre.
         */
        "columns": [
            {"data": "demandeId", "name": "dossier", "class": "colonne-dossier"},
            {"data": "beneficiaireIdentifiant", "name": "beneficiaire", "class": "colonne-beneficiaire"},
            {"data": "structureConseiller", "name": "structureConseiller", "class": "colonne-accompagnement"},
            {"data": "partenaire", "name": "partenaire", "class": "colonne-professionnel"},
            {"data": "commissionDate", "name": "echeances", "class": "colonne-echeances"},
            {"data": "action", "name": "action", "class": "colonne-action", "searchable": false, "sortable": false},
            {"data": "demandeType", "name": "demandeType", "visible": false},
            {"data": "demandeStatutSlug", "name": "demandeStatutSlug", "visible": false}
        ],
        "columnDefs": [
            {
                /*
                 * Dossier : le numéro, la date et le statut forment une bande en tête de ligne, qui
                 * traverse toute la largeur du tableau (mise en forme : tableau.css, .ligne-dossier).
                 * Les libellés de statut sont longs ; dans la seule largeur de la colonne, ils
                 * tenaient sur trois lignes. La colonne elle-même ne porte plus que la thématique.
                 */
                "targets": 0,
                "render": function (data, type, row) {
                    var numero = row['demandeId'];
                    if (peutConsulter()) {
                        numero = '<a href="' + urlConsultation(row) + '"'
                            + ' class="tooltip-consultation" data-toggle="tooltip" data-placement="bottom"'
                            + ' data-container="body" title="Consulter la demande">'
                            + row['demandeId'] + '</a>';
                    }

                    var thematique = arrayDemandeType[row['demandeType']] || '';
                    if (row['demandeTravauxDevisIsBonificationAide'] == 1) {
                        thematique += ' + Bonification';
                    }

                    var date = dateCourte(row['demandeDateCreation']);
                    var statutSlug = (row['remboursementId']) ? row['remboursementStatutSlug'] : row['demandeStatutSlug'];
                    var motif = (row['remboursementId'])
                        ? row['remboursementStatutDescriptionFormatted']
                        : row['demandeStatutDescriptionFormatted'];

                    var statut = etiquetteStatut(statutSlug, motif);
                    var bande = '<div class="ligne-dossier">'
                        + '<span class="cellule-numero">n° ' + numero + (date ? ' - ' + date : '') + '</span>'
                        + (statut ? '<span class="ligne-dossier-separateur">-</span>' + statut : '')
                        + '</div>';

                    return bande + '<div class="cellule">'
                        + (thematique ? '<div class="cellule-secondaire">' + thematique + '</div>' : '')
                        + '</div>';
                }
            },
            {
                // Bénéficiaire : identité, puis code postal et ville
                "targets": 1,
                "render": function (data, type, row) {
                    return cellule(row['beneficiaireIdentifiant'], [row['logement']]);
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
                // Professionnel : raison sociale, puis mention de l'audit
                "targets": 3,
                "render": function (data, type, row) {
                    var audit = '';

                    // Audits énergétiques : la mention n'a pas de sens, la demande EST l'audit
                    if (1 !== row['demandeType'] && 4 !== row['demandeType']) {
                        if (row['auditEId']) {
                            audit = 'Audit n° ' + row['auditEId'];
                        } else if (row['audit'] && 1 !== row['audit']) {
                            audit = 'Sans audit';
                        } else {
                            audit = 'Ancien audit';
                        }
                    }

                    return cellule(row['partenaire'], [audit]);
                }
            },
            {
                // Échéances : date de commission, puis date de remboursement
                "targets": 4,
                "render": function (data, type, row) {
                    var commission = dateCourte(row['commissionDate']);
                    var peutAffecter = (1 === arrayRole['ROLE_CLIENT'] || 1 === arrayRole['ROLE_ADMIN'])
                        && true === arrayCommissionDateStatut.includes(String(row['demandeStatutId']));

                    if (peutAffecter) {
                        var lien = '99051237B5F7DC1D1625BA56FB65B91BC54B1DA31482A19EE70F1B3DFA8E9030C3D002E5DAE225EC716ACF9778241'
                            + (row['commissionDate'] ? '5' : '4')
                            + 'F1F469DE' + row['logementId'] + '807DB67AC' + row['beneficiaireId']
                            + 'D3639C106' + row['demandeId'] + '25E7FED61';
                        commission = '<a href="' + lien + '">' + (commission || 'Affecter une date') + '</a>';
                    }

                    var remboursement = dateCourte(row['remboursementDate']);

                    return cellule(
                        commission ? 'Commission : ' + commission : '',
                        [remboursement ? 'Remboursement : ' + remboursement : '']
                    );
                }
            },
            {
                /*
                 * Trois actions, l'une sous l'autre : consulter le dossier dans le panneau, ouvrir
                 * son historique dans une vue à part, et commenter dans la fenêtre d'origine.
                 * Les couleurs des icônes reprennent celles des boutons d'avant la refonte : bleu
                 * pour l'historique et le commentaire, rouge dès qu'un commentaire existe.
                 * Le lien de consultation pointe vers la vue complète : un clic milieu, ou un
                 * panneau qui ne s'ouvrirait pas, y mènent encore.
                 */
                "targets": 5,
                "render": function (data, type, row) {
                    if (!peutConsulter()) {
                        return '';
                    }

                    var consultation = '<a href="' + urlConsultation(row) + '"'
                        + ' class="tooltip-consultation action-ligne action-consulter icone-orange"'
                        + ' role="button" data-toggle="tooltip" data-placement="bottom"'
                        + ' data-container="body" title="Consulter le dossier"'
                        + ' data-demande="' + row['demandeId'] + '">'
                        + '<i class="glyphicon glyphicon-eye-open"></i></a>';

                    var historique = '<a href="'
                        + Routing.generate('demande_historique', {demandeId: row['demandeId'], redirectRoute: 'demande_list_all'})
                        + '" class="tooltip-historique action-ligne icone-bleu" role="button" data-toggle="tooltip"'
                        + ' data-placement="bottom" data-container="body" title="Afficher son historique">'
                        + '<i class="glyphicon glyphicon-time"></i></a>';

                    // Rouge quand le dossier porte déjà des commentaires, comme avant la refonte
                    var couleurCommentaire = row['countCommentaire'] > 0 ? ' icone-rouge' : ' icone-bleu';
                    var commentaire = '<button id="button_addCommentaire_' + row['demandeId'] + '"'
                        + ' class="tooltip-commentaire action-ligne button_addCommentaire' + couleurCommentaire + '"'
                        + ' type="button" title="Commenter la demande" data-toggle="tooltip"'
                        + ' data-placement="bottom" data-container="body"'
                        + ' data-demandeId="' + row['demandeId'] + '"'
                        + ' data-demandeUrl="' + Routing.generate('demande_create_commentaire', {demandeId: row['demandeId']}) + '"'
                        + ' data-original-title="Commenter la demande">'
                        + '<i class="glyphicon glyphicon-comment"></i></button>';

                    saveEvent_commentaire(null);
                    createModalEvent_commentaire('button_addCommentaire_' + row['demandeId']);

                    return '<div class="actions-ligne">' + consultation + historique + commentaire + '</div>';
                }
            }
        ],
        "fnDrawCallback": function() {
            $('.tooltip-consultation').tooltip();
            $('.tooltip-historique').tooltip();
            $('.tooltip-commentaire').tooltip();
            // Motif du statut : bulle au survol de l'étiquette elle-même
            $('.etiquette-statut.a-un-motif').popover();
        },
        /*
         * Les champs de recherche sont écrits dans le gabarit (ligne #filterrow) : chacun porte
         * l'attribut data-colonne, qui désigne la colonne par son nom logique. Un champ peut donc
         * interroger une colonne masquée, comme la thématique.
         */
        "initComplete": function () {
            var api = this.api();
            var attente = {};

            /*
             * Recherche générale : elle était masquée, faute d'être traitée côté serveur. Elle
             * balaie maintenant toutes les informations affichées (cf. DemandeListSql::
             * searchEverywhere), ce qui évite d'avoir à deviner dans quelle colonne chercher.
             */
            $('#table_list_demande_filter input')
                .attr('placeholder', 'Rechercher dans toutes les colonnes')
                .attr('aria-label', 'Rechercher dans toutes les colonnes');

            /*
             * Filtres à choix multiple. Le fichier de langue étant chargé par son adresse, le cadre
             * du tableau n'existe qu'une fois l'initialisation terminée : c'est donc ici, et par
             * l'interface de DataTables plutôt que par un identifiant deviné, qu'on va le chercher.
             * Les filtres se rangent à la suite du choix du nombre de lignes ; à défaut, en haut du
             * cadre. Ils restent masqués jusque-là, pour ne pas être vus changer de place.
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
            filtreListe(api, $('#bouton_statuts'), $('#menu_statuts'), $('#filtre_demandeStatutSlug'), 'statut', 'Tous les statuts');

            $('#filterrow [data-colonne]').each(function () {
                var champ = $(this);
                var nom = champ.data('colonne');
                var colonne = api.column(nom + ':name');

                if (!colonne || colonne.length === 0) {
                    return;
                }

                // Reprise de la recherche mémorisée d'une visite précédente
                var memorisee = colonne.search();
                if (memorisee) {
                    champ.val(memorisee);
                }

                champ.on('keyup change', function () {
                    var valeur = champ.val();

                    if (colonne.search() === valeur) {
                        return;
                    }

                    clearTimeout(attente[nom]);
                    // Une liste déroulante s'applique aussitôt ; une saisie attend la fin de la frappe
                    attente[nom] = setTimeout(function () {
                        colonne.search(valeur).draw();
                    }, champ.is('select') ? 0 : 400);
                });
            });
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
     * Fonction pour sauvegarder le commentaire (fenêtre ouverte depuis une ligne de la liste)
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
            }).done(function () {
                location.reload();
            }).fail(function (jqXHR) {
                if ('undefined' !== typeof jqXHR.responseJSON && jqXHR.responseJSON.hasOwnProperty('form')) {
                    $('#modal_formCommentaire .modal-body').html(jqXHR.responseJSON.form);
                    saveEvent_commentaire(demandeUrl);
                }
            });
        });
    }

    /**
     * Fonction pour ouvrir la fenêtre d'ajout de commentaire
     */
    function createModalEvent_commentaire(button_addCommentaire)
    {
        $(document).on("click", '#' + button_addCommentaire, function(e)
        {
            var demandeId = $(this).attr('data-demandeId');
            var demandeUrl = $(this).attr('data-demandeUrl');

            setModalContent_commentaire(demandeUrl, 'Demande ' + demandeId);

            e.preventDefault();
        });
    }

    /**
     * Fonction pour charger le contenu de la fenêtre
     *
     * @param demandeUrl
     * @param title
     */
    function setModalContent_commentaire(demandeUrl, title)
    {
        $('.modal_formCommentaire .modal-body').load(demandeUrl, function()
        {
            $('.modal_formCommentaire #modal_formCommentaire-titleText').text(title);
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
