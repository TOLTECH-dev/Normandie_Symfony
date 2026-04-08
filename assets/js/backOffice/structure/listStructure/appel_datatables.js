$(document).ready(function() {
    $('#table_list_structure').DataTable({
        "language": {
            "url": "/build/json/datatable_french.json"
        },
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "pageLength": 25,
        "order": [[ 0, "asc" ]]
    });
} );
