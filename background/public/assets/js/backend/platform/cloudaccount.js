define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/cloudaccount/index',
                    add_url: 'platform/cloudaccount/add',
                    edit_url: 'platform/cloudaccount/edit',
                    del_url: 'platform/cloudaccount/del',
                    multi_url: 'platform/cloudaccount/multi'
                }
            });
            var table = $('#table');
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                columns: [[
                    {field: 'state', checkbox: true},
                    {field: 'id', title: __('Id')},
                    {field: 'username', title: '用户名(手机号码)', operate: 'LIKE'},
                    {field: 'access_token', title: 'AccessToken', operate: 'LIKE', formatter: function (value) {
                        var v = String(value || '');
                        if (!v) return '';
                        if (v.length <= 20) return v;
                        return v.substring(0, 10) + '...' + v.substring(v.length - 8);
                    }},
                    {field: 'status', title: '状态', searchList: {normal: '开启', hidden: '隐藏', limited: '限速'}, formatter: Table.api.formatter.status},
                    {field: 'token_refresh_time', title: 'Token刷新时间', formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange'},
                    {field: 'createtime', title: __('Createtime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange'},
                    {
                        field: 'custom_operate',
                        title: '操作',
                        operate: false,
                        formatter: function (value, row) {
                            return '<a href="javascript:;" class="btn btn-xs btn-info btn-check-token" data-id="' + row.id + '">检测</a> ' +
                                '<a href="javascript:;" class="btn btn-xs btn-warning btn-refresh-token" data-id="' + row.id + '">刷新</a>';
                        }
                    },
                    {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                ]]
            });

            $(document).off('click', '.btn-check-token').on('click', '.btn-check-token', function () {
                var id = Number($(this).data('id') || 0);
                if (!id) return false;
                Backend.api.ajax({url: 'platform/cloudaccount/checktoken', data: {id: id}}, function (ret) {
                    Backend.api.toastr.success(ret.msg || '检测完成');
                    table.bootstrapTable('refresh');
                    return false;
                });
            });

            $(document).off('click', '.btn-refresh-token').on('click', '.btn-refresh-token', function () {
                var id = Number($(this).data('id') || 0);
                if (!id) return false;
                Backend.api.ajax({url: 'platform/cloudaccount/refreshtoken', data: {id: id}}, function (ret) {
                    Backend.api.toastr.success(ret.msg || '刷新完成');
                    table.bootstrapTable('refresh');
                    return false;
                });
            });

            Table.api.bindevent(table);
        },
        add: function () {
            Form.api.bindevent($('form[role=form]'));
        },
        edit: function () {
            Form.api.bindevent($('form[role=form]'));
        }
    };
    return Controller;
});
