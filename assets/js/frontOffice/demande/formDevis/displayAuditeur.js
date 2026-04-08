$(document).ready(function() {
    var elt_demandeTravauxAudit = $('#box-demandeTravauxAudit');
    var elt_demandeAuditE = $('#box-demandeAuditE');
    var elt_demandeAuditeur = $('#box-demandeAuditeur');
    var elt_demandeAuditeurSlug = $('#box-demandeAuditeurslug');

    var prefixPatnameBundle = $("#data-js-bundle").data("prefix-pathname-bundle");

    if (true == elt_demandeTravauxAudit.val() && '1' == elt_demandeAuditE.val()) {
        $('#whitelabel_frontofficebundle_demande_travaux_devis_auditeur_id').val(elt_demandeAuditeur.val());

        $("label[for='devisAuditeur']").addClass('required');
        $('#devisAuditeur').attr("required", true);

        document.getElementById('box-devisAuditeur').innerHTML = ""
            + "<input id='devisAuditeur' class='form-control' readonly='readonly' name='devisAuditeur' placeholder='Auditeur retenu' value='"
            + elt_demandeAuditeurSlug.val()
            + "' type='text' required='required' />";
    } else if (true == elt_demandeTravauxAudit.val() && '0' == elt_demandeAuditE.val()) {
        var type_ = '0';
        var type = parseInt(type_,10);
        if (type === 0 && typeof type == 'number') {
            $.ajax({
                url: Routing.generate(prefixPatnameBundle + '_autocomplete_listPartenaire', {type: type}),
                dataType: "json"
            }).done(function (data) {
                $.map(data, function (item) {
                    if (item.length > 0) {
                        $("label[for='devisAuditeur']").addClass('required');

                        document.getElementById('box-devisAuditeur').innerHTML = ""
                            + "<div class='custom-select'>"
                            + "<select id='devisAuditeur' class='form-control' required='required'>"
                            + "<option value='' selected='selected'>-- Choisir un auditeur --</option>"
                            + "</select>"
                            + "</div>";
                        $('#devisAuditeur').attr("required", true);
                        
                        $.each(item, function (index, row) {
                            if ($('#whitelabel_frontofficebundle_demande_travaux_devis_auditeur_id').val() != null &&
                                $('#whitelabel_frontofficebundle_demande_travaux_devis_auditeur_id').val() == row.id) {
                                $('#devisAuditeur')
                                    .append('<option value="' + row.id + '" selected>' + row.raison_sociale + '</option>');
                            } else {
                                $('#devisAuditeur')
                                    .append('<option value="' + row.id + '">' + row.raison_sociale + '</option>');
                            }
                        });
                        $('#devisAuditeur').on('click change', function () {
                            var idAuditeur = $(this).val();
                            $('#whitelabel_frontofficebundle_demande_travaux_devis_auditeur_id').attr('value', idAuditeur);
                            $('#button_popupValidation').prop('disabled', false);
                        });
                    } else {
                        $('#button_popupValidation').prop('disabled', true);

                        document.getElementById('box-devisAuditeur').innerHTML = ""
                            + "<div class='custom-select'>"
                            + "<select id='devisAuditeur' class='form-control' required='required'>"
                            + "<option value='' selected='selected'>-- Choisir un auditeur --</option>"
                            + "</select>"
                            + "</div>";
                    }
                });
            });
        }
    } else if (false == elt_demandeTravauxAudit.val()) {
        $("label[for='devisAuditeur']").removeClass('required');
        $('#devisAuditeur').attr("required", false);
        // CAS dépôt de l’audit énergétique national
        $("label[for='whitelabel_frontofficebundle_demande_travaux_devis_audit']").addClass('required');

        $('#bloc-devisAuditeur').addClass('hidden');
    }

    ////////////////////////////////////////////////////////////////////

    if (true == elt_demandeTravauxAudit.val()) {
        document.getElementById('box-labelAudit').innerHTML = 'Audit Région Normandie';
    } else if (false == elt_demandeTravauxAudit.val()) {
        document.getElementById('box-labelAudit').innerHTML = 'Audit énergétique national';
    }else {
        document.getElementById('box-labelAudit').innerHTML = 'Audit Région Normandie ou Audit énergétique national';
    }
});
