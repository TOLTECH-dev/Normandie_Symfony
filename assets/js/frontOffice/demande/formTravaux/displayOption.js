$(document).ready(function() {
    var elt_situation = $('#situationLogement_value');
    var situationLogementKey = elt_situation.val();

    if ('0' == situationLogementKey || '1' == situationLogementKey) {
        $('#bloc-revenu').removeClass('hidden');

        if ($('#whitelabel_frontofficebundle_demande__demande_travaux_revenu3').val())  {
            var revenuInit = $('#whitelabel_frontofficebundle_demande__demande_travaux_revenu3').val();
        } else {
            var revenuInit = 0;
        }
        $('#whitelabel_frontofficebundle_demande__demande_travaux_revenu3').prop('value', revenuInit);

        $("#whitelabel_frontofficebundle_demande__demande_travaux_revenu1, #whitelabel_frontofficebundle_demande__demande_travaux_revenu2").on('onchange blur keyup load', function () {
            var valueRevenu1 = $('#whitelabel_frontofficebundle_demande__demande_travaux_revenu1').val();
            var valueRevenu2 = $('#whitelabel_frontofficebundle_demande__demande_travaux_revenu2').val();

            if (valueRevenu1.length != 0) revenu1 = parseInt(valueRevenu1, 10);
            else revenu1 = 0;

            if (valueRevenu2.length != 0) revenu2 = parseInt(valueRevenu2, 10);
            else revenu2 = 0;

            var revenu3 = revenu1 + revenu2;
            $('#whitelabel_frontofficebundle_demande__demande_travaux_revenu3').prop('value', revenu3);
        });

    } else {
        resetBlocRevenu();
        $('#bloc-revenu').addClass('hidden');
    }

    ////////////////////////////////////////////////////////////////////
    var auditE_length = $('#auditE_value').val();
    var demandeTravauxIsCreate = $('#demandeTravauxIsCreate').val();
    var auditE_statut = $('#auditE_statut').val();
    var remboursementAuditStatutId = $('#remboursementAuditStatutId').val();

    if ('true' == demandeTravauxIsCreate) {
        // is in Create form
        $('#whitelabel_frontofficebundle_demande__demande_travaux_audit_0').prop("checked", true);
        $('#whitelabel_frontofficebundle_demande__demande_travaux_audit_1').prop("disabled", true);
    } else {
        // Is in Edit form
        if ('1' == auditE_length && '15' != auditE_statut && '20' != remboursementAuditStatutId) {
            $('#whitelabel_frontofficebundle_demande__demande_travaux_audit_0').prop("checked", true);
            $('#whitelabel_frontofficebundle_demande__demande_travaux_audit_1').prop("disabled", true);
        }
    }

    ////////////////////////////////////////////////////////////////////

    $('button[id="whitelabel_frontofficebundle_demande__valider"]').on('click', function () {
        if ($('#bloc-revenu').is(':hidden')) {

            resetBlocRevenu();

            $('#whitelabel_frontofficebundle_demande__demande_travaux_revenu1').prop('required', false);
            $('#whitelabel_frontofficebundle_demande__demande_travaux_nbPersFoyer').prop('required', false);

        }
    });

    function resetBlocRevenu() {
        $('#whitelabel_frontofficebundle_demande__demande_travaux_avisImposition').prop('value', null);
        $('#whitelabel_frontofficebundle_demande__demande_travaux_avisImpositionConjoint').prop('value', null);
        $('#whitelabel_frontofficebundle_demande__demande_travaux_nbPersFoyer').prop('value', null);
        $('#whitelabel_frontofficebundle_demande__demande_travaux_revenu1').prop('value', null);
        $('#whitelabel_frontofficebundle_demande__demande_travaux_revenu2').prop('value', null);
        $('#whitelabel_frontofficebundle_demande__demande_travaux_revenu3').prop('value', null);
    }
});
