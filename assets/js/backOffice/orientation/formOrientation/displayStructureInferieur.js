$(document).ready(function() {

    initStructureInferieur();

    /* *****************************************************************
    ********************************************************************
                La fonction qui ajoute un formulaire
    ********************************************************************
    *******************************************************************/
    /**
     *
     * @param $container
     */
    function addStructureInferieur($container) {

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
            .replace(/__name__/g, window.indexStructureInferieur)
        ;

        // On crée un objet jquery qui contient ce template
        var $prototype = $(template);

        if (parseInt(window.indexStructureInferieur) > 0) {
            // On ajoute au prototype un lien pour pouvoir supprimer element courant ajouté
            addDeleteStructureInferieurLink($prototype);
        }

        // On ajoute le prototype modifié à la fin de la balise <div>
        $container.append($prototype);

        formatStructureInferieurHtml($container);

        // Enfin, on incrémente le compteur pour que le prochain ajout se fasse avec un autre numéro
        window.indexStructureInferieur++;

        let firstStructureInferieurParentFormGroup = $('div#whitelabel_backofficebundle_orientation_orientation_structureInferieur > div:visible:first-child');

        if (parseInt(window.indexStructureInferieur) == 1) {
            // On supprime le delete lien du 1er item
            firstStructureInferieurParentFormGroup.find("p.box-delete-structureInferieur").remove();
        } else if(parseInt(window.indexStructureInferieur) > 1) {
            // On ajoute le delete lien sur le 1er aussi
            addDeleteStructureInferieurLink(firstStructureInferieurParentFormGroup);
        }
    }

    /**
     *
     * @param $container
     */
    function formatStructureInferieurHtml($container)  {

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
    function addDeleteStructureInferieurLink($prototype) {

        let $deleteLink = $('' +
            '<p class="box-delete-structureInferieur">' +
            '<a class="btn btn-danger btn-xs">' +
            '<i class="glyphicon glyphicon-trash"></i>' +
            '</a>' +
            '</p>'
        );
        $prototype.append($deleteLink);

        $deleteLink.click(function(e) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette structure ?')) {

                $prototype.remove();
                window.indexStructureInferieur--;

                if (parseInt(window.indexStructureInferieur) == 1) {
                    // On supprime le delete lien du 1er item
                    let firstStructureInferieurParentFormGroup = $('div#whitelabel_backofficebundle_orientation_orientation_structureInferieur > div:visible:first-child');
                    firstStructureInferieurParentFormGroup.find("p.box-delete-structureInferieur").remove();
                }

                e.preventDefault();
                return false;
            }
        });
    }

    /**
     *
     */
    function initStructureInferieur() {

        // On récupère la balise <div> en question qui contient l'attribut « data-prototype »
        var $container = $('div#whitelabel_backofficebundle_orientation_orientation_structureInferieur');
        window.indexStructureInferieur = $container.find('select[id^=whitelabel_backofficebundle_orientation_orientation_structureInferieur]').length;

        /* *****************************************************************
        ********************************************************************
            On ajoute un nouveau champ à chaque clic sur le lien d'ajout.
        ********************************************************************
        *******************************************************************/
        $('#add_structureInferieur').on('click', function(e) {
            addStructureInferieur($container);

            e.preventDefault(); // évite qu'un # apparaisse dans l'URL
            return false;
        });

        formatStructureInferieurHtml($container);

        /* *****************************************************************
        ********************************************************************
        On ajoute un premier champ automatiquement s'il n'en existe pas déjà un
        Sinon on y ajoute les boutons delete à coté de chacun des elements
        ********************************************************************
        *******************************************************************/
        if (parseInt(window.indexStructureInferieur) > 0 ) {
            var kDelete = 0;
            // S'il existe déjà des items, on ajoute un lien de suppression pour chacun d'entre eux sauf sur le 1er item
            $container.children('div').each(function() {
                if (kDelete > 0 || window.indexStructureInferieur > 1) {
                    addDeleteStructureInferieurLink($(this));
                }
                kDelete++;
            });
        } else {
            // On en ajoute un par defaut en creation
            $("#add_structureInferieur").trigger("click");
        }
    }
});
