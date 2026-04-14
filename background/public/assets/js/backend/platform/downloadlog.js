define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            var parseQuery = function () {
                var params = {};
                var query = window.location.search || '';
                if (query.indexOf('?') === 0) {
                    query = query.substring(1);
                }
                if (!query) {
                    return params;
                }
                $.each(query.split('&'), function (_, item) {
                    if (!item) {
                        return;
                    }
                    var pair = item.split('=');
                    var key = decodeURIComponent(pair[0] || '');
                    var value = decodeURIComponent(pair[1] || '');
                    params[key] = value;
                });
                return params;
            };
            var renderFilterHint = function (filter) {
                var hints = [];
                if (filter.error_code) {
                    hints.push(__('Error code') + ': ' + filter.error_code);
                }
                if (filter.game_id) {
                    hints.push(__('Game id') + ': ' + filter.game_id);
                }
                if (filter.createtime) {
                    hints.push(__('Time range') + ': ' + filter.createtime);
                }
                if (hints.length) {
                    $('#active-filter-hint').text(__('Active filters') + ' - ' + hints.join(' | ')).show();
                }
            };
            var queryParams = parseQuery();
            var filter = {};
            try {
                filter = queryParams.filter ? JSON.parse(queryParams.filter) : {};
            } catch (e) {
                filter = {};
            }
            renderFilterHint(filter);
            Table.api.init({
                extend: {
                    index_url: 'platform/downloadlog/index',
                    table: 'platform_download_log'
                }
            });
            var table = $("#table");
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url + window.location.search,
                pk: 'id',
                sortName: 'createtime',
                sortOrder: 'desc',
                columns: [
                    [
                        {field: 'id', title: __('Id')},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'game_id', title: __('Game_id')},
                        {field: 'channel', title: __('Channel'), operate: 'LIKE'},
                        {field: 'resource_type', title: __('Resource_type'), operate: 'LIKE'},
                        {field: 'resource_name', title: __('Resource_name'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'), searchList: {"started": __('Started'), "success": __('Success'), "failed": __('Failed')}},
                        {field: 'device_id', title: __('Device_id'), operate: 'LIKE'},
                        {field: 'client_version', title: __('Client_version'), operate: 'LIKE'},
                        {field: 'createtime', title: __('Createtime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange'}
                    ]
                ]
            });
            $(document).off('click', '.btn-exportlogs').on('click', '.btn-exportlogs', function () {
                var options = table.bootstrapTable('getOptions');
                var connector = window.location.search ? '&' : '?';
                var sort = encodeURIComponent(options.sortName || 'createtime');
                var order = encodeURIComponent(options.sortOrder || 'desc');
                window.location.href = 'platform/downloadlog/export' + window.location.search + connector + 'sort=' + sort + '&order=' + order;
            });
            Table.api.bindevent(table);
        }
    };
    return Controller;
});
