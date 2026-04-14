define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/material/index',
                    add_url: 'platform/material/add',
                    edit_url: 'platform/material/edit',
                    del_url: 'platform/material/del',
                    multi_url: 'platform/material/multi'
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
                    {field: 'name', title: __('Name'), operate: 'LIKE'},
                    {field: 'material_type', title: __('Material_type'), operate: 'LIKE'},
                    {field: 'url', title: __('Url'), operate: 'LIKE'},
                    {field: 'tags', title: __('Tags'), operate: 'LIKE'},
                    {field: 'status', title: __('Status'), searchList: {"normal": __('Normal'), "hidden": __('Hidden')}, formatter: Table.api.formatter.status},
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
