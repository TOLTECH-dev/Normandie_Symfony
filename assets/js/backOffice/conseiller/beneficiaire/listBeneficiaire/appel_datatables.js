$(document).ready(function() {
    // Declare DataTable
    var table = $('#table_list_beneficiaire').DataTable({
        "language": {
            "url": "/build/json/datatable_french.json"
        },
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "pageLength": 25,
        "order": [[ 0, "desc" ]],
        "orderCellsTop": true,
        "scrollX": false,
        "stateSave": true,
        "stateDuration": -1,
        "processing": true,
        "serverSide": true,
        "ajax": {
            url: Routing.generate('conseiller_beneficiaire_list_ajax'),
            dataType: 'JSON',
            type: 'POST'
        },
        "columns": [
            {"data": "beneficiaireNomPrenom", "class": "breakWord"},
            {"data": "beneficiaireEmail", "class": "breakWord"},
            {"data": "beneficiaireAuteurCreation", "class": "breakWord"},
            {"data": "action", "class": "breakWord text-center", "searchable": false, "sortable": false}
        ],
        "columnDefs": [
            {
                "targets": 0,
                "render": function(data, type, row) {
                    return row['beneficiaireNomPrenom'];
                }
            },
            {
                "targets": 1,
                "render": function(data, type, row) {
                    return row['beneficiaireEmail'];
                }
            },
            {
                "targets": 2,
                "render": function(data, type, row) {
                    return row['beneficiaireAuteurCreation'];
                }
            },
            {
                "targets": 3,
                "render": function(data, type, row) {
                    let html = '';

                    html += '<a href="' + Routing.generate('conseiller_beneficiaire_view', {beneficiaireId: row['beneficiaireId']}) + '"' +
                        'class="btn btn-success btn-xs"' +
                        'role="button"' +
                        'data-toggle="tooltip"' +
                        'data-placement="bottom"' +
                        'data-container="body"' +
                        'title="Accéder au Menu B&eacute;n&eacute;ficiaire"' +
                        'target="_blank"' +
                        '>' +
                            '<i class="glyphicon glyphicon-eye-open"></i>' +
                        '</a>';

                    return html;
                }
            }
        ]
    });

});
