$(document).ready(function() {
    var typeBeneficiaire = $('#box-typeBeneficiaire').val();

    if ('0 | particulier' == typeBeneficiaire) {
        $('#bloc-KBIS').addClass('hidden');
        $('#bloc-KBIScase').addClass('hidden');
        $('#bloc-KBIScaseAutre').addClass('hidden');
    } else if ('1 | sci' == typeBeneficiaire) {
        $('#bloc-KBIS').removeClass('hidden');
        $('#bloc-KBIScase').removeClass('hidden');
        $('#bloc-KBIScaseAutre').removeClass('hidden');

        $("label[for='whitelabel_backofficebundle_instruction__instruction_auditEnergie_KBISconformite']").addClass('required');
        $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_KBISconformite').prop('required', true);
    }

    ////////////////////////////////////////////////////////////////////

    var elt_JPreason_autre = $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_JPreason input:last');
    var elt_JPconformite = $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_JPconformite');
    var value_JPconformite = $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_JPconformite').val();

    if (value_JPconformite == '0 | oui') {
        $('#bloc-JPtype').removeClass('hidden');
        $("label[for='whitelabel_backofficebundle_instruction__instruction_auditEnergie_JPtype']").addClass('required');
        $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_JPtype').attr("required", true);
    } else {
        $('#bloc-JPtype').addClass('hidden');
        $("label[for='whitelabel_backofficebundle_instruction__instruction_auditEnergie_JPtype']").removeClass('required');
        $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_JPtype').attr("required", false);
    }

    if (value_JPconformite == '0 | oui' || value_JPconformite == '2 | indetermine' || value_JPconformite == '') {
        hideJPcase();
    } else {
        $('#bloc-JPcase').removeClass('hidden');

        if (elt_JPreason_autre.prop('checked') == true) {
            $('#bloc-JPcaseAutre').removeClass('hidden');
        } else {
            $('#bloc-JPcaseAutre').addClass('hidden');
        }
    }

    elt_JPconformite.on('change', function () {
        if ($(this).find(':selected').val() == '0 | oui') {
            $('#bloc-JPtype').removeClass('hidden');
            $("label[for='whitelabel_backofficebundle_instruction__instruction_auditEnergie_JPtype']").addClass('required');
            $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_JPtype').attr("required", true);
        } else {
            $('#bloc-JPtype').addClass('hidden');
            $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_JPtype').val('');
            $("label[for='whitelabel_backofficebundle_instruction__instruction_auditEnergie_JPtype']").removeClass('required');
            $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_JPtype').attr("required", false);
        }

        if ($(this).find(':selected').val() == '0 | oui' || $(this).find(':selected').val() == '2 | indetermine' || $(this).find(':selected').val() == '') {
            hideJPcase();
        } else {
            $('#bloc-JPcase').removeClass('hidden');
        }
    });

    elt_JPreason_autre.on('click', function () {
        if (elt_JPreason_autre.prop('checked') == true) {
            $('#bloc-JPcaseAutre').removeClass('hidden');
        } else {
            $('#bloc-JPcaseAutre').addClass('hidden');
            $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_JPreasonAutre').val('');
        }
    });

    function hideJPcase() {
        $('#bloc-JPcase').addClass('hidden');
        var elt_JPreason = document.querySelectorAll('.JPreason input');
        $.each(elt_JPreason, function () {
            $(this).prop("checked", false);
        });

        $('#bloc-JPcaseAutre').addClass('hidden');
        $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_JPreasonAutre').val('');
    }

    ////////////////////////////////////////////////////////////////////

    var elt_KBISreason_autre = $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_KBISreason input:last');
    var elt_KBISconformite = $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_KBISconformite');
    var value_KBISconformite = $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_KBISconformite').val();

    if (value_KBISconformite == '0 | oui' || value_KBISconformite == '2 | indetermine' || value_KBISconformite == '') {
        hideKBIScase();
    } else {
        $('#bloc-KBIScase').removeClass('hidden');

        if (elt_KBISreason_autre.prop('checked') == true) {
            $('#bloc-KBIScaseAutre').removeClass('hidden');
        } else {
            $('#bloc-KBIScaseAutre').addClass('hidden');
        }
    }

    elt_KBISconformite.on('change', function () {
        if ($(this).find(':selected').val() == '0 | oui' || $(this).find(':selected').val() == '2 | indetermine' || $(this).find(':selected').val() == '') {
            hideKBIScase();
        } else {
            $('#bloc-KBIScase').removeClass('hidden');
        }
    });

    elt_KBISreason_autre.on('click', function () {
        if (elt_KBISreason_autre.prop('checked') == true) {
            $('#bloc-KBIScaseAutre').removeClass('hidden');
        } else {
            $('#bloc-KBIScaseAutre').addClass('hidden');
            $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_KBISreasonAutre').val('');
        }
    });

    function hideKBIScase() {
        $('#bloc-KBIScase').addClass('hidden');
        var elt_KBISreason = document.querySelectorAll('.KBISreason input');
        $.each(elt_KBISreason, function () {
            $(this).prop("checked", false);
        });

        $('#bloc-KBIScaseAutre').addClass('hidden');
        $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_KBISreasonAutre').val('');
    }


    ////////////////////////////////////////////////////////////////////

    var elt_AIreason_autre = $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_AIreason input:last');
    var elt_AIconformite = $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_AIconformite');
    var value_AIconformite = $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_AIconformite').val();

    if (value_AIconformite == '0 | oui' || value_AIconformite == '2 | indetermine' || value_AIconformite == '') {
        hideAIcase();
    } else {
        $('#bloc-AIcase').removeClass('hidden');

        if (elt_AIreason_autre.prop('checked') == true) {
            $('#bloc-AIcaseAutre').removeClass('hidden');
        } else {
            $('#bloc-AIcaseAutre').addClass('hidden');
        }
    }

    elt_AIconformite.on('change', function () {
        if ($(this).find(':selected').val() == '0 | oui' || $(this).find(':selected').val() == '2 | indetermine' || $(this).find(':selected').val() == '') {
            hideAIcase();
        } else {
            $('#bloc-AIcase').removeClass('hidden');
        }
    });

    elt_AIreason_autre.on('click', function () {
        if (elt_AIreason_autre.prop('checked') == true) {
            $('#bloc-AIcaseAutre').removeClass('hidden');
        } else {
            $('#bloc-AIcaseAutre').addClass('hidden');
            $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_AIreasonAutre').val('');
        }
    });

    function hideAIcase() {
        $('#bloc-AIcase').addClass('hidden');
        var elt_AIreason = document.querySelectorAll('.AIreason input');
        $.each(elt_AIreason, function () {
            $(this).prop("checked", false);
        });

        $('#bloc-AIcaseAutre').addClass('hidden');
        $('#whitelabel_backofficebundle_instruction__instruction_auditEnergie_AIreasonAutre').val('');
    }
});
