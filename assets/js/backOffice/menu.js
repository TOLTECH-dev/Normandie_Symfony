$(document).ready(function() {

    function toggleSidebar() {
        $(".buttonSidebar").toggleClass("active");
        $(".mainSidebar").toggleClass("move-to-left");
        $(".sidebar-item").toggleClass("active");
    }

    $(".buttonSidebar").on("click tap", function() {
        toggleSidebar();
    });

    $(document).keyup(function(e) {
        if (e.keyCode === 27) {
            toggleSidebar();
        }
    });

});