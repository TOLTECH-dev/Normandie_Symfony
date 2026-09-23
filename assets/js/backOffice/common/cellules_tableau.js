/*
 * Cellules des listes à colonnes regroupées (consultation des dossiers, remboursement des aides).
 *
 * Une cellule porte une information principale et, en dessous, ses compléments ; le statut est mis
 * en étiquette, et son motif s'affiche au survol de l'étiquette elle-même.
 * Mise en forme : assets/styles/backOffice/common/tableau.css
 */

/** Remplace toutes les occurrences, sans expression régulière à échapper */
export function remplacerTout(chaine, recherche, remplacement) {
    return String(chaine).split(recherche).join(remplacement);
}

/**
 * Étiquette de statut : la couleur est déduite du libellé, faute d'information de famille dans les
 * données renvoyées. Tout libellé non reconnu reste neutre (gris), jamais trompeur.
 * Le motif, quand il existe, s'affiche au survol et à la tabulation.
 */
export function etiquetteStatut(libelle, motif) {
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
        + ' data-content="' + remplacerTout(motif, '"', '&quot;') + '">'
        + texte + '</span>';
}

/** Cellule à deux niveaux : une information principale, puis ses compléments en dessous */
export function cellule(principale, complements) {
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

/** Date au format jour/mois/année ; chaîne vide si la donnée est absente ou illisible */
export function dateCourte(valeur) {
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
 * sélection (valeurs séparées par « | »), repris comme critère par la colonne masquée associée.
 */
export function filtreListe(api, bouton, menu, champCache, nomSingulier, libelleParDefaut) {
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

/**
 * Branche les champs de recherche du pied de tableau (ligne #filterrow) sur leurs colonnes : chacun
 * porte l'attribut data-colonne, qui désigne la colonne par son nom logique. Un champ peut ainsi
 * interroger une colonne masquée.
 */
export function brancherRecherches(api) {
    var attente = {};

    $('#filterrow [data-colonne]').each(function () {
        var champ = $(this);
        var nom = champ.data('colonne');
        var colonne = api.column(nom + ':name');

        if (!colonne || colonne.length === 0) {
            return;
        }

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
