$(document).ready(function()
{
    saveEvent_commentaire(null);
    createModalEvent_commentaire();

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
    function createModalEvent_commentaire()
    {
        $('#button_position2').on('click', function(e)
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
});
