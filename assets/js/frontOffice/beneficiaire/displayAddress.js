$(document).ready(function() {
    var insee_noFormat = $('#whitelabel_frontofficebundle_beneficiaire_INSEE').val();
    var insee = parseInt($('#whitelabel_frontofficebundle_beneficiaire_INSEE').val(),10);

    var prefixPatnameBundle = $("#data-js-bundle").data("prefix-pathname-bundle");

    if (insee && typeof insee == 'number') {
        $.ajax({
            url: Routing.generate(prefixPatnameBundle + '_autocomplete_listAddress', {insee: insee_noFormat}),
            dataType: "json",
            beforeSend: function () {
            }
        }).done(function (data) {
            $.map(data, function (item) {
                if (item.length > 0) {
                    if ($('#whitelabel_frontofficebundle_beneficiaire_nomRueNotFound').is(':checked')) {
                        $('#bloc-addressDisplay').addClass('hidden');
                        $("label[for='nomRueBeneficiaire']").removeClass('required');
                        $('#nomRueBeneficiaire').attr("required", false);
                        $("#nomRueBeneficiaire").empty();

                        $('#bloc-noAddressDisplay').removeClass('hidden');
                        $("label[for='nomRueNotFound']").addClass('required');
                        $('#nomRueNotFound').attr("required", true);
                        document.getElementById('information-noAddressDisplay').innerHTML = "";

                        // Editing form => filling up data
                        var nomRue_ = $('#whitelabel_frontofficebundle_beneficiaire_nomRue').val();
                        $('#nomRueNotFound').val(nomRue_);

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
                            // Editing form => filling up data
                            if ($('#whitelabel_frontofficebundle_beneficiaire_nomRue').val() == row.libelle_voie) {
                                $('#nomRueBeneficiaire')
                                    .append('<option value="' + row.libelle_voie + '" selected>' + row.libelle_voie + '</option>');
                            } else {
                                $('#nomRueBeneficiaire')
                                    .append('<option value="' + row.libelle_voie + '">' + row.libelle_voie + '</option>');
                            }
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

                    $('#checkbox-addressDisplay').removeClass('hidden');



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

                            // Editing form => filling up data
                            var nomRue_ = $('#whitelabel_frontofficebundle_beneficiaire_nomRue').val();
                            $('#nomRueNotFound').val(nomRue_);

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
                                // Editing form => filling up data
                                if ($('#whitelabel_frontofficebundle_beneficiaire_nomRue').val() == row.libelle_voie) {
                                    $('#nomRueBeneficiaire')
                                        .append('<option value="' + row.libelle_voie + '" selected>' + row.libelle_voie + '</option>');
                                } else {
                                    $('#nomRueBeneficiaire')
                                        .append('<option value="' + row.libelle_voie + '">' + row.libelle_voie + '</option>');
                                }
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
                        "<p class='col-xs-12 col-sm-12 col-md-3 col-lg-3'></p>"
                        + "<p class='col-xs-12 col-sm-12 col-md-9 col-lg-9 bold' style='color:#d9534f;'>"
                        + "<i class='glyphicon glyphicon-alert'></i>&nbsp;"
                        + "Adresse introuvable"
                        + "</p>";

                    // Editing form => filling up data
                    var nomRue_ = $('#whitelabel_frontofficebundle_beneficiaire_nomRue').val();
                    $('#nomRueNotFound').val(nomRue_);

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
            "<p class='col-xs-12 col-sm-12 col-md-3 col-lg-3'></p>"
            + "<p class='col-xs-12 col-sm-12 col-md-9 col-lg-9 bold' style='color:#d9534f;'>"
            + "<i class='glyphicon glyphicon-alert'></i>&nbsp;"
            + "Erreur syst&egrave;me"
            + "</p>";
    }
});
