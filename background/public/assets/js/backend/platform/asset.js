define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/asset/index',
                    del_url: 'platform/asset/del',
                    multi_url: 'platform/asset/multi'
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
                        {field: 'user_id', title: __('User_id')},
                        {field: 'game_id', title: __('Game_id')},
                        {field: 'asset_type', title: __('Asset_type'), operate: 'LIKE'},
                        {field: 'asset_name', title: __('Asset_name'), operate: 'LIKE'},
                        {field: 'source_order_no', title: __('Source_order_no'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'), searchList: {"active": __('Active'), "expired": __('Expired')}},
                        {field: 'expiretime', title: __('Expiretime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange'},
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
