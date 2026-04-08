$(document).ready(function() {
    var date = new Date();
    var jour = ('0'+date.getDate()).slice(-2);
    var mois = ('0'+(date.getMonth()+1)).slice(-2);
    var annee = date.getFullYear();

    var date_now = jour + "/" + mois + "/" + annee;

    /* *****************************************************************
     ********************************************************************
                    A U D I T E U R / R E N O V A T E U R
     ********************************************************************
     *******************************************************************/
    var value_dateRattachement = $('#value-dateRattachement').val();
    if ('' != value_dateRattachement) {
        $('#dateRattachement').attr('value', value_dateRattachement);
        $('#dateRattachement').datepicker('setDate', value_dateRattachement);
    } else {
        $('#dateRattachement').attr('value', date_now);
        $('#dateRattachement').datepicker('setDate', date_now);
    }
});
