/*
 * Menu principal de l'espace partenaire.
 *
 * Deux comportements selon la largeur d'écran :
 *   - grand écran : barre latérale permanente, réductible en icônes, choix mémorisé ;
 *   - petit écran : tiroir ouvert par le bouton de l'en-tête, fermé par Échap, par le fond
 *     sombre, ou par un clic sur une entrée.
 *
 * Accessibilité : le bouton porte aria-expanded, le focus part vers le menu à l'ouverture et
 * revient au bouton à la fermeture (RGAA 7.3 et 10.13).
 */
document.addEventListener('DOMContentLoaded', function () {
    var menu = document.getElementById('menu-principal');
    var bouton = document.getElementById('menu-bascule');
    var fond = document.getElementById('menu-fond');
    var boutonReduire = document.getElementById('menu-reduire');
    var corps = document.body;
    var CLE_REDUIT = 'ceen.menu.reduit';

    if (!menu) {
        return;
    }

    /* --- Réduction en icônes (grand écran) --- */
    function appliquerReduction(reduit) {
        corps.classList.toggle('menu-reduit', reduit);

        if (boutonReduire) {
            boutonReduire.setAttribute('aria-expanded', reduit ? 'false' : 'true');
            var libelle = boutonReduire.querySelector('.menu-libelle');
            if (libelle) {
                libelle.textContent = reduit ? 'Déplier le menu' : 'Réduire le menu';
            }
            boutonReduire.setAttribute('title', reduit ? 'Déplier le menu' : 'Réduire le menu');
        }
    }

    try {
        appliquerReduction(window.localStorage.getItem(CLE_REDUIT) === '1');
    } catch (e) {
        // Stockage indisponible (navigation privée, restriction) : menu déplié par défaut.
        appliquerReduction(false);
    }

    if (boutonReduire) {
        boutonReduire.addEventListener('click', function () {
            var reduit = !corps.classList.contains('menu-reduit');
            appliquerReduction(reduit);
            try {
                window.localStorage.setItem(CLE_REDUIT, reduit ? '1' : '0');
            } catch (e) {
                // Le choix ne sera pas mémorisé, sans conséquence sur la navigation.
            }
        });
    }

    /* --- Tiroir (petit écran) --- */
    function ouvrir() {
        corps.classList.add('menu-ouvert');
        if (bouton) {
            bouton.setAttribute('aria-expanded', 'true');
        }
        if (fond) {
            fond.hidden = false;
        }
        menu.setAttribute('tabindex', '-1');
        menu.focus();
    }

    function fermer(rendreLeFocus) {
        if (!corps.classList.contains('menu-ouvert')) {
            return;
        }

        corps.classList.remove('menu-ouvert');
        if (bouton) {
            bouton.setAttribute('aria-expanded', 'false');
            if (rendreLeFocus) {
                bouton.focus();
            }
        }
        if (fond) {
            fond.hidden = true;
        }
    }

    if (bouton) {
        bouton.addEventListener('click', function () {
            if (corps.classList.contains('menu-ouvert')) {
                fermer(true);
            } else {
                ouvrir();
            }
        });
    }

    if (fond) {
        fond.addEventListener('click', function () {
            fermer(true);
        });
    }

    // Échap ferme le tiroir ; il ne l'ouvre jamais, contrairement à l'ancien comportement.
    document.addEventListener('keydown', function (evenement) {
        if (evenement.key === 'Escape' || evenement.keyCode === 27) {
            fermer(true);
        }
    });

    // Sur petit écran, suivre une entrée referme le tiroir avant le chargement de la page.
    menu.addEventListener('click', function (evenement) {
        if (evenement.target.closest('.menu-lien')) {
            fermer(false);
        }
    });

    // Retour au grand écran : le tiroir ne doit pas rester dans un état ouvert.
    window.addEventListener('resize', function () {
        if (window.innerWidth > 991) {
            fermer(false);
        }
    });

    /* --- Menu du compte --- */
    var compteBouton = document.getElementById('compte-bascule');
    var compteMenu = document.getElementById('compte-menu');

    if (!compteBouton || !compteMenu) {
        return;
    }

    function compteEstOuvert() {
        return compteBouton.getAttribute('aria-expanded') === 'true';
    }

    function compteOuvrir() {
        compteMenu.hidden = false;
        compteBouton.setAttribute('aria-expanded', 'true');
    }

    function compteFermer(rendreLeFocus) {
        if (!compteEstOuvert()) {
            return;
        }

        compteMenu.hidden = true;
        compteBouton.setAttribute('aria-expanded', 'false');
        if (rendreLeFocus) {
            compteBouton.focus();
        }
    }

    compteBouton.addEventListener('click', function () {
        if (compteEstOuvert()) {
            compteFermer(false);
        } else {
            compteOuvrir();
            var premier = compteMenu.querySelector('.compte-menu-lien');
            if (premier) {
                premier.focus();
            }
        }
    });

    // Clic en dehors : le menu se referme, sans reprendre le focus.
    document.addEventListener('click', function (evenement) {
        if (!evenement.target.closest('.entete-compte')) {
            compteFermer(false);
        }
    });

    document.addEventListener('keydown', function (evenement) {
        if (evenement.key === 'Escape' || evenement.keyCode === 27) {
            compteFermer(true);
        }
    });

    // Le menu se referme dès que le focus en sort (tabulation vers la page).
    compteMenu.addEventListener('focusout', function (evenement) {
        if (!compteMenu.contains(evenement.relatedTarget) && evenement.relatedTarget !== compteBouton) {
            compteFermer(false);
        }
    });
});
