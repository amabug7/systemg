define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/orderalert/index',
                    edit_url: 'platform/orderalert/edit',
                    del_url: 'platform/orderalert/del',
                    multi_url: 'platform/orderalert/multi'
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
                    {field: 'order_no', title: __('Order_no'), operate: 'LIKE'},
                    {field: 'alert_type', title: __('Alert_type'), operate: 'LIKE'},
                    {field: 'level', title: __('Level'), operate: 'LIKE'},
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
