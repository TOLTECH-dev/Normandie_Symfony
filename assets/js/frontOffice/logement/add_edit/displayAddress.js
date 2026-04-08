$(document).ready(function() {
    var data_form = new Array();
    data_form['codePostal'] = $('#whitelabel_frontofficebundle_logement_codePostal').val();
    data_form['ville'] = $('#whitelabel_frontofficebundle_logement_ville').val();
    data_form['villeId'] = $('#whitelabel_frontofficebundle_logement_villeId').val();
    data_form['numeroRue'] = $('#whitelabel_frontofficebundle_logement_numeroRue').val();
    data_form['complementRue'] = $('#whitelabel_frontofficebundle_logement_complementRue').val();
    data_form['INSEE'] = $('#whitelabel_frontofficebundle_logement_INSEE').val();
    data_form['adresse'] = $('#whitelabel_frontofficebundle_logement_adresse').val();
    data_form['complement1'] = $('#whitelabel_frontofficebundle_logement_complement1').val();
    data_form['complement2'] = $('#whitelabel_frontofficebundle_logement_complement2').val();

    var data_beneficiaire = new Array();
    data_beneficiaire['codePostal'] = $("#beneficiaire_codePostal").val();
    data_beneficiaire['ville'] = $("#beneficiaire_ville").val();
    data_beneficiaire['villeId'] = $("#beneficiaire_villeId").val();
    data_beneficiaire['numeroRue'] = $("#beneficiaire_numeroRue").val();
    data_beneficiaire['complementRue'] = $("#beneficiaire_complementNumeroRue").val();
    data_beneficiaire['INSEE'] = $("#beneficiaire_INSEE").val();
    data_beneficiaire['adresse'] = $("#beneficiaire_nomRue").val();
    data_beneficiaire['complement1'] = $("#beneficiaire_complement1").val();
    data_beneficiaire['complement2'] = $("#beneficiaire_complement2").val();

    var prefixPatnameBundle = $("#data-js-bundle").data("prefix-pathname-bundle");

    // Loading page
    isDifferent(data_form, data_beneficiaire, true);

    // Click on isDifferent
    $('#whitelabel_frontofficebundle_logement_isDifferent').on('click', function () {
        isDifferent(data_form, data_beneficiaire, false);
    });

    // Update form data depends on checkbox isDifferent
    function isDifferent(data_form, data_beneficiaire, flag) {
        var codePostal = $("#whitelabel_frontofficebundle_logement_codePostal");
        var ville = $("#whitelabel_frontofficebundle_logement_ville");
        var numeroRue = $("#whitelabel_frontofficebundle_logement_numeroRue");
        var complementRue = $("#whitelabel_frontofficebundle_logement_complementRue");
        var INSEE = $("#whitelabel_frontofficebundle_logement_INSEE");
        var adresse = $("#whitelabel_frontofficebundle_logement_adresse");
        var complement1 = $("#whitelabel_frontofficebundle_logement_complement1");
        var complement2 = $("#whitelabel_frontofficebundle_logement_complement2");

        if (true == flag) {
            if ($('#whitelabel_frontofficebundle_logement_isDifferent').is(':checked')) {
                codePostal.val(data_form['codePostal']);
                codePostal.attr('readonly', null);

                ville.val(data_form['ville']);
                ville.attr('readonly', true);

                numeroRue.val(data_form['numeroRue']);
                numeroRue.attr('readonly', null);

                complementRue.val(data_form['complementRue']);
                complementRue.attr('readonly', null);

                INSEE.val(data_form['INSEE']);
                INSEE.attr('readonly', true);

                adresse.val(data_form['adresse']);
                adresse.attr('readonly', true);

                complement1.val(data_form['complement1']);
                complement1.attr('readonly', null);

                complement2.val(data_form['complement2']);
                complement2.attr('readonly', null);

                $('#bloc-complementRueForm').addClass('hidden');
                $('#bloc-complementRueInputDisplay').addClass('hidden');
                $('#bloc-complementRueDisplay').removeClass('hidden');

                $('#complementRue option[value="' + data_form["complementRue"] + '"]').attr('selected', true);
                $('#complementRue').on('click', function () {
                    var complementRue = $(this).val();
                    $('#whitelabel_frontofficebundle_logement_complementRue').val(complementRue);
                });

                $('#bloc-noAddressDisplay').removeClass('hidden');
                $("label[for='nomRueNotFound']").addClass('required');
                $('#nomRueNotFound').attr("required", true);
                document.getElementById('information-noAddressDisplay').innerHTML = "";

                $('#nomRueNotFound').attr('readonly', null);
                $('#nomRueNotFound').val(data_form['adresse']);

                findAddress();
            } else {
                codePostal.val(data_form['codePostal']);
                codePostal.attr('readonly', true);

                ville.val(data_form['ville']);
                ville.attr('readonly', true);

                numeroRue.val(data_form['numeroRue']);
                numeroRue.attr('readonly', true);

                complementRue.val(data_form['complementRue']);
                complementRue.attr('readonly', true);

                INSEE.val(data_form['INSEE']);
                INSEE.attr('readonly', true);

                adresse.val(data_form['adresse']);
                adresse.attr('readonly', true);

                complement1.val(data_form['complement1']);
                complement1.attr('readonly', true);

                complement2.val(data_form['complement2']);
                complement2.attr('readonly', true);

                $('#bloc-complementRueForm').addClass('hidden');
                $('#bloc-complementRueInputDisplay').removeClass('hidden');
                $('#bloc-complementRueDisplay').addClass('hidden');

                var complementRueInput = (data_form['complementRue']) ? data_form['complementRue'].split(" | ") : [];
                if (complementRueInput.length>1) {
                    $('#complementRueInput').val(complementRueInput[1].toUpperCase());
                } else {
                    $('#complementRueInput').val('');
                    $('#complementRueInput').attr('placeholder', 'Complément numéro de rue');
                }
                $('#complementRueInput').attr('readonly', true);

                $('#bloc-noAddressDisplay').removeClass('hidden');
                $("label[for='nomRueNotFound']").addClass('required');
                $('#nomRueNotFound').attr("required", true);
                document.getElementById('information-noAddressDisplay').innerHTML = "";

                // Editing form => filling up data
                var nomRue_ = $('#whitelabel_frontofficebundle_logement_adresse').val();
                $('#nomRueNotFound').val(nomRue_);
                $('#nomRueNotFound').attr('readonly', true);

                $('#bloc-addressDisplay').addClass('hidden');
                $('#checkbox-addressDisplay').addClass('hidden');
            }
        } else {
            if ($('#whitelabel_frontofficebundle_logement_isDifferent').is(':checked')) {
                codePostal.val('');
                codePostal.attr('readonly', null);

                ville.val('');
                ville.attr('readonly', true);

                numeroRue.val('');
                numeroRue.attr('readonly', null);

                complementRue.val('');
                complementRue.attr('readonly', null);

                INSEE.val('');
                INSEE.attr('readonly', true);

                adresse.val('');
                adresse.attr('readonly', true);

                complement1.val('');
                complement1.attr('readonly', null);

                complement2.val('');
                complement2.attr('readonly', null);

                $('#bloc-addressForm').addClass('hidden');

                $('#bloc-complementRueForm').addClass('hidden');
                $('#bloc-complementRueInputDisplay').addClass('hidden');
                $('#bloc-complementRueDisplay').removeClass('hidden');

                $('#complementRue').val('');
                $('#complementRue').on('click', function () {
                    var complementRue = $(this).val();
                    $('#whitelabel_frontofficebundle_logement_complementRue').val(complementRue);
                });

                $('#bloc-noAddressDisplay').addClass('hidden');
                $("label[for='nomRueNotFound']").removeClass('required');
                $('#nomRueNotFound').attr("required", false);
                document.getElementById('information-noAddressDisplay').innerHTML = "";

                $('#nomRueNotFound').attr('readonly', null);
                $('#nomRueNotFound').val('');

                findAddress();
            } else {
                codePostal.val(data_beneficiaire['codePostal']);
                codePostal.attr('readonly', true);

                ville.val(data_beneficiaire['ville']);
                ville.attr('readonly', true);

                villeId.val(data_beneficiaire['villeId']);

                numeroRue.val(data_beneficiaire['numeroRue']);
                numeroRue.attr('readonly', true);

                complementRue.val(data_beneficiaire['complementRue']);
                complementRue.attr('readonly', true);

                INSEE.val(data_beneficiaire['INSEE']);
                INSEE.attr('readonly', true);

                adresse.val(data_beneficiaire['adresse']);
                adresse.attr('readonly', true);

                complement1.val(data_beneficiaire['complement1']);
                complement1.attr('readonly', true);

                complement2.val(data_beneficiaire['complement2']);
                complement2.attr('readonly', true);

                $('#bloc-complementRueForm').addClass('hidden');
                $('#bloc-complementRueInputDisplay').removeClass('hidden');
                $('#bloc-complementRueDisplay').addClass('hidden');

                var complementRueInput = (data_beneficiaire['complementRue']) ? data_beneficiaire['complementRue'].split(" | ") : [];
                if (complementRueInput.length>1) {
                    $('#complementRueInput').val(complementRueInput[1].toUpperCase());
                } else {
                    $('#complementRueInput').val('');
                }
                $('#complementRueInput').attr('readonly', true);

                $('#bloc-noAddressDisplay').removeClass('hidden');
                $("label[for='nomRueNotFound']").addClass('required');
                $('#nomRueNotFound').attr("required", true);
                document.getElementById('information-noAddressDisplay').innerHTML = "";

                $('#nomRueNotFound').attr('readonly', true);
                $('#nomRueNotFound').val(data_beneficiaire['adresse']);

                $('#bloc-addressDisplay').addClass('hidden');
                $('#checkbox-addressDisplay').addClass('hidden');
            }
        }
    }


    function findAddress() {
        var insee_noFormat = $('#whitelabel_frontofficebundle_logement_INSEE').val();
        var insee = parseInt($('#whitelabel_frontofficebundle_logement_INSEE').val(),10);

        if (insee && typeof insee == 'number') {
            $.ajax({
                url: Routing.generate(prefixPatnameBundle + '_autocomplete_listAddress', {insee: insee_noFormat}),
                dataType: "json"
            }).done(function (data) {
                $.map(data, function (item) {
                    if (item.length > 0) {
                        if ($('#whitelabel_frontofficebundle_logement_nomRueNotFound').is(':checked')) {
                            $('#bloc-addressDisplay').addClass('hidden');
                            $("label[for='nomRue']").removeClass('required');
                            $('#nomRue').attr("required", false);
                            $("#nomRue").empty();

                            $('#bloc-noAddressDisplay').removeClass('hidden');
                            $("label[for='nomRueNotFound']").addClass('required');
                            $('#nomRueNotFound').attr("required", true);
                            document.getElementById('information-noAddressDisplay').innerHTML = "";

                            // Editing form => filling up data
                            var nomRue_ = $('#whitelabel_frontofficebundle_logement_adresse').val();
                            $('#nomRueNotFound').val(nomRue_);

                            // Filling up form
                            $('#nomRueNotFound').on('onchange blur keyup load', function () {
                                var nomRue = $(this).val();
                                $('#whitelabel_frontofficebundle_logement_adresse').val(nomRue);
                            });
                        } else {
                            $('#bloc-addressDisplay').removeClass('hidden');
                            $("label[for='nomRue']").addClass('required');
                            $('#nomRue').attr("required", true);
                            $('#nomRue').append('<option value="">-- Choisir un nom de rue --</option>');
                            $.each(item, function (index, row) {
                                // Editing form => filling up data
                                if ($('#whitelabel_frontofficebundle_logement_adresse').val() == row.libelle_voie) {
                                    $('#nomRue')
                                        .append('<option value="' + row.libelle_voie + '" selected>' + row.libelle_voie + '</option>');
                                } else {
                                    $('#nomRue')
                                        .append('<option value="' + row.libelle_voie + '">' + row.libelle_voie + '</option>');
                                }
                            });
                            $('#nomRue').on('click change', function () {
                                var nomRue = $(this).val();
                                $('#whitelabel_frontofficebundle_logement_adresse').val(nomRue);
                            });

                            $('#bloc-noAddressDisplay').addClass('hidden');
                            $("label[for='nomRueNotFound']").removeClass('required');
                            $('#nomRueNotFound').attr("required", false);
                            document.getElementById('information-noAddressDisplay').innerHTML = "";
                        }

                        $('#checkbox-addressDisplay').removeClass('hidden');



                        var elt_checkbox_address = $('#whitelabel_frontofficebundle_logement_nomRueNotFound');
                        elt_checkbox_address.on('click', function () {
                            $("#nomRue").empty();
                            $('#nomRueNotFound').val("");
                            $('#whitelabel_frontofficebundle_logement_adresse').val("");

                            if ($('#whitelabel_frontofficebundle_logement_nomRueNotFound').is(':checked')) {
                                $('#bloc-addressDisplay').addClass('hidden');
                                $("label[for='nomRue']").removeClass('required');
                                $('#nomRue').attr("required", false);
                                $("#nomRue").empty();

                                $('#bloc-noAddressDisplay').removeClass('hidden');
                                $("label[for='nomRueNotFound']").addClass('required');
                                $('#nomRueNotFound').attr("required", true);
                                document.getElementById('information-noAddressDisplay').innerHTML = "";

                                // Editing form => filling up data
                                var nomRue_ = $('#whitelabel_frontofficebundle_logement_adresse').val();
                                $('#nomRueNotFound').val(nomRue_);

                                // Filling up form
                                $('#nomRueNotFound').on('onchange blur keyup load', function () {
                                    var nomRue = $(this).val();
                                    $('#whitelabel_frontofficebundle_logement_adresse').val(nomRue);
                                });
                            } else {
                                $('#bloc-addressDisplay').removeClass('hidden');
                                $("label[for='nomRue']").addClass('required');
                                $('#nomRue').attr("required", true);
                                $('#nomRue').append('<option value="">-- Choisir un nom de rue --</option>');
                                $.each(item, function (index, row) {
                                    // Editing form => filling up data
                                    if ($('#whitelabel_frontofficebundle_logement_adresse').val() == row.libelle_voie) {
                                        $('#nomRue')
                                            .append('<option value="' + row.libelle_voie + '" selected>' + row.libelle_voie + '</option>');
                                    } else {
                                        $('#nomRue')
                                            .append('<option value="' + row.libelle_voie + '">' + row.libelle_voie + '</option>');
                                    }
                                });
                                $('#nomRue').on('click change', function () {
                                    var nomRue = $(this).val();
                                    $('#whitelabel_frontofficebundle_logement_adresse').val(nomRue);
                                });

                                $('#bloc-noAddressDisplay').addClass('hidden');
                                $("label[for='nomRueNotFound']").removeClass('required');
                                $('#nomRueNotFound').attr("required", false);
                                document.getElementById('information-noAddressDisplay').innerHTML = "";
                            }
                        });
                    } else {
                        $('#bloc-addressDisplay').addClass('hidden');
                        $("label[for='nomRue']").removeClass('required');
                        $('#nomRue').attr("required", false);
                        $("#nomRue").empty();

                        $('#checkbox-addressDisplay').addClass('hidden');
                        $('#whitelabel_frontofficebundle_logement_nomRueNotFound').prop('checked', false);

                        $('#bloc-noAddressDisplay').removeClass('hidden');
                        $("label[for='nomRueNotFound']").addClass('required');
                        $('#nomRueNotFound').attr("required", true);
                        document.getElementById('information-noAddressDisplay').innerHTML =
                            "<p class='col-xs-12 col-sm-12 col-md-4 col-lg-4'></p>"
                            + "<p class='col-xs-12 col-sm-12 col-md-8 col-lg-8 bold' style='color:#d9534f;'>"
                            + "<i class='glyphicon glyphicon-alert'></i>&nbsp;"
                            + "Adresse introuvable"
                            + "</p>";

                        // Editing form => filling up data
                        var nomRue_ = $('#whitelabel_frontofficebundle_logement_adresse').val();
                        $('#nomRueNotFound').val(nomRue_);

                        // Filling up form
                        $('#nomRueNotFound').on('onchange blur keyup load', function () {
                            var nomRue = $(this).val();
                            $('#whitelabel_frontofficebundle_logement_adresse').val(nomRue);
                        });
                    }
                });
            });
        } else {
            $('#bloc-addressDisplay').addClass('hidden');
            $("label[for='nomRue']").removeClass('required');
            $('#nomRue').attr("required", false);
            $("#nomRue").empty();

            $('#checkbox-addressDisplay').addClass('hidden');
            $('#whitelabel_frontofficebundle_logement_nomRueNotFound').prop('checked', false);

            $('#bloc-noAddressDisplay').addClass('hidden');
            $("label[for='nomRueNotFound']").removeClass('required');
            $('#nomRueNotFound').attr("required", false);
            document.getElementById('information-noAddressDisplay').innerHTML =
                "<p class='col-xs-12 col-sm-12 col-md-4 col-lg-4'></p>"
                + "<p class='col-xs-12 col-sm-12 col-md-8s col-lg-8 bold' style='color:#d9534f;'>"
                + "<i class='glyphicon glyphicon-alert'></i>&nbsp;"
                + "Erreur syst&egrave;me"
                + "</p>";
        }
    }
});

