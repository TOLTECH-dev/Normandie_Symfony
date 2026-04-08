jQuery.extend( jQuery.fn.dataTableExt.oSort, {
    "date-uk-pre": function ( date ) {
        var formatDate = date.split('/');
        return (formatDate[2] + formatDate[1] + formatDate[0]) * 1;
    },

    "date-uk-asc": function ( dateFirst, dateSecond ) {
        return ((dateFirst < dateSecond) ? -1 : ((dateFirst > dateSecond) ? 1 : 0));
    },

    "date-uk-desc": function ( dateFirst, dateSecond ) {
        return ((dateFirst < dateSecond) ? 1 : ((dateFirst > dateSecond) ? -1 : 0));
    }
});