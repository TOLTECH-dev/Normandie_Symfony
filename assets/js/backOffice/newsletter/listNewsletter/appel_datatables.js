$(document).ready(function()
{
    // Init column filter SEARCH
    $('#table_list_newsletter tr.filterrow th[class="with_search"]').each( function () {
        var title = $(this).html();
        $(this).html( '<input type="text" class="with_search" placeholder="'+title+'" />' );
    });

    // DataTable
    var tableNewsletter = $('#table_list_newsletter').DataTable({
        "language": {
            "url": "/build/json/datatable_french.json"
        },
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "pageLength": 25,
        "aoColumns": [{"sType": "date-uk"},null,null],
        "order": [[0, "desc"]],
        "orderCellsTop": true
    });

    // Apply column filter SEARCH
    $('#table_list_newsletter tr.filterrow th[class="with_search"] input').on( 'change keyup', function () {
        tableNewsletter
            .column( $(this).parent().index()+':visible' )
            .search( this.value )
            .draw();
    });

    // Init and Apply column filter SELECT
    $('#table_list_newsletter tr.filterrow th[class="with_select"]').each( function (m, n) {
        var select = $('<select><option value=""></option></select>')
            .appendTo($(this).empty())
            .on('change', function () {
                tableNewsletter.column(n.cellIndex)
                    .search($(this).val() ? '^'+$(this).val() : '', true, false)
                    .draw();
            });

    tableNewsletter.column(n.cellIndex).data().unique().sort().each( function (d, j) {
            if (d) {
                select.append("<option value=\"" + d + "\">" + d + "</option>");
            }
        });
    });

    // Clear all filter
    $('#table_list_newsletter .clear_search_input').on('click', function () {
        $('#table_list_newsletter tr.filterrow th input').val('').change();
        $('#table_list_newsletter tr.filterrow th select').val('').change();
    });
});
