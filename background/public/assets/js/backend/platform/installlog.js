define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            var parseMeta = function (value) {
                if (!value) {
                    return {};
                }
                if (typeof value === 'object') {
                    return value;
                }
                try {
                    return JSON.parse(value);
                } catch (e) {
                    return {};
                }
            };
            var metaField = function (row, path, fallback) {
                var meta = parseMeta(row.meta_json);
                var parts = String(path || '').split('.');
                var cur = meta;
                for (var i = 0; i < parts.length; i++) {
                    if (!cur || typeof cur !== 'object') {
                        return fallback || '';
                    }
                    cur = cur[parts[i]];
                }
                return cur === undefined || cur === null ? (fallback || '') : cur;
            };
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
                if (filter.status) {
                    hints.push(__('Status') + ': ' + filter.status);
                }
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
            var applyStatusFilter = function (status) {
                var params = parseQuery();
                var filterObj = {};
                var opObj = {};
                try {
                    filterObj = params.filter ? JSON.parse(params.filter) : {};
                } catch (e) {
                    filterObj = {};
                }
                try {
                    opObj = params.op ? JSON.parse(params.op) : {};
                } catch (e) {
                    opObj = {};
                }
                if (status) {
                    filterObj.status = status;
                    opObj.status = '=';
                } else {
                    delete filterObj.status;
                    delete opObj.status;
                }
                params.filter = JSON.stringify(filterObj);
                params.op = JSON.stringify(opObj);
                var list = [];
                $.each(params, function (key, value) {
                    if (value !== undefined && value !== null && value !== '') {
                        list.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
                    }
                });
                window.location.href = window.location.pathname + (list.length ? ('?' + list.join('&')) : '');
            };
            var showDetail = function (row) {
                var meta = parseMeta(row.meta_json);
                $('#installlog-detail-json').text(JSON.stringify({
                    id: row.id,
                    user_id: row.user_id,
                    game_id: row.game_id,
                    status: row.status,
                    install_path: row.install_path,
                    error_code: row.error_code,
                    createtime: row.createtime,
                    summary: {
                        pipeline_status: meta.status || '',
                        failed_step: meta.failed_step || '',
                        total_ms: meta.total_ms || 0,
                        deploy_mode: meta.deploy_mode || '',
                        rollback_applied: !!meta.rollback_applied
                    },
                    items: Array.isArray(meta.items) ? meta.items : [],
                    raw_meta: meta
                }, null, 2));
                $('#installlog-detail-modal').modal('show');
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
                    index_url: 'platform/installlog/index',
                    table: 'platform_install_log'
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
                        {field: 'status', title: __('Status'), searchList: {"started": __('Started'), "success": __('Success'), "failed": __('Failed')}},
                        {field: 'install_path', title: __('Install_path'), operate: 'LIKE'},
                        {
                            field: 'pipeline_status',
                            title: '报告状态',
                            operate: false,
                            formatter: function (value, row) {
                                return metaField(row, 'status', '-');
                            }
                        },
                        {
                            field: 'failed_step',
                            title: '失败环节',
                            operate: false,
                            formatter: function (value, row) {
                                return metaField(row, 'failed_step', '-');
                            }
                        },
                        {
                            field: 'total_ms',
                            title: '总耗时(ms)',
                            operate: false,
                            formatter: function (value, row) {
                                return metaField(row, 'total_ms', 0);
                            }
                        },
                        {
                            field: 'deploy_mode',
                            title: '部署模式',
                            operate: false,
                            formatter: function (value, row) {
                                return metaField(row, 'deploy_mode', '-');
                            }
                        },
                        {
                            field: 'rollback_applied',
                            title: '回滚',
                            operate: false,
                            formatter: function (value, row) {
                                return metaField(row, 'rollback_applied', false) ? '是' : '否';
                            }
                        },
                        {field: 'device_id', title: __('Device_id'), operate: 'LIKE'},
                        {field: 'client_version', title: __('Client_version'), operate: 'LIKE'},
                        {field: 'error_code', title: __('Error_code'), operate: 'LIKE'},
                        {field: 'createtime', title: __('Createtime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange'},
                        {
                            field: 'detail',
                            title: __('Detail'),
                            operate: false,
                            formatter: function (value, row) {
                                return '<a href="javascript:;" class="btn btn-xs btn-info btn-logdetail" data-id="' + row.id + '">' + __('Detail') + '</a>';
                            }
                        }
                    ]
                ]
            });
            $(document).off('click', '.btn-only-failed').on('click', '.btn-only-failed', function () {
                applyStatusFilter('failed');
            });
            $(document).off('click', '.btn-clear-status-filter').on('click', '.btn-clear-status-filter', function () {
                applyStatusFilter('');
            });
            $(document).off('click', '.btn-logdetail').on('click', '.btn-logdetail', function () {
                var id = Number($(this).data('id') || 0);
                var rows = table.bootstrapTable('getData') || [];
                var target = null;
                for (var i = 0; i < rows.length; i++) {
                    if (Number(rows[i].id) === id) {
                        target = rows[i];
                        break;
                    }
                }
                if (target) {
                    showDetail(target);
                }
            });
            $(document).off('click', '.btn-exportlogs').on('click', '.btn-exportlogs', function () {
                var options = table.bootstrapTable('getOptions');
                var connector = window.location.search ? '&' : '?';
                var sort = encodeURIComponent(options.sortName || 'createtime');
                var order = encodeURIComponent(options.sortOrder || 'desc');
                window.location.href = 'platform/installlog/export' + window.location.search + connector + 'sort=' + sort + '&order=' + order;
            });
            Table.api.bindevent(table);
        }
    };
    return Controller;
});
