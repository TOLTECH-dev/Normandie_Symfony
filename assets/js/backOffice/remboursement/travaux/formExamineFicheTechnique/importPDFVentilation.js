$(document).ready(function() {
    var idDocumentVentilationFinDeChantier = $('#whitelabel_backofficebundle_remboursement__remboursement_travaux_ficheTechnique_ficheTechnique_finChantier_ventilationDocument');
    var idBoutonValider = $("#whitelabel_backofficebundle_remboursement__valider");
    var messageDocumentVentilationFinDeChantier = $("#custom-control_documentVentilation_finChantier");

    idDocumentVentilationFinDeChantier.change(function(e) {
        var file = e.target.files[0];
        validateFile(file, messageDocumentVentilationFinDeChantier, idBoutonValider, 'pdf');
    });
});
