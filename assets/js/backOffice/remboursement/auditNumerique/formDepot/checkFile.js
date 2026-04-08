$(document).ready(function() {
    var idBoutonValider = $("#whitelabel_backofficebundle_remboursement__valider");
    var fileExtension   = ["application/pdf", "image/jpg", "image/jpeg", "image/png"];
    var typeDocumentAudit = 'audit';

    $('input:file').change(function() {
        var errorFlag = false;

        $('input:file').each(function() {
            var splitId = $(this).attr('id').split("_");
            var typeDocument = splitId.pop();

            if ($(this).get(0).files[0]) {
                errorFlag = validateFile(this.files[0], typeDocument, $("#custom-control_" + typeDocument), errorFlag);
            }
        });

        if (true == errorFlag) {
            idBoutonValider.prop('disabled', true);
        } else {
            idBoutonValider.prop('disabled', false);
        }
    });

    /**
     *
     * @param file
     * @param typeDocument
     * @param message
     * @param errorFlag
     * @returns {boolean}
     */
    function validateFile(file, typeDocument, message, errorFlag) {
        message.empty();

        var normalSize = (typeDocumentAudit == typeDocument) ? '10485760' : '5242880';
        var maxSizeLibelle = (typeDocumentAudit == typeDocument) ? '10 MB' : '5 MB';

        if (file.size > normalSize) {
            message.append('<li>Le fichier ' + file.name + ' est trop volumineux (' + formatFileSize(file) + '). Sa taille ne doit pas dépasser ' + maxSizeLibelle + '.</li>').css('color', 'red');
            errorFlag = true;
        }

        if (fileExtension.indexOf(file.type) <= -1) {
            message.append('<li>Le type du fichier est invalide. Les types autorisés sont ".pdf", ".jpg", ".jpeg", ".png". </li>').css('color', 'red');
            errorFlag = true;
        }

        return errorFlag;
    }

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
