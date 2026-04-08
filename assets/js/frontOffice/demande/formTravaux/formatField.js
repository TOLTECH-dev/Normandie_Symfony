$(document).ready(function() {
    $('#whitelabel_frontofficebundle_demande__demande_travaux_nbPersFoyer').formatter(format_nb_personne);
    $('#whitelabel_frontofficebundle_demande__demande_travaux_revenu1').formatter(format_montant);
    $('#whitelabel_frontofficebundle_demande__demande_travaux_revenu2').formatter(format_montant);

    ////////////////////////////////////////////////////////////////////

    /* Disable starting by 0 */
    $("#whitelabel_frontofficebundle_demande__demande_travaux_nbPersFoyer").on('keyup', function(){
        var nbPersFoyer = $(this).val();
        if (nbPersFoyer == 0){
            $(this).val("");
        } else {
            $(this).val(parseInt(nbPersFoyer));
        }
    });
});
