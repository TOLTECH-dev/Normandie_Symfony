$(document).ready(function() {
    var idDocumentInfiltrometrieFinDeChantier = $('#whitelabel_backofficebundle_remboursement__remboursement_travaux_ficheTechnique_ficheTechnique_finChantier_infiltrometrieDocument');
    var idBoutonValider = $("#whitelabel_backofficebundle_remboursement__valider");
    var messageDocumentInfiltrometrieFinDeChantier = $("#custom-control_documentInfiltrometrie_finChantier");

    idDocumentInfiltrometrieFinDeChantier.change(function(e) {
        var file = e.target.files[0];
        validateFile(file, messageDocumentInfiltrometrieFinDeChantier, idBoutonValider, 'pdf');
    });
});
