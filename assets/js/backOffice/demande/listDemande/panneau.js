/*
 * Consultation d'un dossier sans quitter la liste.
 *
 * Un clic sur l'action d'une ligne réduit le tableau à sa première colonne et charge le dossier
 * dans le panneau de droite : les onglets de la vue complète (bénéficiaire, logement, demande,
 * remboursement), l'historique en frise et les commentaires. Ces trois écrans existent toujours
 * par ailleurs ; le panneau évite seulement d'avoir à les ouvrir chacun de son côté.
 *
 * Tous les branchements sont délégués au document : le contenu du tableau comme celui du panneau
 * sont reconstruits à chaque chargement.
 */
$(document).ready(function () {
    var zone = $('#zone_liste_dossiers');
    var panneau = $('#panneau_dossier');

    if (!zone.length || !panneau.length) {
        return;
    }

    /*
     * Hauteur commune à la colonne et au dossier : ce qui reste sous le titre jusqu'au bas de la
     * fenêtre. On la mesure plutôt que de la déduire d'une constante, la hauteur du fil d'Ariane et
     * du titre variant d'un écran à l'autre. La mise en forme s'en sert par --hauteur-zone.
     */
    var MARGE_BASSE = 16;
    var HAUTEUR_MINIMALE = 320;

    function ajusterHauteur() {
        if (!zone.hasClass('avec-panneau')) {
            zone[0].style.removeProperty('--hauteur-zone');
            return;
        }

        // Si la page a été défilée, la zone est déjà remontée : on part alors de sa position collée
        var haut = Math.max(zone[0].getBoundingClientRect().top, MARGE_BASSE);
        var hauteur = Math.max(HAUTEUR_MINIMALE, window.innerHeight - haut - MARGE_BASSE);

        zone[0].style.setProperty('--hauteur-zone', hauteur + 'px');
    }

    var attenteRedimensionnement = null;
    $(window).on('resize', function () {
        clearTimeout(attenteRedimensionnement);
        attenteRedimensionnement = setTimeout(ajusterHauteur, 150);
    });

    /*
     * Bulles de motif du statut : elles s'ouvrent au survol et au clavier, ce qui viendrait
     * au-devant du clic sur la ligne. Tant qu'un dossier est ouvert, elles sont retirées ; le motif
     * reste lisible dans le dossier lui-même. Elles reviennent à la fermeture. Le retrait est
     * refait après chaque dessin du tableau, qui les réinstalle.
     */
    function majEtiquettes() {
        var etiquettes = $('#table_list_demande .etiquette-statut.a-un-motif');

        if (zone.hasClass('avec-panneau')) {
            etiquettes.popover('destroy').removeAttr('tabindex').removeAttr('role');
        } else {
            etiquettes.attr({tabindex: '0', role: 'button'}).popover();
        }
    }

    $('#table_list_demande').on('draw.dt', majEtiquettes);

    /** Ouvre le panneau sur un dossier et met sa ligne en évidence */
    function ouvrir(demandeId, ligne) {
        if (!demandeId) {
            return;
        }

        $('#table_list_demande tbody tr').removeClass('est-consulte');
        ligne.addClass('est-consulte');

        zone.addClass('avec-panneau');
        majEtiquettes();
        panneau.prop('hidden', false).html('<p class="panneau-attente">Chargement du dossier&hellip;</p>');
        ajusterHauteur();

        $.ajax({
            url: Routing.generate('demande_panneau', {demandeId: demandeId}),
            dataType: 'html'
        }).done(function (html) {
            panneau.html(html);
            panneau.find('[data-toggle="tooltip"]').tooltip();
            panneau.find('[data-toggle="popover"]').popover({html: true});
        }).fail(function (jqXHR) {
            panneau.html(messageErreur(jqXHR, 'Ce dossier n&rsquo;a pas pu &ecirc;tre affich&eacute;.'));
        });
    }

    function fermer() {
        zone.removeClass('avec-panneau');
        panneau.prop('hidden', true).empty();
        $('#table_list_demande tbody tr').removeClass('est-consulte');
        majEtiquettes();
        ajusterHauteur();
    }

    /** Un refus d'accès se dit en clair : le dossier est hors du périmètre du compte */
    function messageErreur(jqXHR, defaut) {
        var texte = (404 === jqXHR.status)
            ? 'Ce dossier n&rsquo;existe pas ou ne fait pas partie de votre p&eacute;rim&egrave;tre.'
            : defaut;

        return '<p class="panneau-erreur">' + texte + '</p>';
    }

    /** Contenu d'un onglet chargé seulement quand on l'ouvre (historique, commentaires) */
    function chargerOnglet(panneauOnglet) {
        var cible = $(panneauOnglet);
        var url = cible.data('url');

        if (!url || cible.data('charge')) {
            return;
        }
        cible.data('charge', true);

        $.ajax({url: url, dataType: 'html'}).done(function (html) {
            cible.html(html);
            cible.find('[data-toggle="tooltip"]').tooltip();
        }).fail(function (jqXHR) {
            cible.data('charge', false);
            cible.html(messageErreur(jqXHR, 'Ce contenu n&rsquo;a pas pu &ecirc;tre affich&eacute;.'));
        });
    }

    // Ouverture depuis la liste. Le lien mène à la vue complète : on ne l'intercepte que pour un
    // clic simple, afin de laisser fonctionner l'ouverture dans un nouvel onglet.
    $(document).on('click', '.action-consulter', function (evenement) {
        if (evenement.which > 1 || evenement.ctrlKey || evenement.metaKey || evenement.shiftKey) {
            return;
        }

        evenement.preventDefault();
        $(this).tooltip('hide');
        ouvrir($(this).data('demande'), $(this).closest('tr'));
    });

    /*
     * Clic n'importe où sur la ligne : même effet que son action, mais seulement quand un dossier
     * est déjà ouvert — la ligne sert alors à en changer. Sur le tableau complet, rien ne se passe :
     * on y clique pour lire, l'ouverture restant commandée par le bouton de la colonne Action.
     * On laisse passer ce qui a déjà un comportement propre — lien du numéro, date de commission à
     * affecter, étiquette de statut dont le motif s'affiche au survol — pour ne rien détourner.
     */
    $(document).on('click', '#table_list_demande tbody tr', function (evenement) {
        if (!zone.hasClass('avec-panneau')) {
            return;
        }
        if (evenement.which > 1 || evenement.ctrlKey || evenement.metaKey || evenement.shiftKey) {
            return;
        }
        if ($(evenement.target).closest('a, button, input, select, textarea, .etiquette-statut').length) {
            return;
        }

        var action = $(this).find('.action-consulter');
        if (action.length) {
            ouvrir(action.data('demande'), $(this));
        }
    });

    $(document).on('click', '.panneau-fermer', function () {
        fermer();
    });

    /*
     * Deux fenêtres d'action ont besoin du script de la vue complète : le choix des contacts
     * (structures puis conseillers chargés en Ajax) et le changement de niveau d'aide (affichage du
     * rénovateur). Ces scripts se mettaient en place au chargement de la page, quand leur
     * formulaire n'existe pas encore ici : on les relance à l'ouverture de la fenêtre, une fois le
     * formulaire présent. C'est aussi ce qui évite d'interroger le serveur pour un dossier que
     * personne n'ouvrira.
     */
    $(document).on('shown.bs.modal', '#panneau_popupAssignContacts', function () {
        if ('function' === typeof window.initialiserAssignationContacts) {
            window.initialiserAssignationContacts();
        }
    });

    $(document).on('shown.bs.modal', '#panneau_popupNiveauAide', function () {
        if ('function' === typeof window.initialiserNiveauAide) {
            window.initialiserNiveauAide();
        }
    });

    // Échap ferme le panneau, sauf si une fenêtre est ouverte par-dessus
    $(document).on('keydown', function (evenement) {
        if (27 === evenement.keyCode && zone.hasClass('avec-panneau') && !$('.modal.in').length) {
            fermer();
        }
    });

    $(document).on('shown.bs.tab', '#panneau_dossier [data-toggle="tab"]', function () {
        var cible = $(this).attr('href');

        if (cible && $(cible).hasClass('panneau-differe')) {
            chargerOnglet(cible);
        }
    });

    // Détail d'une étape de l'historique : courriels envoyés, contenu d'un commentaire
    $(document).on('click', '.frise-detail-bouton', function () {
        var bouton = $(this);
        var detail = $('#' + bouton.attr('aria-controls'));
        var ouvert = !detail.prop('hidden');

        detail.prop('hidden', ouvert);
        bouton.attr('aria-expanded', ouvert ? 'false' : 'true');
    });

    /*
     * Nouveau commentaire : la fenêtre déjà utilisée par les autres écrans est reprise telle
     * quelle. Elle charge le formulaire et la liste des commentaires du dossier, et l'enregistrement
     * suit le même échange qu'ailleurs (POST du formulaire, 200 si c'est enregistré, 400 avec le
     * formulaire et ses erreurs sinon). À la différence des autres écrans, la page n'est pas
     * rechargée : la fenêtre se ferme et l'historique, où figurent les commentaires, est redemandé.
     */
    /** Adresse du formulaire de commentaire du panneau, sa version « panneau » du gabarit comprise */
    function urlCommentaire() {
        var base = $('.panneau-commenter').data('url');

        return base ? base + (base.indexOf('?') === -1 ? '?' : '&') + 'panneau=1' : null;
    }

    $(document).on('click', '.panneau-commenter', function () {
        var fenetre = $('#panneau_commentaire');

        fenetre.find('.fenetre-commentaire-corps')
            .html('<p class="panneau-attente">Chargement du formulaire&hellip;</p>')
            .load(urlCommentaire(), function () {
                fenetre.find('.commentaire-contenu').trigger('focus');
            });

        fenetre.modal({show: true});
    });

    $(document).on('submit', '#panneau_commentaire form', function (evenement) {
        evenement.preventDefault();

        var formulaire = $(this);
        var fenetreCommentaire = $('#panneau_commentaire');
        var corps = fenetreCommentaire.find('.fenetre-commentaire-corps');
        var bouton = formulaire.find('#whitelabel_backofficebundle_historique_email_enregistrer');
        var url = urlCommentaire();

        if (bouton.hasClass('disabled')) {
            return;
        }
        bouton.addClass('disabled');

        $.ajax({
            type: formulaire.attr('method') || 'POST',
            url: url,
            data: formulaire.serialize()
        }).done(function () {
            fenetreCommentaire.modal('hide');
            corps.empty();

            // L'historique porte les commentaires : il est redemandé s'il a déjà été consulté
            var historique = $('#tab_panneau_historique');
            if (historique.data('charge')) {
                historique.data('charge', false);
                chargerOnglet('#tab_panneau_historique');
            }
        }).fail(function (jqXHR) {
            bouton.removeClass('disabled');

            if ('undefined' !== typeof jqXHR.responseJSON && jqXHR.responseJSON.hasOwnProperty('form')) {
                corps.html(jqXHR.responseJSON.form);
            } else {
                corps.html(messageErreur(jqXHR, 'Ce commentaire n&rsquo;a pas pu &ecirc;tre enregistr&eacute;.'));
            }
        });
    });
});
