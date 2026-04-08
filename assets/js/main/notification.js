$(document).ready(function(){
    $("#notification").fadeTo(6000, 0).slideUp(700, function(){
        $("#notification").remove();
    });
});
