define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/reconcile/index',
                    add_url: 'platform/reconcile/add',
                    del_url: 'platform/reconcile/del',
                    multi_url: 'platform/reconcile/multi'
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
                    {field: 'biz_date', title: __('Biz_date')},
                    {field: 'channel_key', title: __('Channel_key'), operate: 'LIKE'},
                    {field: 'order_total', title: __('Order_total')},
                    {field: 'paid_total', title: __('Paid_total')},
                    {field: 'mismatch_total', title: __('Mismatch_total')},
                    {field: 'status', title: __('Status'), operate: 'LIKE'},
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
