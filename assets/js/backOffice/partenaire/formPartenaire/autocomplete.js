$(document).ready(function(){
    jQuery.ui.autocomplete.prototype._resizeMenu = function () {
        var ul = this.menu.element;
        ul.outerWidth(this.element.outerWidth());
    };

    $("#whitelabel_backofficebundle_partenaire__partenaire_adresse_codePostal").on('keyup', function () {
        $('#whitelabel_backofficebundle_partenaire__partenaire_adresse_ville').val('');
        $('#whitelabel_backofficebundle_partenaire__partenaire_adresse_departement').val('');
    });

    var autocompleteInstance = $("#whitelabel_backofficebundle_partenaire__partenaire_adresse_codePostal").autocomplete({
        source: function( request, response ) {
            var codePostal = parseInt(request.term,10);
            if (codePostal && typeof codePostal == 'number') {
                $.ajax({
                    url: "168E46D2986D011C9864866FCB37291BA772E56C9D1AC2C6"+request.term+"26880543E88535164454BBFE58A3829B9348DFD304B3DC4496C22065CC1B3CED35BCC14AE3E76E53",
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
                            document.getElementById('match').innerHTML = '<br>' + "<p><span class='bold'>Aucun résultat</span></p>";
                        }
                        $('#whitelabel_backofficebundle_partenaire__partenaire_adresse_codePostal').removeClass("ui-autocomplete-loading");
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
            $( "#whitelabel_backofficebundle_partenaire__partenaire_adresse_codePostal" ).val( ui.item.code_postal );
            $( "#whitelabel_backofficebundle_partenaire__partenaire_adresse_ville" ).val( ui.item.nom );

            var departement = ui.item.dep_code + ' - ' + ui.item.dep_name;
            $('#whitelabel_backofficebundle_partenaire__partenaire_adresse_departement').val(departement);
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

            $('#whitelabel_backofficebundle_partenaire__partenaire_adresse_codePostal').prop('value', ui.item.code_postal);
            $('#whitelabel_backofficebundle_partenaire__partenaire_adresse_ville').prop('value', ui.item.nom);

            //var departement = (ui.item.code_postal).slice(0,2);
            var departement = ui.item.dep_code + ' - ' + ui.item.dep_name;
            $('#whitelabel_backofficebundle_partenaire__partenaire_adresse_departement').prop('value', departement);
        }
    }).data("ui-autocomplete");

    if (autocompleteInstance) {
        autocompleteInstance._renderItem = function( ul, item ) {
            return $( "<li>" )
                .append( "<div>" + item.label + " - " + item.nom + "</div>" )
                .appendTo( ul );
        };
    }
});
