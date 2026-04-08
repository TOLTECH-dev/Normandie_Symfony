$(document).ready(function()
{
    var elt_currentFlag = $('#currentFlag');
    var currentFlag = elt_currentFlag.val();

    if ('1' == currentFlag) {
        $("label[for='form_dateRMH']").removeClass('required');
        $('#form_dateRMH').prop("required", false);
    }
});
