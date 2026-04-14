define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/message/index',
                    edit_url: 'platform/message/edit',
                    del_url: 'platform/message/del',
                    multi_url: 'platform/message/multi'
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
                    {field: 'nickname', title: __('Nickname'), operate: 'LIKE'},
                    {field: 'contact', title: __('Contact'), operate: 'LIKE'},
                    {field: 'content', title: __('Content'), operate: 'LIKE'},
                    {field: 'status', title: __('Status'), searchList: {"pending": __('Pending'), "approved": __('Approved'), "rejected": __('Rejected')}},
                    {field: 'audit_remark', title: __('Audit_remark'), operate: 'LIKE'},
                    {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                ]]
            });
            Table.api.bindevent(table);
        },
        edit: function () { Form.api.bindevent($("form[role=form]")); }
    };
    return Controller;
});
