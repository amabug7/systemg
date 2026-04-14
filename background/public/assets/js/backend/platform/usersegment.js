define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/usersegment/index',
                    add_url: 'platform/usersegment/add',
                    edit_url: 'platform/usersegment/edit',
                    del_url: 'platform/usersegment/del',
                    multi_url: 'platform/usersegment/multi'
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
                    {field: 'user_id', title: __('User_id')},
                    {field: 'segment_tag', title: __('Segment_tag'), operate: 'LIKE'},
                    {field: 'score', title: __('Score')},
                    {field: 'active_days_30', title: __('Active_days_30')},
                    {field: 'pay_amount_30', title: __('Pay_amount_30')},
                    {field: 'snapshot_date', title: __('Snapshot_date')},
                    {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                ]]
            });
            Table.api.bindevent(table);
        },
        add: function () { Form.api.bindevent($("form[role=form]")); },
        edit: function () { Form.api.bindevent($("form[role=form]")); }
    };
    return Controller;
});
