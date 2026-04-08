var fileExtensionXml = ["application/xml", "text/xml"];
var fileExtensionPdf = ["application/pdf"];

/* *************************************************
                    FUNCTION
************************************************* */
function validateFile(file, message, idSubmit, extension) {
    var errorFlag = false;
    message.empty();
    var normalSize = '5242880';
    if (file.size > normalSize) {
        message.append('<li>Le fichier ' + file.name + ' est trop volumineux (' + formatFileSize(file) + '). Sa taille ne doit pas dépasser 5 MB. </li>').css('color', 'red');
        errorFlag = true;
    }

    if ('xml' == extension) {
        if (fileExtensionXml.indexOf(file.type) <= -1) {
            message.append('<li>Le type du fichier est invalide. Les types autorisés sont ".xml". </li>').css('color', 'red');
            errorFlag = true;
        }
    } else if ('pdf' == extension) {
        if (fileExtensionPdf.indexOf(file.type) <= -1) {
            message.append('<li>Le type du fichier est invalide. Les types autorisés sont ".pdf". </li>').css('color', 'red');
            errorFlag = true;
        }
    }

    if (true == errorFlag) {
        idSubmit.prop('disabled', true);
    } else {
        idSubmit.prop('disabled', false);
    }
        
    return errorFlag;
}

function formatFileSize(file) {
    var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    if (0 == file.size) return '0 Byte';
    var i = parseInt(Math.floor(Math.log(file.size) / Math.log(1024)));

    return Math.round(file.size / Math.pow(1024, i), 2) + ' ' + sizes[i];
}

