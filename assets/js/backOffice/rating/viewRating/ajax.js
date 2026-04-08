$(document).ready(function ()
{
    createModalEvent_rating();

    /**
     * Fonction pour consulter l'évaluation de l'Auditeur / Rénovateur
     */
    function createModalEvent_rating()
    {
        $('.button_viewRating').on('click', function()
        {
            var objectUrl = $(this).attr('data-partenaireUrl');
            var objectUrlExportRating = $(this).attr('data-partenaireUrlExportRating');
            var objectType = $(this).attr('data-partenaireType');
            var objectRaisonSociale = $(this).attr('data-partenaireRaisonSociale');

            var title = '';
            if ('0' === objectType || '1' === objectType) {
                title = 'Evaluation de ' + objectRaisonSociale;
            } else {
                return false;
            }

            setModalContent_rating(objectUrl, objectUrlExportRating, title.fontcolor("#77b636"));
        });
    }

    /**
     * Fonction pour charger le contenu de la modal
     *
     * @param objectUrl
     * @param objectUrlExportRating
     * @param title
     */
    function setModalContent_rating(objectUrl, objectUrlExportRating, title)
    {
        $('.modal_view .modal-body').load(objectUrl, function()
        {
            $('.modal_view #modal_view-titleText').html(title);
            $('a#partenaireUrlExportRating').attr('href', objectUrlExportRating);

            $('#modal_view').modal({ show: true });
        });
    }
});
