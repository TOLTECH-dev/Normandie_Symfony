$(document).ready(function() {
    jQuery.ui.autocomplete.prototype._resizeMenu = function () {
        var ul = this.menu.element;
        ul.outerWidth(this.element.outerWidth());
    };

    $("#whitelabel_frontofficebundle_beneficiaire_codePostal").on('keyup', function () {
        $('#whitelabel_frontofficebundle_beneficiaire_ville').val('');
        $('#whitelabel_frontofficebundle_beneficiaire_villeId').val('');
    });

    var prefixPatnameBundle = $("#data-js-bundle").data("prefix-pathname-bundle");

    $("#whitelabel_frontofficebundle_beneficiaire_codePostal").autocomplete({
        source: function( request, response ) {
            var codePostal = parseInt(request.term,10);
            if (codePostal && typeof codePostal == 'number') {
                $.ajax({
                    url: Routing.generate(prefixPatnameBundle + '_autocomplete_listCity', {codePostal: request.term}),
                    dataType: "json",
                    //data: { term: codePostal },
                    success: function (data) {
                        $('.spinner').hide();
                        $('#match').html('').show();
                        if (data.cityList.length > 0) {
                            response(
                                $.map(data.cityList, function (object) {
                                    return {
                                        label: object.code_postal,
                                        id: object.id,
                                        nom: object.nom,
                                        code_postal: object.code_postal,
                                        code_insee: object.code_insee,
                                        cedex: object.cedex,
                                        dep_id: object.dep_id,
                                        dep_code: object.dep_code,
                                        dep_name: object.dep_name
                                    };
                                })
                            );
                        } else {
                            document.getElementById('match').innerHTML = '<br>' + "<span class='bold'>Aucun résultat</span>";
                        }
                        $('#whitelabel_frontofficebundle_beneficiaire_codePostal').removeClass("ui-autocomplete-loading");
                    }
                });
            }
        },
        messages: {
            noResults: '',
            results: function() {}
        },
        minLength: 3,
        //delay: 1500,
        focus: function( event, ui ) {
            /*
            $( "#whitelabel_frontofficebundle_beneficiaire_codePostal" ).val( ui.item.code_postal );
            $('#whitelabel_frontofficebundle_beneficiaire_ville').val(ui.item.nom);

            $("#whitelabel_frontofficebundle_beneficiaire_INSEE").val(ui.item.code_insee);
            */
            
            return false;
        },
        select: function( event, ui ) {
            if (ui.item.id == null) ui.item.id = 'non renseigné';
            if (ui.item.nom == null) ui.item.nom = 'non renseigné';
            if (ui.item.code_postal == null) ui.item.code_postal = 'non renseigné';
            if (ui.item.code_insee == null) ui.item.code_insee = 'non renseigné';
            if (ui.item.cedex == null) ui.item.cedex = 'non renseigné';

            document.getElementById('match').innerHTML = '';

            $('#whitelabel_frontofficebundle_beneficiaire_codePostal').val(ui.item.code_postal);
            $('#whitelabel_frontofficebundle_beneficiaire_ville').val(ui.item.nom);
            $('#whitelabel_frontofficebundle_beneficiaire_villeId').val(ui.item.id);

            $("#whitelabel_frontofficebundle_beneficiaire_INSEE").val(ui.item.code_insee);
            var insee_noFormat = $('#whitelabel_frontofficebundle_beneficiaire_INSEE').val();
            var insee = parseInt($('#whitelabel_frontofficebundle_beneficiaire_INSEE').val(),10);

            if (insee && typeof insee == 'number') {
                $.ajax({
                    url: Routing.generate(prefixPatnameBundle + '_autocomplete_listAddress', {insee: insee_noFormat}),
                    dataType: "json",
                    //data: { term: insee_ },
                    beforeSend: function () {
                        $("#nomRueBeneficiaire").empty();
                        $('#nomRueNotFound').val("");
                        $('#whitelabel_frontofficebundle_beneficiaire_nomRue').val("");
                    }
                }).done(function (data) {
                    $.map(data, function (item) {
                        if (item.length > 0) {
                            $('#bloc-addressDisplay').removeClass('hidden');
                            $("label[for='nomRueBeneficiaire']").addClass('required');
                            $('#nomRueBeneficiaire').attr("required", true);
                            $('#nomRueBeneficiaire').append('<option value="">-- Choisir un nom de rue --</option>');
                            $.each(item, function (index, row) {
                                $('#nomRueBeneficiaire')
                                    .append('<option value="' + row.libelle_voie + '">' + row.libelle_voie + '</option>');
                            });
                            $('#nomRueBeneficiaire').on('click change', function () {
                                var nomRue = $(this).val();
                                $('#whitelabel_frontofficebundle_beneficiaire_nomRue').val(nomRue);
                            });

                            $('#checkbox-addressDisplay').removeClass('hidden');
                            $('#whitelabel_frontofficebundle_beneficiaire_nomRueNotFound').prop('checked', false);

                            $('#bloc-noAddressDisplay').addClass('hidden');
                            $("label[for='nomRueNotFound']").removeClass('required');
                            $('#nomRueNotFound').attr("required", false);
                            document.getElementById('information-noAddressDisplay').innerHTML = "";

                            var elt_checkbox_address = $('#whitelabel_frontofficebundle_beneficiaire_nomRueNotFound');
                            elt_checkbox_address.on('click', function () {
                                $("#nomRueBeneficiaire").empty();
                                $('#nomRueNotFound').val("");
                                $('#whitelabel_frontofficebundle_beneficiaire_nomRue').val("");

                                if ($('#whitelabel_frontofficebundle_beneficiaire_nomRueNotFound').is(':checked')) {
                                    $('#bloc-addressDisplay').addClass('hidden');
                                    $("label[for='nomRueBeneficiaire']").removeClass('required');
                                    $('#nomRueBeneficiaire').attr("required", false);
                                    $("#nomRueBeneficiaire").empty();

                                    $('#bloc-noAddressDisplay').removeClass('hidden');
                                    $("label[for='nomRueNotFound']").addClass('required');
                                    $('#nomRueNotFound').attr("required", true);
                                    document.getElementById('information-noAddressDisplay').innerHTML = "";

                                    // Filling up form
                                    $('#nomRueNotFound').on('onchange blur keyup load', function () {
                                        var nomRue = $(this).val();
                                        $('#whitelabel_frontofficebundle_beneficiaire_nomRue').val(nomRue);
                                    });
                                } else {
                                    $('#bloc-addressDisplay').removeClass('hidden');
                                    $("label[for='nomRueBeneficiaire']").addClass('required');
                                    $('#nomRueBeneficiaire').attr("required", true);
                                    $('#nomRueBeneficiaire').append('<option value="">-- Choisir un nom de rue --</option>');
                                    $.each(item, function (index, row) {
                                        $('#nomRueBeneficiaire')
                                            .append('<option value="' + row.libelle_voie + '">' + row.libelle_voie + '</option>');
                                    });
                                    $('#nomRueBeneficiaire').on('click change', function () {
                                        var nomRue = $(this).val();
                                        $('#whitelabel_frontofficebundle_beneficiaire_nomRue').val(nomRue);
                                    });

                                    $('#bloc-noAddressDisplay').addClass('hidden');
                                    $("label[for='nomRueNotFound']").removeClass('required');
                                    $('#nomRueNotFound').attr("required", false);
                                    document.getElementById('information-noAddressDisplay').innerHTML = "";
                                }
                            });
                        } else {
                            $('#bloc-addressDisplay').addClass('hidden');
                            $("label[for='nomRueBeneficiaire']").removeClass('required');
                            $('#nomRueBeneficiaire').attr("required", false);
                            $("#nomRueBeneficiaire").empty();

                            $('#checkbox-addressDisplay').addClass('hidden');
                            $('#whitelabel_frontofficebundle_beneficiaire_nomRueNotFound').prop('checked', false);

                            $('#bloc-noAddressDisplay').removeClass('hidden');
                            $("label[for='nomRueNotFound']").addClass('required');
                            $('#nomRueNotFound').attr("required", true);
                            document.getElementById('information-noAddressDisplay').innerHTML =
                                "<p class='col-xs-12 col-sm-12 col-md-4 col-lg-4'></p>"
                                + "<p class='col-xs-12 col-sm-12 col-md-8 col-lg-8 bold' style='color:#d9534f;'>"
                                + "<i class='glyphicon glyphicon-alert'></i>&nbsp;"
                                + "Adresse introuvable"
                                + "</p>";

                            // Filling up form
                            $('#nomRueNotFound').on('onchange blur keyup load', function () {
                                var nomRue = $(this).val();
                                $('#whitelabel_frontofficebundle_beneficiaire_nomRue').val(nomRue);
                            });
                        }
                    });
                });
            } else {
                $('#bloc-addressDisplay').addClass('hidden');
                $("label[for='nomRueBeneficiaire']").removeClass('required');
                $('#nomRueBeneficiaire').attr("required", false);
                $("#nomRueBeneficiaire").empty();

                $('#checkbox-addressDisplay').addClass('hidden');
                $('#whitelabel_frontofficebundle_beneficiaire_nomRueNotFound').prop('checked', false);

                $('#bloc-noAddressDisplay').addClass('hidden');
                $("label[for='nomRueNotFound']").removeClass('required');
                $('#nomRueNotFound').attr("required", false);
                document.getElementById('information-noAddressDisplay').innerHTML =
                    "<p class='col-xs-12 col-sm-12 col-md-4 col-lg-4'></p>"
                    + "<p class='col-xs-12 col-sm-12 col-md-8 col-lg-8 bold' style='color:#d9534f;'>"
                    + "<i class='glyphicon glyphicon-alert'></i>&nbsp;"
                    + "Adresse introuvable"
                    + "</p>";
            }
        }
    }).autocomplete( "instance" )._renderItem = function( ul, item ) {
        return $( "<li>" )
            .append( "<div>" + item.label + " - " + item.nom + "</div>" )
            .appendTo( ul );
    };
});
