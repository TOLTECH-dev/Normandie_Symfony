$(document).ready(function()
{
    var $container = $('div#whitelabel_backofficebundle_remboursement__remboursement_travaux_instruction_remboursement_travaux_instruction_conformite');

    $container.children('div').children('label').addClass('hidden');
    $container.children('div').children('div').addClass('remboursement_travaux_instruction_conformite');

    var index = $container.find(':input[type=file]').length;

    var elt_document_init = document.querySelectorAll('.custom-file');
    validateAllFile(elt_document_init);

    /* *****************************************************************
    ********************************************************************
     On ajoute les liens vers les documents des devis
    ********************************************************************
    *******************************************************************/
    $container.children('div').children('div').children('div:last-child()')
        .append('<div class="container-instruction_conformite_document" style="height:30px;font-size:1.1em;line-height:30px;"></div>');

    var elt_instruction_conformite_document = document.querySelectorAll('.instruction_conformite_document');
    var i = 1;
    var regex = new RegExp(/([^A-Za-z\-])/);

    $.each(elt_instruction_conformite_document, function () {
        var extension = $(this).val().substr(-3);
        if (!regex.test(extension)) {
            $container.children('div:nth-child(' + i + ')').children('div').children('div:last-child()').children('div')
                .append('<a href="' + $(this).val() + '" target="_blank">Voir le document</a>');
        }
        i++;
    });

    /* *****************************************************************
    ********************************************************************
        On ajoute un nouveau champ à chaque clic sur le lien d'ajout.
    ********************************************************************
    *******************************************************************/
    $('#add_conformite').click(function(e) {
        addUpload($container);

        $container.children('div').children('label').addClass('hidden');
        $container.children('div').children('div').addClass('remboursement_travaux_instruction_conformite');

        $("#whitelabel_backofficebundle_remboursement__remboursement_travaux_instruction_remboursement_travaux_instruction_conformite_" + (index - 1) + "_document").change(function() {
            var elt_document_init = document.querySelectorAll('.custom-file');
            validateAllFile(elt_document_init);
        });

        e.preventDefault();
        return false;
    });

    /* *****************************************************************
     ********************************************************************
     On ajoute un premier champ automatiquement s'il n'en existe pas déjà un.
     ********************************************************************
     *******************************************************************/
    if (index == 0) {
        addFirstUpload($container);

        $container.children('div').children('label').addClass('hidden');
        $container.children('div').children('div').addClass('remboursement_travaux_instruction_conformite');
    } else {
        var k = 0;
        $container.children('div').each(function() {
            addDeleteLink($(this), k);
            k++;
        });
    }

    // Event File
    $('input:file').change(function() {
        var elt_document_init = document.querySelectorAll('.custom-file');
        validateAllFile(elt_document_init);
    });

    // Event Destinataire
    $('#whitelabel_backofficebundle_remboursement__remboursement_travaux_instruction_destinataire').change(function () {
        $('#whitelabel_backofficebundle_remboursement__remboursement_travaux_instruction_rib').val('');

        var elt_document_init = document.querySelectorAll('.custom-file');
        validateAllFile(elt_document_init);
    });

    /* *****************************************************************
     ********************************************************************
     La fonction qui ajoute un premier formulaire Remboursement_travaux_instruction_conformiteType
     ********************************************************************
     *******************************************************************/
    function addFirstUpload($container) {
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
     La fonction qui ajoute un formulaire Remboursement_travaux_instruction_conformiteType
     ********************************************************************
     *******************************************************************/
    function addUpload($container) {
        var dataPrototype = $container.attr('data-prototype');

        if (!dataPrototype) {
            console.error('data-prototype attribute is missing on container');
            return;
        }

        var template = dataPrototype
            .replace(/__name__label__/g, '')
            .replace(/__name__/g, index)
        ;

        var $prototype = $(template);

        addDeleteLink($prototype, 99);
        $container.append($prototype);

        index++;
    }

    /* *****************************************************************
     ********************************************************************
     La fonction qui ajoute un lien de suppression
     ********************************************************************
     *******************************************************************/
    function addDeleteLink($prototype, k) {
        if (0 != k) {
            var $deleteLink = $('<p class="box_deleteConformite"><a href="#" class="btn btn-danger btn-xs"><i class="glyphicon glyphicon-trash"></i></a></p>');

            $prototype.append($deleteLink);

            $deleteLink.click(function(e) {
                $prototype.remove();

                // Checking file
                var elt_document_init = document.querySelectorAll('.custom-file');
                validateAllFile(elt_document_init);

                e.preventDefault();
                return false;
            });
        }
    }

    /* *****************************************************************
     ********************************************************************
     La fonction qui vérifie la validité de tous les fichiers
     ********************************************************************
     *******************************************************************/
    function validateAllFile(element) {
        var idBoutonValider = $("#whitelabel_backofficebundle_remboursement__valider");
        var errorFlag = false;
        var numberFactureDocument = 0;

        $.each(element, function() {
            var splitId = $(this).attr('id').split("_");
            var typeDocument = splitId.pop();

            if ('document' == typeDocument) {
                numberFactureDocument++;
            }

            errorFlag = validateFile(this.files[0], typeDocument, errorFlag, numberFactureDocument);
        });

        if (true == errorFlag) {
            idBoutonValider.prop('disabled', true);
        } else {
            idBoutonValider.prop('disabled', false);
        }
    }

    /* *****************************************************************
     ********************************************************************
     La fonction qui vérifie la validité d'un fichier
     ********************************************************************
     *******************************************************************/
    function validateFile(file, typeDocument, errorFlag, numberFactureDocument) {
        var fileExtension = ["application/pdf", "image/jpg", "image/jpeg", "image/png"];
        var message = $("#custom-control_" + typeDocument);
        var normalSize = '5242880';

        if ('document' != typeDocument || ('document' == typeDocument && '1' == numberFactureDocument)) {
            message.empty();
        }

        if (file) {
            if (file.size > normalSize) {
                if ('document' == typeDocument) {
                    message.append('<li>Ligne ' + numberFactureDocument + ' : Le fichier ' + file.name + ' est trop volumineux (' + formatFileSize(file) + '). Sa taille ne doit pas dépasser 5 MB. </li>').css('color', 'red');
                } else {
                    message.append('<li>Le fichier ' + file.name + ' est trop volumineux (' + formatFileSize(file) + '). Sa taille ne doit pas dépasser 5 MB. </li>').css('color', 'red');
                }
                errorFlag = true;
            }

            if (fileExtension.indexOf(file.type) <= -1) {
                if ('document' == typeDocument) {
                    message.append('<li>Ligne ' + numberFactureDocument + ' : Le type du fichier est invalide. Les types autorisés sont ".pdf", ".jpg", ".jpeg", ".png". </li>').css('color', 'red');
                } else {
                    message.append('<li>Le type du fichier est invalide. Les types autorisés sont ".pdf", ".jpg", ".jpeg", ".png". </li>').css('color', 'red');
                }
                errorFlag = true;
            }
        }

        return errorFlag;
    }

    /* *****************************************************************
     ********************************************************************
     La fonction qui calcule la taille du fichier
     ********************************************************************
     *******************************************************************/
    function formatFileSize(file) {
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        if (0 == file.size) return '0 Byte';
        var i = parseInt(Math.floor(Math.log(file.size) / Math.log(1024)));

        return Math.round(file.size / Math.pow(1024, i), 2) + ' ' + sizes[i];
    }
});
