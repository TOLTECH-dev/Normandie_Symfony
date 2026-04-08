$(document).ready(function()
{
    // On récupère la balise <div> en question qui contient l'attribut « data-prototype »
    var $container = $('div#whitelabel_backofficebundle_epci__EPCI_contact');

    $container.children('div').children('label').addClass('hidden');
    $container.children('div').addClass('box-EPCI_contact').children('div').addClass('EPCI_contact');

    addRequiredBySelector('.EPCI_contact');

    var index = $container.find('div.box-EPCI_contact').length;

    /* *****************************************************************
    ********************************************************************
        On ajoute un nouveau champ à chaque clic sur le lien d'ajout.
    ********************************************************************
    *******************************************************************/
    $('#add_contact').click(function(e) {
        addContact($container);

        // Cacher les label générés automatiquement et mise en forme
        $container.children('div').children('label').addClass('hidden');
        $container.children("div:nth-child("+index+")").addClass('box-EPCI_contact').children('div').addClass('new_EPCI_contact');

        addRequiredBySelector('.new_EPCI_contact');

        $('.contact-telephone').formatter(format_telephone);

        e.preventDefault(); // évite qu'un # apparaisse dans l'URL
        return false;
    });

    /* *****************************************************************
    ********************************************************************
    On ajoute un premier champ automatiquement s'il n'en existe pas déjà un.
    ********************************************************************
    *******************************************************************/
    if (index == 0) {
        // addFirstContact($container);

        // Cacher les label générés automatiquement et mise en forme
        //$container.children('div').children('label').addClass('hidden');
        //$container.children('div').children('div').addClass('EPCI_contact');
    } else {
        // S'il existe déjà des contacts, on ajoute un lien de suppression pour chacun d'entre eux
        $container.children('div').each(function() {
            addDeleteLink($(this));
        });
    }

    /* *****************************************************************
    ********************************************************************
    La fonction qui ajoute un premier formulaire Structure_contactType.
    ********************************************************************
    *******************************************************************/
    function addFirstContact($container) {
        var dataPrototype = $container.attr('data-prototype');

        if (!dataPrototype) {
            return;
        }

        // Dans le contenu de l'attribut « data-prototype », on remplace :
        // - le texte "__name__label__" qu'il contient par le label du champ
        // - le texte "__name__" qu'il contient par le numéro du champ
        var template = dataPrototype
            //.replace(/__name__label__/g, 'Contact n°' + (index+1))
                .replace(/__name__label__/g, '')
                .replace(/__name__/g,        index)
        ;

        // On crée un objet jquery qui contient ce template
        var $prototype = $(template);

        // On ajoute le prototype modifié à la fin de la balise <div>
        $container.append($prototype);

        // Enfin, on incrémente le compteur pour que le prochain ajout se fasse avec un autre numéro
        index++;
    }

    /* *****************************************************************
    ********************************************************************
    La fonction qui ajoute un formulaire Structure_contactType.
    ********************************************************************
    *******************************************************************/
    function addContact($container) {
        var dataPrototype = $container.attr('data-prototype');

        if (!dataPrototype) {
            return;
        }

        // Dans le contenu de l'attribut « data-prototype », on remplace :
        // - le texte "__name__label__" qu'il contient par le label du champ
        // - le texte "__name__" qu'il contient par le numéro du champ
        var template = dataPrototype
            //.replace(/__name__label__/g, 'Contact n°' + (index+1))
                .replace(/__name__label__/g, '')
                .replace(/__name__/g,        index)
        ;

        // On crée un objet jquery qui contient ce template
        var $prototype = $(template);

        // On ajoute au prototype un lien pour pouvoir supprimer le contact
        addDeleteLink($prototype);

        // On ajoute le prototype modifié à la fin de la balise <div>
        $container.append($prototype);

        // Enfin, on incrémente le compteur pour que le prochain ajout se fasse avec un autre numéro
        index++;
    }

    /* *****************************************************************
     ********************************************************************
     La fonction qui ajoute un lien de suppression d'un contact.
     ********************************************************************
     *******************************************************************/
    function addDeleteLink($prototype) {
        // Création du lien
        var $deleteLink = $('<p class="box_deleteContact"><a href="#" class="btn btn-danger btn-xs"><i class="glyphicon glyphicon-trash"></i></a></p>');

        // Ajout du lien
        $prototype.append($deleteLink);

        // Ajout du listener sur le clic du lien pour effectivement supprimer l'EPCI
        $deleteLink.click(function(e) {
            if (confirm('Êtes-vous sûr de vouloir supprimer l\'EPCI ?')) {
                $prototype.remove();

                index--;

                e.preventDefault();
                return false;
            }
        });
    }

    // Add required field
    function addRequiredBySelector(cssSelector) {
        var elt_contact = document.querySelectorAll(cssSelector);
        $.each(elt_contact, function () {
            // Nom
            $(this).children('div:nth-child(2)').children('label').addClass('required');
            $(this).children('div:nth-child(2)').children('input').attr('required', true);

            // Prénom
            $(this).children('div:nth-child(3)').children('label').addClass('required');
            $(this).children('div:nth-child(3)').children('input').attr('required', true);

            // Email
            $(this).children('div:nth-child(6)').children('label').addClass('required');
            $(this).children('div:nth-child(6)').children('input').attr('required', true);
        });
    }
});
