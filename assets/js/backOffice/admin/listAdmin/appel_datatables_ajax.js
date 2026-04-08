$(document).ready(function()
{
    var arraySelectOptions = $('#js-options').data('array-select-options');
    var arrayRoles = $('#js-roles').data('roles');
    var isAdmin = $('#js-roles').data('is-admin');

    var columns = new Array();
    $.each($(".header"), function(index) {
        if(index != 8) {
            columns.push({"data": $(this).attr("data-fieldCode"), "class": "text-center", "searchable": true, "sortable": true});
        } else {
            columns.push({"data": $(this).attr("data-fieldCode"), "class": "text-center", "searchable": false, "sortable": false});
        }
    });

    // DataTable
    var table = $('#table_list_user').DataTable({
        responsive: true,
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
            url: '1AA14D75EF7F40684C7E3A47FEF8A70A7B47FD46BFDF2CF3195C1D76FBCDFA5BF6F740AE63C33E25AB9256D1A4B9BA5E4E52E76160E99727E829C72EB224B61E',
            dataType: 'JSON',
            type: 'POST'
        },
        "columns": columns,
        "columnDefs": [
            {
                "targets": 1,
                "render": function(data, type, row) {
                    let html = '';

                    html += '<a href="D642A'+ row['id'] + 'C19217141BF15615F2C34D18AF2627C056242640BB89DCFC66B1515F3FE1621EBF058FEE8D63294AB559321FF579A266393384B7637521BB60A863C1432"' +
                       'data-toggle="tooltip"' +
                       'data-placement="bottom"' +
                       'data-container="body"' +
                       'title="Consulter l\'Utilisateur"' +
                    '>' +
                        row['username'] +
                    '</a>';

                    return html;
                }
            },
            {
                "targets": 5,
                "render": function(data, type, row) {
                    let html = '';

                    if (1 == row['enabled']) {
                        html += 'Actif';
                    } else {
                        html += 'Inactif depuis le ' + row['dateInactif'];
                    }

                    return html;
                }
            },
            {
                "targets": 6,
                "render": function(data, type, row) {
                    let role = (row['roles'][0]) ? (row['roles'][0]) : '';
                    if (role) {
                        return arrayRoles[role];
                    }
                }
            },
            {
                "targets": 8,
                "render": function(data, type, row) {
                    let html = '';

                    if ('1' == isAdmin) {

                        if (0 == row['enabled']) {

                            let flag = 1;
                            html += '<a href="B00F2FD60B914182CEB5B6085DE5EBB56' + row['id'] + '6150BF4CE27E66BB845AC0650F3B9EABB7B82774699BB74622090A004907EBC' + flag + '7C88BFF4C2B09112B6456CFD77D73CDC"' +
                               'class="btn btn-danger btn-xs"' +
                               'role="button"' +
                               'data-toggle="tooltip"' +
                               'data-placement="bottom"' +
                               'data-container="body"' +
                               'title="Activer l\'Utilisateur"' +
                            '>' +
                                '<i class="glyphicon glyphicon-flag"></i>'+
                            '</a>';

                        } else if (1 == row['enabled']) {

                            let flag = 0;
                            html += '<a href="B00F2FD60B914182CEB5B6085DE5EBB56' + row['id'] + '6150BF4CE27E66BB845AC0650F3B9EABB7B82774699BB74622090A004907EBC' + flag + '7C88BFF4C2B09112B6456CFD77D73CDC"' +
                               'class="btn btn-success btn-xs"' +
                               'role="button"' +
                               'data-toggle="tooltip"' +
                               'data-placement="bottom"' +
                               'data-container="body"' +
                               'title="D&eacute;sactiver l\'Utilisateur"'+
                            '>' +
                                '<i class="glyphicon glyphicon-flag"></i>'+
                            '</a>';
                        }

                    }

                    return html;
                }
            },
        ],
        "initComplete": function() {
            var api = this.api();

            // Apply SEARCH filter
            api.columns('.with_search').every(function() {
                var that = this;

                var title = $(that.header()).text();
                var id = $(that.footer()).prop('id');
                $(that.footer()).html('<input type="text" name="' + id + '" placeholder="' + title + '" />');

                var search_thread = null;
                $('input', this.footer()).on('keyup change', function() {
                    var this_val = $(this).val();

                    if (that.search() !== this_val) {
                        clearTimeout(search_thread);
                        search_thread = setTimeout(function () {
                            that
                                .search($.fn.dataTable.util.escapeRegex(this_val))
                                .draw();
                        }, 1000);
                    }
                });
            });

            // Apply SELECT filter
            api.columns('.with_select').every(function () {
                var column = this;
                var id = $(this.footer()).prop('id');
                var select = $('<select name="' + id + '"><option value=""></option></select>')
                    .appendTo($(column.footer()).empty())
                    .on('keyup change', function () {
                        var val = $.fn.dataTable.util.escapeRegex($(this).val());
                        column
                            .search( val ? val : '', true, false )
                            .draw();
                    });

                if (5 === $(this)[0][0] && arraySelectOptions['userStatutSlug']) { // Actif
                    $.each(arraySelectOptions['userStatutSlug'], function(index, value) {
                        select.append("<option value=\"" + index + "\">" + value + "</option>");
                    });
                } else if (6 === $(this)[0][0] && arraySelectOptions['userRolesLabel']) { // Roles label
                    $.each(arraySelectOptions['userRolesLabel'], function(index, value) {
                        select.append("<option value=\"" + index + "\">" + value + "</option>");
                    });
                }
            });

            // Restore state
            var state = table.state.loaded();
            if (state) {
                table.columns().eq(0).each(function (colIdx) {
                    var colSearch = state.columns[colIdx].search;
                    var indexChild = parseInt(colIdx+1);

                    $('tr#filterrow th:nth-child( ' + indexChild + ') input').val(colSearch.search);
                    $('tr#filterrow th:nth-child( ' + indexChild + ') select').val(colSearch.search);
                });
            }
        }
    });

    // Clear all filter
    $('#clear_search_input').on('click', function () {

        $('tr#filterrow th input').val('');
        $('tr#filterrow th select').val('');

        table
            .columns()
            .search( '' )
            .columns( '.with_search .with_select' )
            .search( '' )
            .draw();
    });
});
