define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/opsalert/index',
                    edit_url: 'platform/opsalert/edit',
                    del_url: 'platform/opsalert/del',
                    multi_url: 'platform/opsalert/multi'
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
                    {field: 'alert_type', title: __('Alert_type'), operate: 'LIKE'},
                    {field: 'level', title: __('Level'), operate: 'LIKE'},
                    {field: 'target', title: __('Target'), operate: 'LIKE'},
                    {field: 'status', title: __('Status'), searchList: {"open": __('Open'), "closed": __('Closed')}},
                    {field: 'workorder_no', title: __('Workorder_no'), operate: 'LIKE'},
                    {field: 'createtime', title: __('Createtime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange'},
                    {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                ]]
            });
            Table.api.bindevent(table);
        },
        edit: function () { Form.api.bindevent($("form[role=form]")); }
    };
    return Controller;
});
