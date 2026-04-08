$(document).ready(function() {
    $('#table_list_demande').DataTable({
        "language": {
            "url": "/build/json/datatable_french.json"
        },
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "pageLength": 25,
        "order": [[ 0, "desc" ]],
        drawCallback: function() {
            $('[data-toggle="popover"]').popover(
                {html: true}
            );
        }
    });
} );
