$(document).ready(function() {

    /* *****************************************************************
    ********************************************************************
                        MANAGE CHEQUE REASONS
    ********************************************************************
    *******************************************************************/
    var elt_chequeReasonAutre = $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_chequeReason input:last');
    var elt_isChequeConforme = $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_isChequeConforme');
    var value_isChequeConforme = elt_isChequeConforme.val();

    if (value_isChequeConforme == '0 | oui' || value_isChequeConforme == '') {
        hideChequeCase();
    } else {
        $('#bloc-chequeCase').removeClass('hidden');

        if (elt_chequeReasonAutre.prop('checked') == true) {
            $('#bloc-chequeCaseAutre').removeClass('hidden');
        } else {
            $('#bloc-chequeCaseAutre').addClass('hidden');
        }
    }

    elt_isChequeConforme.on('change', function () {
        if ($(this).find(':selected').val() == '0 | oui' || $(this).find(':selected').val() == '') {
            hideChequeCase();
        } else {
            $('#bloc-chequeCase').removeClass('hidden');
        }
    });

    elt_chequeReasonAutre.on('click', function () {
        if (elt_chequeReasonAutre.prop('checked') == true) {
            $('#bloc-chequeCaseAutre').removeClass('hidden');
        } else {
            $('#bloc-chequeCaseAutre').addClass('hidden');
            $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_chequeReasonAutre').val('');
        }
    });

    function hideChequeCase() {
        $('#bloc-chequeCase').addClass('hidden');
        var elt_chequeReason = document.querySelectorAll('.chequeReason input');
        $.each(elt_chequeReason, function () {
            $(this).prop("checked", false);
        });

        $('#bloc-chequeCaseAutre').addClass('hidden');
        $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_chequeReasonAutre').val('');
    }

    /* *****************************************************************
    ********************************************************************
                        MANAGE FACTURE REASONS
    ********************************************************************
    *******************************************************************/

    var elt_factureReasonAutre = $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_factureReason input:last');
    var elt_isFactureConforme = $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_isFactureConforme');
    var value_isFactureConforme = elt_isFactureConforme.val();

    if (value_isFactureConforme == '0 | oui' || value_isFactureConforme == '') {
        hideFactureCase();
    } else {
        $('#bloc-factureCase').removeClass('hidden');

        if (elt_factureReasonAutre.prop('checked') == true) {
            $('#bloc-factureCaseAutre').removeClass('hidden');
        } else {
            $('#bloc-factureCaseAutre').addClass('hidden');
        }
    }

    elt_isFactureConforme.on('change', function () {
        if ($(this).find(':selected').val() == '0 | oui' || $(this).find(':selected').val() == '') {
            hideFactureCase();
        } else {
            $('#bloc-factureCase').removeClass('hidden');
        }
    });

    elt_factureReasonAutre.on('click', function () {
        if (elt_factureReasonAutre.prop('checked') == true) {
            $('#bloc-factureCaseAutre').removeClass('hidden');
        } else {
            $('#bloc-factureCaseAutre').addClass('hidden');
            $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_factureReasonAutre').val('');
        }
    });

    function hideFactureCase() {
        $('#bloc-factureCase').addClass('hidden');
        var elt_factureReason = document.querySelectorAll('.factureReason input');
        $.each(elt_factureReason, function () {
            $(this).prop("checked", false);
        });

        $('#bloc-factureCaseAutre').addClass('hidden');
        $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_factureReasonAutre').val('');
    }

    /* *****************************************************************
    ********************************************************************
                        MANAGE RIB REASONS
    ********************************************************************
    *******************************************************************/
    var elt_ribReasonAutre = $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_ribReason input:last');
    var elt_isRibConforme = $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_isRibConforme');
    var value_isRibConforme = elt_isRibConforme.val();

    if (value_isRibConforme == '0 | oui' || value_isRibConforme == '') {
        hideRibCase();
    } else {
        $('#bloc-ribCase').removeClass('hidden');

        if (elt_ribReasonAutre.prop('checked') == true) {
            $('#bloc-ribCaseAutre').removeClass('hidden');
        } else {
            $('#bloc-ribCaseAutre').addClass('hidden');
        }
    }

    elt_isRibConforme.on('change', function () {
        if ($(this).find(':selected').val() == '0 | oui' || $(this).find(':selected').val() == '') {
            hideRibCase();
        } else {
            $('#bloc-ribCase').removeClass('hidden');
        }
    });

    elt_ribReasonAutre.on('click', function () {
        if (elt_ribReasonAutre.prop('checked') == true) {
            $('#bloc-ribCaseAutre').removeClass('hidden');
        } else {
            $('#bloc-ribCaseAutre').addClass('hidden');
            $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_ribReasonAutre').val('');
        }
    });

    function hideRibCase() {
        $('#bloc-ribCase').addClass('hidden');
        var elt_ribReason = document.querySelectorAll('.ribReason input');
        $.each(elt_ribReason, function () {
            $(this).prop("checked", false);
        });

        $('#bloc-ribCaseAutre').addClass('hidden');
        $('#whitelabel_backofficebundle_remboursement__remboursement_auditNumerique_instruction_ribReasonAutre').val('');
    }
    
});
