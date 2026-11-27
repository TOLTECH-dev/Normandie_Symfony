/**
 * D26013 — chronologie generale, chargee a la demande.
 *
 * La table d historique compte 411 000 lignes : rien n est lu tant que l
 * onglet n est pas ouvert, et la suite se charge par tranches de cinquante.
 * C est la condition posee pour que la liste des utilisateurs et celle des
 * dossiers ne paient pas le prix d une chronologie qu on ne consultera
 * peut-etre pas.
 *
 * Les ecouteurs sont poses par jQuery et non en attributs : la politique de
 * securite du contenu refuse les gestionnaires inscrits dans le HTML.
 *
 * La pagination se fait par curseur : le serveur rend l identifiant a partir
 * duquel poursuivre, le bouton le porte jusqu a l appel suivant.
 */
$(document).ready(function () {
    var panneau = $('.timeline-globale');

    if (0 === panneau.length) {
        return;
    }

    var adresse = panneau.data('url');
    var liste = panneau.find('.timeline-liste');
    var attente = panneau.find('.timeline-attente');
    var erreur = panneau.find('.timeline-erreur');
    var bouton = panneau.find('.timeline-plus');
    var fin = panneau.find('.timeline-fin');
    var enCours = false;
    var premiereOuverture = true;

    /** Charge une tranche et l ajoute a la suite de celles deja affichees. */
    function charger(avant) {
        if (enCours) {
            return;
        }

        enCours = true;
        bouton.prop('disabled', true);
        erreur.addClass('hidden');

        $.ajax({
            url: adresse,
            data: avant ? { avant: avant } : {},
            dataType: 'json'
        }).done(function (reponse) {
            attente.addClass('hidden');
            liste.append(reponse.html);

            if (reponse.suivant) {
                bouton.data('avant', reponse.suivant).removeClass('hidden');
            } else {
                bouton.addClass('hidden');
                fin.removeClass('hidden');
            }
        }).fail(function () {
            attente.addClass('hidden');
            erreur.removeClass('hidden');
            bouton.addClass('hidden');
        }).always(function () {
            enCours = false;
            bouton.prop('disabled', false);
        });
    }

    // La premiere tranche part a l ouverture de l onglet, une seule fois.
    $('a[data-toggle="tab"][href="#tab_timeline"]').on('shown.bs.tab', function () {
        if (!premiereOuverture) {
            return;
        }

        premiereOuverture = false;
        charger(null);
    });

    bouton.on('click', function () {
        charger($(this).data('avant'));
    });
});
