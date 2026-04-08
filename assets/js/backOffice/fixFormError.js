/* Fix sticky header */
var delay = 0;
var offset = 125;

document.addEventListener('invalid', function(e){
    $(e.target).addClass("invalid");
    $('html, body').animate({scrollTop: $($(".invalid")[0]).offset().top - offset }, delay);
}, true);
document.addEventListener('change', function(e){
    $(e.target).removeClass("invalid")
}, true);
