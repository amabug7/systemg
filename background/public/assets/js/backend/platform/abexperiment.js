define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/abexperiment/index',
                    add_url: 'platform/abexperiment/add',
                    edit_url: 'platform/abexperiment/edit',
                    del_url: 'platform/abexperiment/del',
                    multi_url: 'platform/abexperiment/multi'
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
                    {field: 'experiment_key', title: __('Experiment_key'), operate: 'LIKE'},
                    {field: 'name', title: __('Name'), operate: 'LIKE'},
                    {field: 'terminal', title: __('Terminal'), operate: 'LIKE'},
                    {field: 'status', title: __('Status'), operate: 'LIKE'},
                    {field: 'metric_event', title: __('Metric_event'), operate: 'LIKE'},
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
