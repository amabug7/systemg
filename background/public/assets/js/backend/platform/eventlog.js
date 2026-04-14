define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/eventlog/index',
                    del_url: 'platform/eventlog/del',
                    multi_url: 'platform/eventlog/multi'
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
                    {field: 'event_name', title: __('Event_name'), operate: 'LIKE'},
                    {field: 'terminal', title: __('Terminal'), operate: 'LIKE'},
                    {field: 'page_key', title: __('Page_key'), operate: 'LIKE'},
                    {field: 'channel', title: __('Channel'), operate: 'LIKE'},
                    {field: 'game_id', title: __('Game_id')},
                    {field: 'user_id', title: __('User_id')},
                    {field: 'createtime', title: __('Createtime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange'},
                    {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                ]]
            });
            Table.api.bindevent(table);
        }
    };
    return Controller;
});
