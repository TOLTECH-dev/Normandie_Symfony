$(document).ready(function ()
{
    createModalEvent();

    /**
     * Fonction pour ouvrir la modal
     */
    function createModalEvent()
    {
        $('.button_frise_avancement').on('click', function(e)
        {
            var friseAvancementUrl = $(this).attr('data-friseAvancementUrl');
            var demandeId = $(this).attr('data-demandeId');
            var title = "Etat d'avancement du Dossier n°" + demandeId;

            setModalContent(friseAvancementUrl, title.fontcolor("#77b636"));

            e.preventDefault();
        });
    }

    /**
     * Fonction pour charger le contenu de la modal
     * @param friseAvancementUrl
     * @param title
     */
    function setModalContent(friseAvancementUrl, title)
    {
        $('.modal_frise_avancement .modal-body').load(friseAvancementUrl, function()
        {
            $('.modal_frise_avancement #modal_titleText').html(title);
            $('#modal_frise_avancement').modal(
                { show: true }
            );
        });
    }
});