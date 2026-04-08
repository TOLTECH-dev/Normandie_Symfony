$(document).ready(function()
{
    var arrayDemandeType =  new Array();
    arrayDemandeType[1] = 'Audit énergétique et scénarios';
    arrayDemandeType[2] = 'Audit numérique';
    arrayDemandeType[3] = 'Travaux à définir';
    arrayDemandeType[4] = 'Audit énergétique Région Normandie';
    arrayDemandeType[5] = 'Mise à jour Audit énergétique et scénarios';
    arrayDemandeType[30] = 'Travaux niveau 1';
    arrayDemandeType[31] = 'Travaux niveau 2';
    arrayDemandeType[32] = 'Travaux niveau 2 - Rénovateur BBC';
    arrayDemandeType[331] = 'Travaux niveau 3 - Rénovation BBC (1/2)';
    arrayDemandeType[332] = 'Travaux niveau 3 - Rénovation BBC (2/2)';
    arrayDemandeType[341] = 'Travaux niveau 3 - Biosourcé (1/2)';
    arrayDemandeType[342] = 'Travaux niveau 3 - Biosourcé (2/2)';
    arrayDemandeType[36] = 'Travaux - Sortie de passoire';
    arrayDemandeType[37] = 'Travaux - Première étape BBC avec RGE';
    arrayDemandeType[381] = 'Travaux - Première étape BBC avec Rénovateur (1/2)';
    arrayDemandeType[382] = 'Travaux - Première étape BBC avec Rénovateur (2/2)';
    arrayDemandeType[391] = 'Travaux - Rénovation globale BBC (1/2)';
    arrayDemandeType[392] = 'Travaux - Rénovation globale BBC (2/2)';

    var isAdmin = $('#js-roles').data('is-admin');
    var isClient = $('#js-roles').data('is-client');

    var columns = new Array();
    $.each($(".header"), function(index) {
        if ([0,1].indexOf(index) !== -1) {
            columns.push({"data": $(this).attr("data-fieldCode"), "class": "breakWord"});
        } else {
            columns.push({"data": $(this).attr("data-fieldCode"), "class": "text-center"});
        }
    });

    // DataTable
    var table = $('#table_list_titre').DataTable({
        "language": {
            "url": "/build/json/datatable_french.json"
        },
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "pageLength": 25,
        "order": [],
        "orderCellsTop": true,
        "scrollX": false,
        "stateSave": true,
        "stateDuration": -1,
        "processing": true,
        "serverSide": true,
        "ajax": {
            url: 'E31EC9DA885C4125538CF22A7EE00F4018138F22095DB6E8A95D2654705148DDE542014E9391BE4075B6A06142254EC40E121A75A12393BFF0E4D6D3C8D10044',
            dataType:'JSON',
            type: 'POST'
        },
        "columns": columns,
        "columnDefs": [
            {
                "targets": 0,
                "render": function(data, type, row) {
                    var typeDisplay = '';

                    if(arrayDemandeType[row['demandeType']]) {
                        typeDisplay = arrayDemandeType[row['demandeType']];
                    }
                    return typeDisplay;
                }
            },
            {
                "targets": 5,
                "render": function (data, type, row) {
                    var htmlValeurTitre = '';
                    htmlValeurTitre += '' +
                        number_format(row['valeurTitre'], 2, ',', ' ') + ' &euro;<br>'
                    ;

                    return htmlValeurTitre;
                }
            },
            {
                "targets": 8,
                "render": function(data, type, row) {
                    let html = '';

                    if ('1' == isAdmin || '1' == isClient) {

                        html += '<a href="03E096937A7877EABDBC219E08D6EDC9BDCF5A00E68D45DF28' + row['titreId'] + 'E74A6C34A6F7C7535B3916D9E8DEE3A1307B830E668605413D4B278667C58038F835590A0E4ABA"' +
                            'class="btn btn-success btn-xs"' +
                            'role="button"' +
                            'data-toggle="tooltip"' +
                            'data-placement="bottom"' +
                            'data-container="body"' +
                            'title="T&eacute;l&eacute;charger l\'attestation de non-r&eacute;ception ou de perte du ch&egrave;que &eacute;co-&eacute;nergie Normandie"' +
                            'target="_blank"' +
                            '>' +
                            '<i class="glyphicon glyphicon-envelope"></i>'+
                            '</a>';
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
                var title = $(that.footer()).text();
                $(that.footer()).html('<input type="text" placeholder="' + title + '" />');

                var search_thread = null;
                $('input', that.footer()).on('keyup change', function() {
                    var this_val = $(this).val();
                    if (that.search() !== this_val) {
                        clearTimeout(search_thread)
                        search_thread = setTimeout(function () {
                            var val = $.fn.dataTable.util.escapeRegex(this_val);
                            that
                                .search( val )
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
                    .on('change', function () {
                        var val = $.fn.dataTable.util.escapeRegex(
                            $(this).val()
                        );
                        column
                            .search( val ? val : '', true, false)
                            .draw();
                    });

                $.each(arrayDemandeType, function( k, v ) {
                    if (v) {
                        select.append("<option value=\"" + k + "\">" + v + "</option>");
                    }
                });
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

    /**
     *
     * @param number
     * @param decimals
     * @param dec_point
     * @param thousands_point
     */
    function number_format(number, decimals, dec_point, thousands_point)
    {
        if (number == null || !isFinite(number)) {
            throw new TypeError("Le nombre est invalide");
        }

        if (!decimals) {
            var len = number.toString().split('.').length;
            decimals = len > 1 ? len : 0;
        }

        if (!dec_point) {
            dec_point = '.';
        }

        if (!thousands_point) {
            thousands_point = ',';
        }

        number = parseFloat(number).toFixed(decimals);

        number = number.replace(".", dec_point);

        var splitNum = number.split(dec_point);
        splitNum[0] = splitNum[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousands_point);
        number = splitNum.join(dec_point);

        return number;
    }

});
