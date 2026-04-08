$(document).ready(function() {
    var $container = $('div#whitelabel_backofficebundle_structure__structure_permanence');

    $container.children('div').children('label').addClass('hidden');
    $container.children('div').addClass('box-structure_permanence').children('div').addClass('structure_permanence');

    var index = $container.find(':input').length;

    /* *****************************************************************
     ********************************************************************
     On ajoute un nouveau champ à chaque clic sur le lien d'ajout.
     ********************************************************************
     *******************************************************************/
    $('#add_permanence').click(function(e) {
        addPermanence($container);

        $container.children('div').children('label').addClass('hidden');
        $container.children('div').addClass('box-structure_permanence').children('div').addClass('structure_permanence');

        $('.permanence-telephone').formatter(format_telephone);
        $('.permanence-codePostal').formatter(format_code_postal);

        e.preventDefault();
        return false;
    });

    /* *****************************************************************
     ********************************************************************
     On ajoute un premier champ automatiquement s'il n'en existe pas déjà un.
     ********************************************************************
     *******************************************************************/
    if (index == 0) {
        addFirstPermanence($container);

        $container.children('div').children('label').addClass('hidden');
        $container.children('div').addClass('box-structure_permanence').children('div').addClass('structure_permanence');
    } else {
        $container.children('div').each(function() {
            addDeleteLink($(this));
        });
    }

    /* *****************************************************************
     ********************************************************************
     La fonction qui ajoute un premier formulaire Structure_permanenceType.
     ********************************************************************
     *******************************************************************/
    function addFirstPermanence($container) {
        var dataPrototype = $container.attr('data-prototype');

        if (!dataPrototype) {
            return;
        }

        var template = dataPrototype
            .replace(/__name__label__/g, '')
            .replace(/__name__/g,        index)
        ;

        var $prototype = $(template);

        $container.append($prototype);

        index++;
    }

    /* *****************************************************************
     ********************************************************************
     La fonction qui ajoute un formulaire Structure_permanenceType.
     ********************************************************************
     *******************************************************************/
    function addPermanence($container) {
        var dataPrototype = $container.attr('data-prototype');

        if (!dataPrototype) {
            return;
        }

        var template = dataPrototype
            .replace(/__name__label__/g, '')
            .replace(/__name__/g,        index)
        ;

        var $prototype = $(template);

        addDeleteLink($prototype);
        $container.append($prototype);

        index++;
    }

    /* *****************************************************************
     ********************************************************************
     La fonction qui ajoute un lien de suppression d'une permanence.
     ********************************************************************
     *******************************************************************/
    function addDeleteLink($prototype) {
        var $deleteLink = $('' +
            '<p class="box_deletePermanence">' +
            '<a href="#" class="btn btn-danger btn-xs">' +
            '<i class="glyphicon glyphicon-trash"></i> Supprimer la permanence' +
            '</a>' +
            '</p>'
        );

        $prototype.append($deleteLink);

        $deleteLink.click(function (e) {
            if (confirm('Êtes-vous sûr de vouloir supprimer la permanence ?')) {
                $prototype.remove();

                e.preventDefault();
                return false;
            }
        });
    }
});
