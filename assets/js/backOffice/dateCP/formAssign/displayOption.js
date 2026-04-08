$(document).ready(function() {
    var elt_currentFlag = $('#currentFlag');
    var currentFlag = elt_currentFlag.val();

    if ('1' == currentFlag) {
        $("label[for='form_dateCP']").removeClass('required');
        $('#form_dateCP').prop("required", false);
    }
});
