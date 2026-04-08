setTimeout(function(){
    document.getElementById('bloc-identificationCheckbox').style.display = 'block';
}, 3000);

////////////////////////////////////////////////////////////////////

$(document).ready(function() {
    var elt_situation0 = $('#whitelabel_frontofficebundle_logement_situation_0');
    var elt_situation1 = $('#whitelabel_frontofficebundle_logement_situation_1');
    var elt_situation2 = $('#whitelabel_frontofficebundle_logement_situation_2');

    // Loading page
    if ($('input[id=whitelabel_frontofficebundle_logement_situation_1]:checked').val() || '') {
        hideTypeHabitation();
    } else {
        displayTypeHabitation();
    }

    // Clicking on radio Prpriétaire Ocuupant
    elt_situation0.on('click', function () {
        if ($('input[id=whitelabel_frontofficebundle_logement_situation_0]:checked').val() || '') {
            displayTypeHabitation();
        }
    });

    // Clicking on radio Propriétaire Bailleur
    elt_situation1.on('click', function () {
        if ($('input[id=whitelabel_frontofficebundle_logement_situation_1]:checked').val() || '') {
            hideTypeHabitation();
        }
    });

    // Clicking on radio Locataire
    elt_situation2.on('click', function () {
        if ($('input[id=whitelabel_frontofficebundle_logement_situation_2]:checked').val() || '') {
            displayTypeHabitation();
        }
    });

    ////////////////////////////////////////////////////////////////////

    function displayTypeHabitation() {
        $('#bloc-typeHabitation').removeClass('hidden');

        $('#label-typeHabitation').addClass('required');
        $('#whitelabel_frontofficebundle_logement_typeHabitation_0').attr("required", true);
        $('#whitelabel_frontofficebundle_logement_typeHabitation_1').attr("required", true);

        var elt_situationForm = $('#box-situation').val();
        if (elt_situationForm) {
            if ('0 | principale' == elt_situationForm) {
                $('#bloc-typeHabitation input[id=whitelabel_frontofficebundle_logement_typeHabitation_0]').prop('checked', true);
            } else if ('1 | secondaire' == elt_situationForm) {
                $('#bloc-typeHabitation input[id=whitelabel_frontofficebundle_logement_typeHabitation_1]').prop('checked', true);
            }
        }
    }

    function hideTypeHabitation() {
        $('#bloc-typeHabitation').addClass('hidden');

        $('#label-typeHabitation').removeClass('required');
        $('#whitelabel_frontofficebundle_logement_typeHabitation_0').attr("required", false);
        $('#whitelabel_frontofficebundle_logement_typeHabitation_1').attr("required", false);

        $('#bloc-typeHabitation input[type=radio]:checked').prop('checked', false);
    }
});
