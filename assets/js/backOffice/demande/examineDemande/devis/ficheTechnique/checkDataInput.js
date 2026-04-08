$(document).ready(function() {
  var columnType = ['initial', 'BBC', 'prescription'];

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
    'CEP':'0',
    'CEPGES':'0',
    'CEPUbat':'0',
    'CEPUbatBase':'0',
    'CEPQ4Pa_surf':'0'
  };

  columnType.forEach(function (item) {
    window.checkDataInputSurfaceHabitableByColumn(item, numericFieldsToControl);
  });
});
