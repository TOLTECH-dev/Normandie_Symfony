$(document).ready(function() {
    var columnType = ['initial', 'BBC', 'prescription'];

    columnType.forEach(function (item) {
        window.initAndShowEtiquetteEnergetiqueByColumn(item);
    });
});
