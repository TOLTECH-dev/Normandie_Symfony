$(document).ready(function() {
    // On récupère la balise <div> en question qui contient l'attribut « data-prototype »
    var $container = $('div#whitelabel_backofficebundle_structure__structure_contact');

    // Cacher les label générés automatiquement et mise en forme
    $container.children('div').children('label').addClass('hidden');
    $container.children('div').children('div').addClass('structure_contact');

    // On définit un compteur unique pour nommer les champs qu'on va ajouter dynamiquement
    var index = $container.find(':input').length;

    /* *****************************************************************
    ********************************************************************
        On ajoute un nouveau champ à chaque clic sur le lien d'ajout.
    ********************************************************************
    *******************************************************************/
    $('#add_contact').click(function(e) {
        addContact($container);

        // Cacher les label générés automatiquement et mise en forme
        $container.children('div').children('label').addClass('hidden');
        $container.children('div').children('div').addClass('structure_contact');

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
        addFirstContact($container);

        // Cacher les label générés automatiquement et mise en forme
        $container.children('div').children('label').addClass('hidden');
        $container.children('div').children('div').addClass('structure_contact');
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

        // Ajout du listener sur le clic du lien pour effectivement supprimer le contact
        $deleteLink.click(function (e) {
            if (confirm('Êtes-vous sûr de vouloir supprimer le contact ?')) {
                $prototype.remove();

                e.preventDefault();
                return false;
            }
        });
    }
});
