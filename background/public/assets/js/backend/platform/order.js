define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/order/index',
                    del_url: 'platform/order/del',
                    multi_url: 'platform/order/multi'
                }
            });
            var table = $("#table");
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                sortOrder: 'desc',
                columns: [
                    [
                        {field: 'state', checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE'},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'game_id', title: __('Game_id')},
                        {field: 'item_type', title: __('Item_type'), operate: 'LIKE'},
                        {field: 'item_name', title: __('Item_name'), operate: 'LIKE'},
                        {field: 'channel_key', title: __('Channel_key'), operate: 'LIKE'},
                        {field: 'amount', title: __('Amount')},
                        {field: 'pay_status', title: __('Pay_status'), searchList: {"created": __('Created'), "paid": __('Paid'), "closed": __('Closed')}},
                        {field: 'paid_at', title: __('Paid_at'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange'},
                        {field: 'createtime', title: __('Createtime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange'},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });
            Table.api.bindevent(table);
        }
    };
    return Controller;
});
