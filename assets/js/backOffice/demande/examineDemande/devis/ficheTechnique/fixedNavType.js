$(document).ready(function() {
    var elt_type0 = $('#whitelabel_backofficebundle_fichetechnique_ficheTechnique_initial_type');
    var elt_type1 = $('#whitelabel_backofficebundle_fichetechnique_ficheTechnique_BBC_type');
    var elt_type2 = $('#whitelabel_backofficebundle_fichetechnique_ficheTechnique_prescription_type');

    elt_type0.attr('value', '0 | situationInitiale');
    elt_type1.attr('value', '1 | scenarioBBC');
    elt_type2.attr('value', '2 | prescriptionTravaux');

    ////////////////////////////////////////////////////////////////////

    var header = document.querySelector("#bloc-type section");

    if(header) {
        function scrolled() {
            var windowHeight = document.body.clientHeight,
                currentScroll = document.body.scrollTop || document.documentElement.scrollTop;

            header.className = (currentScroll >= windowHeight - header.offsetHeight) ? "fixed col-xs-12 col-sm-12 col-md-10 col-lg-10 col-xs-offset-0 col-sm-offset-0 col-md-offset-1 col-lg-offset-1" : "";
        }

        addEventListener("scroll", scrolled, false);
    }
});
