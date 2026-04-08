$(document).ready(function() {
  var columnType = ['finChantier'];

  var numericFieldsToControl = {
    'surfaceHabitable':'1',
    'surfaceSRT':'0',
    'toitureSurface':'0',
    'toitureR':'1',
    'mursSurface':'0',
    'mursR': '1',
    'menuiseriesExterieuresSurface':'0',
    'menuiseriesExterieuresUW':'1',
    'plancherBasSurface':'0',
    'plancherBasR':'1',
    'CEP':'1',
    'CEPGES':'1',
    'CEPUbat':'1',
    'CEPUbatBase':'0',
    'CEPQ4Pa_surf':'1'
  };

  columnType.forEach(function (item) {
    window.checkDataInputSurfaceHabitableByColumn(item, numericFieldsToControl);
  });
});
