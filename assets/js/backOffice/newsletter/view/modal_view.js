$(document).ready(function ()
{    
    createModalEvent();    

    /**
     * Fonction pour ouvrir la modal 
     */
    function createModalEvent()
    {
        $('.button_viewNewsletter').on('click', function(e)
        {  
            var newsletterUrl = $(this).attr('data-NewsletterUrl');
            var title = "Message";

            setModalContent(newsletterUrl, title.fontcolor("#77b636"));

            e.preventDefault();
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
        $('.modal_newsletter .modal-body').load(contactUrl, function()
        {
            $('.modal_newsletter #modal_newsletter-titleText').html(title);
            $('#modal_newsletter').modal(
                { show: true }
            );           
        });
    }
});
