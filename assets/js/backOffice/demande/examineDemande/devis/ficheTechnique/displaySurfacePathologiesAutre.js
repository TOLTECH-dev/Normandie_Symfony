$(document).ready(function()
{
    var elt_initialSurfacePathologiesAutre = $("div#whitelabel_backofficebundle_fichetechnique_ficheTechnique_initial_surfacePathologies input:last");
    displayInitialSurfacePathologiesAutre();

    elt_initialSurfacePathologiesAutre.on('click', function () {
        displayInitialSurfacePathologiesAutre();
    });

    function displayInitialSurfacePathologiesAutre() {

        if (elt_initialSurfacePathologiesAutre.prop('checked') == true) {
            $('#bloc-surfacePathologiesAutre').removeClass('hidden');
        } else {
            $('#bloc-surfacePathologiesAutre').addClass('hidden');
            $('#whitelabel_backofficebundle_fichetechnique_ficheTechnique_initial_surfacePathologiesAutre').val('');
        }
    }
});
