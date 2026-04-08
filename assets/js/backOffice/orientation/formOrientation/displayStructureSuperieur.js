$(document).ready(function() {

    initStructureSuperieur();

    /* *****************************************************************
    ********************************************************************
                La fonction qui ajoute un formulaire
    ********************************************************************
    *******************************************************************/
    /**
     *
     * @param $container
     */
    function addStructureSuperieur($container) {

        var dataPrototype = $container.attr('data-prototype');

        if (!dataPrototype) {
            return;
        }

        // Dans le contenu de l'attribut « data-prototype », on remplace :
        // - le texte "__name__label__" qu'il contient par vide
        // - le texte "__name__" qu'il contient par le numéro du champ
        let template = dataPrototype
            //.replace(/__name__label__/g,
            .replace(/__name__label__/g, '')
            .replace(/__name__/g, window.indexStructureSuperieur)
        ;

        // On crée un objet jquery qui contient ce template
        var $prototype = $(template);

        if (parseInt(window.indexStructureSuperieur) > 0) {
            // On ajoute au prototype un lien pour pouvoir supprimer element
            addDeleteStructureSuperieurLink($prototype);
        }

        // On ajoute le prototype modifié à la fin de la balise <div>
        $container.append($prototype);

        formatStructureSuperieurHtml($container);

        // Enfin, on incrémente le compteur pour que le prochain ajout se fasse avec un autre numéro
        window.indexStructureSuperieur++;

        let firstStructureSuperieurParentFormGroup = $('div#whitelabel_backofficebundle_orientation_orientation_structureSuperieur > div:visible:first-child');

        if (parseInt(window.indexStructureSuperieur) == 1) {
            // On supprime le delete lien du 1er item
            firstStructureSuperieurParentFormGroup.find("p.box-delete-structureSuperieur").remove();
        } else if(parseInt(window.indexStructureSuperieur) > 1) {
            // On ajoute le delete lien sur le 1er aussi
            addDeleteStructureSuperieurLink(firstStructureSuperieurParentFormGroup);
        }
    }

    /**
     *
     * @param $container
     */
    function formatStructureSuperieurHtml($container)  {

        // Cacher les label générés automatiquement et mise en forme
        $container.children('div').children('label').addClass('hidden');
        $container.children('div').children('div').children('div').addClass('form-group');
        $container.children('div').children('div').children('div').children('label').addClass('col-xs-12 col-sm-12 col-md-4 col-lg-4 control-label required');
        $container.children('div').children('div').children('div').children('select').wrap( '<div class="col-xs-12 col-sm-12 col-md-8 col-lg-8 custom-select"></div>' );
    }


    /* *****************************************************************
    ********************************************************************
                        FUNCTION - DELETING ELEMENT
    ********************************************************************
    *******************************************************************/
    /**
     *
     * @param $prototype
     */
    function addDeleteStructureSuperieurLink($prototype) {

        var $deleteLink = $('' +
            '<p class="box-delete-structureSuperieur">' +
                '<a class="btn btn-danger btn-xs">' +
                    '<i class="glyphicon glyphicon-trash"></i>' +
                '</a>' +
            '</p>'
        );
        $prototype.append($deleteLink);

        $deleteLink.click(function(e) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette structure ?')) {
                $prototype.remove();

                window.indexStructureSuperieur--;

                if (parseInt(window.indexStructureSuperieur) == 1) {
                    // On supprime le delete lien du 1er item
                    let firstStructureSuperieurParentFormGroup = $('div#whitelabel_backofficebundle_orientation_orientation_structureSuperieur > div:visible:first-child');
                    firstStructureSuperieurParentFormGroup.find("p.box-delete-structureSuperieur").remove();
                }

                e.preventDefault();
                return false;
            }
        });
    }

    /**
     *
     */
    function initStructureSuperieur() {

        // On récupère la balise <div> en question qui contient l'attribut « data-prototype »
        var $container = $('div#whitelabel_backofficebundle_orientation_orientation_structureSuperieur');
        window.indexStructureSuperieur = $container.find('select[id^=whitelabel_backofficebundle_orientation_orientation_structureSuperieur]').length;

        /* *****************************************************************
        ********************************************************************
            On ajoute un nouveau champ à chaque clic sur le lien d'ajout.
        ********************************************************************
        *******************************************************************/
        $('#add_structureSuperieur').on('click', function(e) {
            addStructureSuperieur($container);

            e.preventDefault(); // évite qu'un # apparaisse dans l'URL
            return false;
        });

        formatStructureSuperieurHtml($container);

        /* *****************************************************************
        ********************************************************************
        On ajoute un premier champ automatiquement s'il n'en existe pas déjà un
        Sinon on y ajoute les boutons delete à coté de chacun des elements
        ********************************************************************
        *******************************************************************/
        if (parseInt(window.indexStructureSuperieur) > 0 ) {
            var kDelete = 0;
            // S'il existe déjà des items, on ajoute un lien de suppression pour chacun d'entre eux sauf sur le 1er item
            $container.children('div').each(function() {
                if (kDelete > 0 || window.indexStructureSuperieur > 1) {
                    addDeleteStructureSuperieurLink($(this));
                }
                kDelete++;
            });
        } else {
            // On en ajoute un par defaut en creation
            $("#add_structureSuperieur").trigger("click");
        }
    }
});
