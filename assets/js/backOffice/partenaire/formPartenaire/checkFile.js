$(document).ready(function() {
    var messageDocument     = $("#custom-control_document");
    var idBoutonValider     = $("#whitelabel_backofficebundle_partenaire__valider");
    var fileExtension       = ["application/pdf", "image/jpg", "image/jpeg", "image/png"];

    /* *************************************************
                        AUDITEUR
    ************************************************* */
    var idDocument = $('#whitelabel_backofficebundle_partenaire__partenaire_optionAuditeur_rib');

    idDocument.change(function() {
        validateFile(this.files[0], messageDocument, idBoutonValider);
    });

    /* *************************************************
                        FUNCTION
    ************************************************* */
    function validateFile(file, message, idSubmit) {
        var errorFlag = false;
        var normalSize = '5242880';
        message.empty();

        if (file.size > normalSize) {
            message.append('<li>Le fichier ' + file.name + ' est trop volumineux (' + formatFileSize(file) + '). Sa taille ne doit pas dépasser 5 MB. </li>').css('color', 'red');
            errorFlag = true;
        }

        if (fileExtension.indexOf(file.type) <= -1) {
            message.append('<li>Le type du fichier est invalide. Les types autorisés sont ".pdf", ".jpg", ".jpeg", ".png". </li>').css('color', 'red');
            errorFlag = true;
        }

        if (true == errorFlag) {
            idSubmit.prop('disabled', true);
        } else {
            idSubmit.prop('disabled', false);
        }
    }

    function formatFileSize(file) {
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        if (0 == file.size) return '0 Byte';
        var i = parseInt(Math.floor(Math.log(file.size) / Math.log(1024)));

        return Math.round(file.size / Math.pow(1024, i), 2) + ' ' + sizes[i];
    }
});
