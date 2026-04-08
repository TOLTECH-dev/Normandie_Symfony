$(document).ready(function()
{
    var initScore = $('#js-initScore').attr('data-initScore');
    var initCommentaire = $('#js-initCommentaire').attr('data-initCommentaire');
    var initNotation = $('#js-initNotation').attr('data-initNotation');

    var elt_commentaire = $("#form_rating_commentaire");
    var elt_rating = $("#form_rating_score");
    var elt_valider = $("#form_valider");

    var isDisabled = true;
    if (!initNotation) isDisabled = false;

    if (initScore) {
        isDisabled = false;

        // Field Score
        if ('3' == initScore) {
            $("#input3").prop('checked', true);
        } else {
            if ('2' == initScore) {
                $("#input2").prop('checked', true);
            } else if ('1' == initScore) {
                $("#input1").prop('checked', true);
            }

            // Field Commentaire
            $("#box_commentaire").removeClass('hidden');
            $("label[for='form_rating_commentaire']").addClass('required');
            elt_commentaire.prop("required", true);
        }

        elt_rating.val(initScore);
        if (initCommentaire) elt_commentaire.val(initCommentaire);
    }
    elt_valider.prop("disabled", isDisabled);



    $('.button_score').on('click', function()
    {
        var ratingScore = $(this).attr("data-score");
        elt_rating.val(ratingScore);
        elt_valider.prop("disabled", false);

        elt_commentaire.val('');
        if (('1' === ratingScore) || ('2' === ratingScore)) {
            $("#box_commentaire").removeClass('hidden');
            $("label[for='form_rating_commentaire']").addClass('required');
            elt_commentaire.prop("required", true);
        } else {
            $("#box_commentaire").addClass('hidden');
            $("label[for='form_rating_commentaire']").removeClass('required');
            elt_commentaire.prop("required", false);
        }
    });
});
