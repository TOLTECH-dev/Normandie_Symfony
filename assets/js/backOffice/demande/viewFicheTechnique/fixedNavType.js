$(document).ready(function()
{
    var header = document.querySelector("#bloc-type section");

    if (header) {
        function scrolled() {
            var windowHeight = document.body.clientHeight,
                currentScroll = document.body.scrollTop || document.documentElement.scrollTop;

            header.className = (currentScroll >= windowHeight - header.offsetHeight) ? "fixed col-xs-12 col-sm-12 col-md-10 col-lg-8 col-xs-offset-0 col-sm-offset-0 col-md-offset-1 col-lg-offset-2" : "";
        }

        addEventListener("scroll", scrolled, false);
    }
});
