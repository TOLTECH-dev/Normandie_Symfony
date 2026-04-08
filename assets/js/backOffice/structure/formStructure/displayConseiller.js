$(document).ready(function() {
    var $container = $('div#whitelabel_backofficebundle_structure__structure_conseiller');

    $container.children('div').children('label').addClass('hidden');
    $container.children('div').addClass('box-structure_conseiller').children('div').addClass('structure_conseiller');

    var elt_conseiller = document.querySelectorAll('.structure_conseiller');
    $.each(elt_conseiller, function () {
        // Nom du conseiller
        $(this).children('div:nth-child(1)').children('label').addClass('required');
        $(this).children('div:nth-child(1)').children('input').attr('required', true);

        // Prénom du conseiller
        $(this).children('div:nth-child(2)').children('label').addClass('required');
        $(this).children('div:nth-child(2)').children('input').attr('required', true);

        // Email du conseiller
        $(this).children('div:nth-child(4)').children('label').addClass('required');
        $(this).children('div:nth-child(4)').children('input').attr('required', true);
    });

    //var index = $container.find(':input').length;
    var index = $container.find('div.box-structure_conseiller').length;
    /* *****************************************************************
     ********************************************************************
     On ajoute un nouveau champ à chaque clic sur le lien d'ajout.
     ********************************************************************
     *******************************************************************/
    $('#add_conseiller').click(function(e) {
        addConseiller($container);

        $container.children('div').children('label').addClass('hidden');
        $container.children("div:nth-child("+index+")").addClass('box-structure_conseiller').children('div').addClass('new_structure_conseiller');

        var elt_new_conseiller = document.querySelectorAll('.new_structure_conseiller');
        $.each(elt_new_conseiller, function () {
            // Nom du conseiller
            $(this).children('div:nth-child(1)').children('label').addClass('required');
            $(this).children('div:nth-child(1)').children('input').attr('required', true);

            // Prénom du conseiller
            $(this).children('div:nth-child(2)').children('label').addClass('required');
            $(this).children('div:nth-child(2)').children('input').attr('required', true);

            // Email du conseiller
            $(this).children('div:nth-child(4)').children('label').addClass('required');
            $(this).children('div:nth-child(4)').children('input').attr('required', true);

            // box checkbox enabled
            //$(this).children('div:nth-child(6)').addClass('hidden');

            // .label_conseiller_enabled
            $(this).children('div:nth-child(6)').children('label').addClass('hidden');

            // .value_conseiller_enabled
            $(this).children('div:nth-child(6)').children('input').addClass('hidden');
            $(this).children('div:nth-child(6)').children('input').attr('checked', true);

            // box replace text
            $(this).children('div:nth-child(7)').html('' +
                '<p style="text-align:right;"><i class="glyphicon glyphicon-alert"></i> Conseiller actif par d&eacute;fault.</p>');

            $('.conseiller-telephone').formatter(format_telephone);
        });

        e.preventDefault();
        return false;
    });

    /* *****************************************************************
     ********************************************************************
     On ajoute un premier champ automatiquement s'il n'en existe pas déjà un.
     ********************************************************************
     *******************************************************************/
    if (0 != index) {
        var isAdmin = $('#isAdmin').val();

        if ('0' != isAdmin) {
            $container.children('div').each(function () {
                addDeleteLink($(this));
            });
        }
    }

    /* *****************************************************************
     ********************************************************************
     La fonction qui ajoute un premier formulaire Structure_conseillerType.
     ********************************************************************
     *******************************************************************/
    function addFirstConseiller($container) {
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
     La fonction qui ajoute un formulaire Structure_conseillerType.
     ********************************************************************
     *******************************************************************/
    function addConseiller($container) {
        var dataPrototype = $container.attr('data-prototype');

        if (!dataPrototype) {
            console.error('data-prototype attribute is missing on container');
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
     La fonction qui ajoute un lien de suppression d'un conseiller.
     ********************************************************************
     *******************************************************************/
    function addDeleteLink($prototype) {
        var $deleteLink = $('' +
            '<p class="box_deleteConseiller">' +
            '<a href="#" class="btn btn-danger btn-xs">' +
            '<i class="glyphicon glyphicon-trash"></i> Supprimer le conseiller' +
            '</a>' +
            '</p>'
        );

        $prototype.append($deleteLink);

        $deleteLink.click(function (e) {
            if (confirm('Êtes-vous sûr de vouloir supprimer le conseiller ?')) {
                $prototype.remove();

                index--;

                e.preventDefault();
                return false;
            }
        });
    }
});
