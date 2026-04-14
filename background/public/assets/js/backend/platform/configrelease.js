define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/configrelease/index',
                    add_url: 'platform/configrelease/add',
                    edit_url: 'platform/configrelease/edit',
                    del_url: 'platform/configrelease/del',
                    multi_url: 'platform/configrelease/multi'
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
                    {field: 'release_no', title: __('Release_no'), operate: 'LIKE'},
                    {field: 'terminal', title: __('Terminal'), operate: 'LIKE'},
                    {field: 'release_version', title: __('Release_version')},
                    {field: 'status', title: __('Status'), operate: 'LIKE'},
                    {field: 'operator_id', title: __('Operator_id')},
                    {field: 'createtime', title: __('Createtime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange'},
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
