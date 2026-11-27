/**
 * Gestion des motifs de non-conformite (D26013) : memorisation de l onglet.
 *
 * Les actions de la page reviennent sur l onglet du document concerne, le
 * serveur le reprecisant dans l adresse. Il reste le cas du simple
 * rafraichissement apres un changement d onglet : sans ce script, la page
 * reviendrait sur le premier document.
 *
 * Les ecouteurs sont poses par jQuery, et non en attributs : la politique de
 * securite du contenu refuse les gestionnaires inscrits dans le HTML.
 */
$(document).ready(function () {
    var onglets = $('#onglets-motifs');

    if (0 === onglets.length || !window.history || !window.history.replaceState) {
        return;
    }

    onglets.on('shown.bs.tab', 'a[data-toggle="tab"]', function () {
        var cle = $(this).data('cle');

        if (!cle) {
            return;
        }

        var adresse = window.location.pathname + '?doc=' + encodeURIComponent(cle);

        window.history.replaceState(null, '', adresse);
    });
});
