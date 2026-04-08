$(document).ready(function ()
{
    saveEvent(null);
    createModalEvent();
    updateModalEvent();
    deleteModalEvent();

    /**
     * Fonction pour sauvegarder le champ (ajout/edition/suppression)
     *
     * @param contactUrl
     */
    function saveEvent(contactUrl)
    {
        $('.modal-body form:not(#form_user_delete)').submit(function(e)
        {
            e.preventDefault();

            if ($('#whitelabel_backofficebundle_contact_valider').hasClass('disabled')) {
                return false;
            }
            $('#whitelabel_backofficebundle_contact_valider').addClass('disabled');

            $.ajax({
                type: $(this).attr('method'),
                url: contactUrl,
                data: $(this).serialize()
            }).done(function (data) {
                location.reload();
            }).fail(function (jqXHR, textStatus, errorThrown) {
                if ('undefined' !== typeof jqXHR.responseJSON && jqXHR.responseJSON.hasOwnProperty('form')) {
                    $('.modal-body').html(jqXHR.responseJSON.form);
                    saveEvent();
                }
            });
        });
    }

    /**
     * Fonction pour ouvrir la modal d'ajout
     */
    function createModalEvent()
    {
        $('#button_addContact').on('click', function(e)
        {
            var contactUrl = $(this).attr('data-contactUrl');
            var title = "Ajout d'un Contact";

            setModalContent(contactUrl, title.fontcolor("#77b636"));

            e.preventDefault();
        });
    }

    /**
     * Fonction pour ouvrir la modal d'édition
     */
    function updateModalEvent()
    {
        $('.button_editContact').on('click', function()
        {
            var contactUrl = $(this).attr('data-contactUrl');
            var title = "Modification d'un Contact";

            setModalContent(contactUrl, title.fontcolor("#77b636"));
        });
    }

    /**
     * Fonction pour ouvrir la modal de suppression
     */
    function deleteModalEvent()
    {
        $('.button_deleteContact').on('click', function()
        {
            var contactUrl = $(this).attr('data-contactUrl');
            var title = "Suppression d'un Contact";

            setModalContent(contactUrl, title.fontcolor("#77b636"));
        });
    }

    /**
     * Fonction pour charger le contenu de la modal
     *
     * @param contactUrl
     * @param title
     */
    function setModalContent(contactUrl, title)
    {
        $('.modal_contact .modal-body').load(contactUrl, function()
        {
            $('.modal_contact #modal_contact-titleText').html(title);
            $('#modal_contact').modal(
                { show: true },
                $('.contact_telephone').formatter(format_telephone)
            );

            saveEvent(contactUrl);
        });
    }
});
