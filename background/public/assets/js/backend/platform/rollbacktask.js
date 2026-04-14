define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/rollbacktask/index',
                    del_url: 'platform/rollbacktask/del',
                    multi_url: 'platform/rollbacktask/multi'
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
                    {field: 'task_no', title: __('Task_no'), operate: 'LIKE'},
                    {field: 'trigger_type', title: __('Trigger_type'), operate: 'LIKE'},
                    {field: 'terminal', title: __('Terminal'), operate: 'LIKE'},
                    {field: 'from_release_no', title: __('From_release_no'), operate: 'LIKE'},
                    {field: 'to_release_no', title: __('To_release_no'), operate: 'LIKE'},
                    {field: 'status', title: __('Status'), operate: 'LIKE'},
                    {field: 'createtime', title: __('Createtime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange'},
                    {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                ]]
            });
            Table.api.bindevent(table);
        }
    };
    return Controller;
});
