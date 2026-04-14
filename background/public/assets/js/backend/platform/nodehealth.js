define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/nodehealth/index',
                    add_url: 'platform/nodehealth/add',
                    del_url: 'platform/nodehealth/del',
                    multi_url: 'platform/nodehealth/multi'
                }
            });
            var table = $("#table");
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                sortOrder: 'desc',
                columns: [[
                    {field: 'state', checkbox: true},
                    {field: 'id', title: __('Id')},
                    {field: 'channel_key', title: __('Channel_key'), operate: 'LIKE'},
                    {field: 'endpoint', title: __('Endpoint'), operate: 'LIKE'},
                    {field: 'latency_ms', title: __('Latency_ms')},
                    {field: 'http_code', title: __('Http_code')},
                    {field: 'status', title: __('Status'), operate: 'LIKE'},
                    {field: 'detail', title: __('Detail'), operate: 'LIKE'},
                    {field: 'createtime', title: __('Createtime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange'},
                    {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                ]]
            });
            Table.api.bindevent(table);
        },
        add: function () { Form.api.bindevent($("form[role=form]")); }
    };
    return Controller;
});
