$(document).ready(function() {
    var $container = $('div#whitelabel_frontofficebundle_demande_travaux_devis_demande_travaux_devis_upload');

    $container.children('div').children('label').addClass('hidden');
    $container.children('div').children('div').addClass('demande_travaux_devis_upload');

    var index = $container.find(':input[type=number]').length;

    var typeDocumentDevis = 'devisDocument';
    var typeDocumentAudit = 'audit';

    var elt_document_init = document.querySelectorAll('.custom-file');
    validateAllFile(elt_document_init);

    /* *****************************************************************
    ********************************************************************
     On ajoute les liens vers les documents des devis
    ********************************************************************
    *******************************************************************/
    $container.children('div').children('div').children('div:last-child()')
        .append('<div class="container-devis_upload_document" style="height:30px;font-size:1.1em;line-height:30px;"></div>');

    var elt_devis_upload_document = document.querySelectorAll('.devis_upload_document');
    var i = 1;
    var regex = new RegExp(/([^A-Za-z\-])/);

    $.each(elt_devis_upload_document, function () {
        var extension = $(this).data('extension');

        if (!regex.test(extension) && $(this).val()) {
            $container.children('div:nth-child(' + i + ')').children('div').children('div:last-child()').children('div')
                .append('<a href="' + $(this).val() + '" target="_blank">Voir le document</a>');

            // Pour les fichiers deja presents, retirer les proprietes obligatoires (label et input)
            $container.children('div:nth-child(' + i + ')').children('div').children('div:last-child()').children("label").removeClass('required');
            $container.children('div:nth-child(' + i + ')').children('div').children('div:last-child()').children("input").removeAttr('required');
        }
        i++;
    });

    /* *****************************************************************
    ********************************************************************
        On ajoute un nouveau champ à chaque clic sur le lien d'ajout.
    ********************************************************************
    *******************************************************************/
    $("#add_document").on("click", function (e) {
        addUpload($container);

        $container.children('div').children('label').addClass('hidden');
        $container.children('div').children('div').addClass('demande_travaux_devis_upload');

        var elt_montant = document.querySelectorAll('.montantDevis');
        calcul(elt_montant);

        $("#bloc-document input[type=number]").on('change blur keyup load', function () {
            calcul(elt_montant);
        });

        $("#whitelabel_frontofficebundle_demande_travaux_devis_demande_travaux_devis_upload_" + (index - 1) + "_devisDocument").change(function() {
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
        $container.children('div').children('div').addClass('demande_travaux_devis_upload');
    } else {
        var k = 0;
        $container.children('div').each(function() {
            addDeleteLink($(this), k);
            k++;
        });
    }

    // Loading page
    var montantInit = 0;
    if ($('#whitelabel_frontofficebundle_demande_travaux_devis_totalDevis').val())  {
        montantInit = $('#whitelabel_frontofficebundle_demande_travaux_devis_totalDevis').val();
    }
    $('#whitelabel_frontofficebundle_demande_travaux_devis_totalDevis').attr('value', montantInit);

    // Calculating sum devis
    var elt_montant_init = document.querySelectorAll('.montantDevis');
    $("#bloc-document input[type=number]").on('change blur keyup load', function () {
        calcul(elt_montant_init);
    });

    // Checking file
    $('input:file').change(function() {
        var elt_document_init = document.querySelectorAll('.custom-file');
        validateAllFile(elt_document_init);
    });

    /* *****************************************************************
     ********************************************************************
     La fonction qui ajoute un premier formulaire Demande_travaux_devis_uploadType.
     ********************************************************************
     *******************************************************************/
    /**
     *
     * @param $container
     */
    function addFirstUpload($container) {
        var template = $container.attr('data-prototype')
            .replace(/__name__label__/g, '')
            .replace(/__name__/g,        index)
        ;

        var $prototype = $(template);

        $container.append($prototype);

        index++;
    }

    /* *****************************************************************
     ********************************************************************
     La fonction qui ajoute un formulaire Demande_travaux_devis_uploadType.
     ********************************************************************
     *******************************************************************/
    /**
     *
     * @param $container
     */
    function addUpload($container) {
        var template = $container.attr('data-prototype')
            .replace(/__name__label__/g, '')
            .replace(/__name__/g,        index)
        ;

        var $prototype = $(template);

        addDeleteLink($prototype, 99);
        $container.append($prototype);

        index++;
    }

    /* *****************************************************************
     ********************************************************************
     La fonction qui ajoute un lien de suppression d'un montant.
     ********************************************************************
     *******************************************************************/
    /**
     *
     * @param $prototype
     * @param k
     */
    function addDeleteLink($prototype, k) {
        if (0 != k) {
            var $deleteLink = $('<p class="box_deleteDocument"><a href="#" class="btn btn-danger btn-xs"><i class="glyphicon glyphicon-trash"></i></a></p>');

            $prototype.append($deleteLink);

            $deleteLink.on("click", function (e) {
                if (confirm('Êtes-vous sûr de vouloir supprimer le devis ?')) {
                    $prototype.remove();

                    var elt_montant = document.querySelectorAll('.montantDevis');
                    calcul(elt_montant);

                    // Checking file
                    var elt_document_init = document.querySelectorAll('.custom-file');
                    validateAllFile(elt_document_init);

                    e.preventDefault();
                    return false;
                }
            });
        }
    }

    /* *****************************************************************
     ********************************************************************
     La fonction qui calcule le montant total des Devis.
     ********************************************************************
     *******************************************************************/
    /**
     *
     * @param element
     */
    function calcul(element) {
        var montant = 0;

        $.each(element, function () {
            var value = parseInt($(this).val(), 10);
            if (isNaN(value)) value = 0;
            montant += value;
        });
        $('#whitelabel_frontofficebundle_demande_travaux_devis_totalDevis').attr('value', montant);
    }

    /* *****************************************************************
     ********************************************************************
     La fonction qui vérifie la validité de tous les fichiers
     ********************************************************************
     *******************************************************************/
    /**
     *
     * @param element
     */
    function validateAllFile(element) {
        var idBoutonValider = $("#button_popupValidation");
        var errorFlag = false;
        var numberDevisDocument = 0;

        $.each(element, function() {
            var splitId = $(this).attr('id').split("_");
            var typeDocument = splitId.pop();

            if (typeDocumentDevis == typeDocument) {
                numberDevisDocument++;
            }

            errorFlag = validateFile(this.files[0], typeDocument, errorFlag, numberDevisDocument);
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
    /**
     *
     * @param file
     * @param typeDocument
     * @param errorFlag
     * @param numberDevisDocument
     * @returns {boolean}
     */
    function validateFile(file, typeDocument, errorFlag, numberDevisDocument) {
        var fileExtension = ["application/pdf", "image/jpg", "image/jpeg", "image/png"];
        var message = $("#custom-control_" + typeDocument);
        var normalSize = (typeDocumentAudit == typeDocument) ? '10485760' : '5242880';
        var maxSizeLibelle = (typeDocumentAudit == typeDocument) ? '10 MB' : '5 MB';

        if ('devisDocument' != typeDocument || ('devisDocument' == typeDocument && '1' == numberDevisDocument)) {
            message.empty();
        }

        //if ($(this).get(0).files[0]) {
        if (file) {
            if (file.size > normalSize) {
                if (typeDocumentDevis == typeDocument) {
                    message.append('<li>Ligne ' + numberDevisDocument + ' : Le fichier ' + file.name + ' est trop volumineux (' + formatFileSize(file) + '). Sa taille ne doit pas dépasser ' + maxSizeLibelle + '.</li>').css('color', 'red');
                } else {
                    message.append('<li>Le fichier ' + file.name + ' est trop volumineux (' + formatFileSize(file) + '). Sa taille ne doit pas dépasser ' + maxSizeLibelle + '. </li>').css('color', 'red');
                }
                errorFlag = true;
            }

            if (fileExtension.indexOf(file.type) <= -1) {
                if (typeDocumentDevis == typeDocument) {
                    message.append('<li>Ligne ' + numberDevisDocument + ' : Le type du fichier est invalide. Les types autorisés sont ".pdf", ".jpg", ".jpeg", ".png". </li>').css('color', 'red');
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
    /**
     *
     * @param file
     * @returns {string}
     */
    function formatFileSize(file) {
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        if (0 == file.size) return '0 Byte';
        var i = parseInt(Math.floor(Math.log(file.size) / Math.log(1024)));

        return Math.round(file.size / Math.pow(1024, i), 2) + ' ' + sizes[i];
    }
});
