$(document).ready(function()
{
    var elt_isDoublon = $('#box-isDoublon');
    var elt_formDisplayAuditeur = $('#box-formDisplayAuditeur');
    var prefixPatnameBundle = $("#data-js-bundle").data("prefix-pathname-bundle");

    if (true != elt_isDoublon.val()) {
        document.getElementById('box-auditeur').innerHTML = ""
            + "<input id='input-auditeur' class='form-control' readonly='readonly' name='input-auditeur' placeholder='Auditeur retenu' value='"
            + elt_formDisplayAuditeur.val()
            + "' type='text' required='required' />"
        ;
    } else {
        var type_ = '0';
        var type = parseInt(type_,10);
        if (type === 0 && typeof type == 'number') {
            $.ajax({
                url: Routing.generate(prefixPatnameBundle + '_autocomplete_listPartenaire', {type: type}),
                dataType: "json"
            }).done(function (data) {
                $.map(data, function (item) {
                    if (item.length > 0) {
                        document.getElementById('box-auditeur').innerHTML = ""
                            + "<div class='custom-select'>"
                            + "<select id='input-auditeur' class='form-control' required='required'>"
                            + "<option value='' selected='selected'>-- Choisir un auditeur --</option>"
                            + "</select>"
                            + "</div>"
                        ;

                        $.each(item, function (index, row) {
                            if ($('#whitelabel_frontofficebundle_demande__demande_auditNumerique_auditeur_id').val() != null &&
                                $('#whitelabel_frontofficebundle_demande__demande_auditNumerique_auditeur_id').val() == row.id) {
                                $('#input-auditeur')
                                    .append('<option value="' + row.id + '" selected>' + row.raison_sociale + '</option>');
                            } else {
                                $('#input-auditeur')
                                    .append('<option value="' + row.id + '">' + row.raison_sociale + '</option>');
                            }
                        });
                        $('#input-auditeur').on('click change', function () {
                            var valueAuditeur = $(this).val();
                            $('#whitelabel_frontofficebundle_demande__demande_auditNumerique_auditeur_id').prop('value', valueAuditeur);
                        });
                    } else {
                        document.getElementById('box-auditeur').innerHTML = ""
                            + "<div class='custom-select'>"
                            + "<select id='input-auditeur' class='form-control' required='required'>"
                            + "<option value='' selected='selected'>-- Choisir un auditeur --</option>"
                            + "</select>"
                            + "</div>"
                        ;
                    }
                });
            });
        }
    }
});
