$(document).ready(function() {
    var idDocumentAuditApresTravauxFinDeChantier = $('#whitelabel_backofficebundle_remboursement__remboursement_travaux_ficheTechnique_ficheTechnique_finChantier_auditApresTravauxDocument');
    var idBoutonValider = $("#whitelabel_backofficebundle_remboursement__valider");
    var messageDocumentAuditApresTravauxFinDeChantier = $("#custom-control_documentAuditApresTravaux_finChantier");

    idDocumentAuditApresTravauxFinDeChantier.change(function(e) {
        var file = e.target.files[0];
        validateFile(file, messageDocumentAuditApresTravauxFinDeChantier, idBoutonValider, 'pdf');
    });
});
