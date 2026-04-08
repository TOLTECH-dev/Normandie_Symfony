/**
 *
 * @param CEP
 * @param GES
 * @returns {string}
 */
function getEtiquetteEnergetiqueByColumnByCEPAndGES(CEP, GES) {

    let etiquetteEnergetiqueLabel = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G'
    ];

    var annexeCEP = [
        [0, 70],
        [70, 110],
        [110, 180],
        [180, 250],
        [250, 330],
        [330, 420],
        [420, null]
    ];

    var annexeGES = [
        [0, 6],
        [6, 11],
        [11, 30],
        [30, 50],
        [50, 70],
        [70, 100],
        [100, null]
    ];

    let indexCEPEtiquetteEnergetique = getIndexEtiquetteEnergetique(annexeCEP, CEP);
    let indexGESEtiquetteEnergetique = getIndexEtiquetteEnergetique(annexeGES, GES);

    if (isNaN(indexCEPEtiquetteEnergetique) || isNaN(indexGESEtiquetteEnergetique)) {
        return '';
    }

    let indexEtiquetteEnergetique = (indexCEPEtiquetteEnergetique > indexGESEtiquetteEnergetique) ? indexCEPEtiquetteEnergetique : indexGESEtiquetteEnergetique;

    return etiquetteEnergetiqueLabel[indexEtiquetteEnergetique];
}

/**
 *
 * @param annexeArray
 * @param valueType
 * @returns {null}
 */
function getIndexEtiquetteEnergetique(annexeArray, valueType) {

    var currentIndexEtiquetteEnergetique = null;
    annexeArray.forEach(function (valueItem, indexItem) {
        let min = valueItem[0];
        let max = valueItem[1];

        if (null === max && parseInt(valueType) >= min) {
            currentIndexEtiquetteEnergetique = indexItem;
            return;
        } else if (parseInt(valueType) >= min && parseInt(valueType) < max) {
            currentIndexEtiquetteEnergetique = indexItem;
            return;
        }
    });

    return currentIndexEtiquetteEnergetique;
}

/**
 *
 * @param columnType
 */
window.initAndShowEtiquetteEnergetiqueByColumn = function (columnType) {

    var elementCEP = $("." + columnType + "-CEP");
    var elementGES = $("." + columnType + "-CEPGES");
    var elementEtiquetteEnergetique = $("." + columnType + "-etiquetteEnergetique");

    // Event CEP
    elementCEP.on('change blur keyup load', function() {

        let elementCEPValue = $(this).val();
        let elementGESValue = elementGES.val();

        elementCEPValue = elementCEPValue.replace(",", ".").replace(/\s/g,'');
        elementGESValue = elementGESValue.replace(",", ".").replace(/\s/g,'');

        if (elementCEPValue && elementGESValue && !isNaN(elementCEPValue) && !isNaN(elementGESValue)) {
            let etiquetteEnergetiqueCalcule = getEtiquetteEnergetiqueByColumnByCEPAndGES(elementCEPValue, elementGESValue);
            elementEtiquetteEnergetique.val(etiquetteEnergetiqueCalcule);
        } else {
            elementEtiquetteEnergetique.val('');
        }
    });

    // Event GES
    elementGES.on('change blur keyup load', function() {

        let elementCEPValue = elementCEP.val();
        let elementGESValue = $(this).val();

        elementCEPValue = elementCEPValue.replace(",", ".").replace(/\s/g,'');
        elementGESValue = elementGESValue.replace(",", ".").replace(/\s/g,'');

        if (elementGESValue && elementCEPValue && !isNaN(elementGESValue) && !isNaN(elementCEPValue)) {
            let etiquetteEnergetiqueCalcule = getEtiquetteEnergetiqueByColumnByCEPAndGES(elementCEPValue, elementGESValue);
            elementEtiquetteEnergetique.val(etiquetteEnergetiqueCalcule);
        } else {
            elementEtiquetteEnergetique.val('');
        }
    });
}