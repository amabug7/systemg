define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/repairtemplate/index',
                    add_url: 'platform/repairtemplate/add',
                    edit_url: 'platform/repairtemplate/edit',
                    del_url: 'platform/repairtemplate/del',
                    multi_url: 'platform/repairtemplate/multi'
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
                    {field: 'template_key', title: __('Template_key'), operate: 'LIKE'},
                    {field: 'game_id', title: __('Game_id')},
                    {field: 'repair_type', title: __('Repair_type'), operate: 'LIKE'},
                    {field: 'version', title: __('Version')},
                    {field: 'status', title: __('Status'), searchList: {"normal": __('Normal'), "hidden": __('Hidden')}, formatter: Table.api.formatter.status},
                    {field: 'updatetime', title: __('Updatetime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange'},
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
