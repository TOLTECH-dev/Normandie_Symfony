$(document).ready(function()
{
    $("button.button-cancelRMH").on('click', function () {
        var dateRMHId = $(this).find("input[name^='dateRMHId_']" ).val();
        $('#form_dateRMH_id').prop('value', dateRMHId);
    });
});
