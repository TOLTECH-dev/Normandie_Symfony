$(document).ready(function() {
    var columnType = ['finChantier'];

    columnType.forEach(function (item) {
        window.initAndShowEtiquetteEnergetiqueByColumn(item);
    });
});
