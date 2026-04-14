define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var normalizeAccountList = function (data) {
        if ($.isArray(data)) {
            return data;
        }
        var list = [];
        $.each(data || {}, function (_, item) {
            list.push(item);
        });
        return list;
    };

    var formatAccountStatus = function (status) {
        var map = {
            normal: '开启',
            limited: '限速',
            hidden: '隐藏'
        };
        return map[status] || status || '-';
    };

    var buildAccountOptionText = function (item) {
        return '账号#' + item.id + ' ' + (item.username || '-') + '（' + formatAccountStatus(item.status) + '）';
    };

    var findDownloadUrlInObject = function (input) {
        if (!input) {
            return '';
        }
        if (typeof input === 'string') {
            return $.trim(input);
        }
        if ($.isArray(input)) {
            for (var i = 0; i < input.length; i++) {
                var arrayUrl = findDownloadUrlInObject(input[i]);
                if (arrayUrl) {
                    return arrayUrl;
                }
            }
            return '';
        }
        var directKeys = ['download_url', 'fileDownloadUrl', 'downloadUrl', 'url'];
        for (var j = 0; j < directKeys.length; j++) {
            var value = input[directKeys[j]];
            if (typeof value === 'string' && $.trim(value)) {
                return $.trim(value);
            }
        }
        for (var key in input) {
            if (!Object.prototype.hasOwnProperty.call(input, key)) {
                continue;
            }
            if (typeof input[key] === 'object' && input[key] !== null) {
                var nestedUrl = findDownloadUrlInObject(input[key]);
                if (nestedUrl) {
                    return nestedUrl;
                }
            }
        }
        return '';
    };

    var fillDownloadUrl = function (layero, data, ret) {
        var url = findDownloadUrlInObject(data) || findDownloadUrlInObject(ret && ret.data) || '';
        $(layero).find('#cloud-download-url').val(url);
    };

    var openDownloadDialog = function (row) {

        Backend.api.ajax({url: 'platform/cloudfile/accountoptions'}, function (data, ret) {
            var list = normalizeAccountList(data || []);

            var options = ['<option value="">按规则自动选择</option>'];
            var accountTips = ['<div class="help-block" style="margin-bottom:8px;">可用账号：</div>'];
            for (var i = 0; i < list.length; i++) {
                options.push('<option value="' + list[i].id + '">' + buildAccountOptionText(list[i]) + '</option>');
                accountTips.push('<div class="help-block" style="margin:2px 0;">' + buildAccountOptionText(list[i]) + '</div>');
            }
            if (!list.length) {
                accountTips.push('<div class="help-block text-danger" style="margin:2px 0;">当前没有可用账号</div>');
            }
            var html = '' +
                '<div style="padding:12px;">' +
                '<div class="form-group"><label>选择账号</label><select id="cloud-download-account" class="form-control">' + options.join('') + '</select>' + accountTips.join('') + '</div>' +
                '<div class="form-group"><button id="cloud-fetch-url" class="btn btn-primary">获取下载地址</button></div>' +
                '<div class="form-group"><label>下载地址</label><textarea id="cloud-download-url" class="form-control" rows="4" readonly></textarea></div>' +
                '</div>';
            layer.open({
                type: 1,
                title: '天翼云下载 - ' + (row.name || ''),
                area: ['700px', '420px'],
                content: html,
                success: function (layero) {
                    $(layero).off('click', '#cloud-fetch-url').on('click', '#cloud-fetch-url', function () {
                        var accountId = Number($(layero).find('#cloud-download-account').val() || 0);
                        Backend.api.ajax({
                            url: 'platform/cloudfile/previewdownload',
                            data: {file_id: row.id, account_id: accountId}
                        }, function (data, ret) {
                            fillDownloadUrl(layero, data, ret);
                            return false;
                        });


                    });
                }
            });
            return false;
        });
    };


    var formatFileSizeMb = function (value) {
        var size = Number(value || 0);
        if (!isFinite(size) || size <= 0) {
            return '0 MB';
        }
        var mb = size / 1024 / 1024;
        return mb.toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1') + ' MB';
    };

    var fillCloudInfoPreview = function (form, data) {
        form.find('[data-cloudfile-name]').val(data.name || '');
        form.find('[data-cloudfile-size]').val(formatFileSizeMb(data.file_size));
        var text = '已从云盘读取文件信息';
        if (data.account_username) {
            text += '，使用账号：' + data.account_username;
        }
        form.find('[data-cloudfile-source]').text(text);
    };

    var clearCloudInfoPreview = function (form) {
        form.find('[data-cloudfile-name]').val('');
        form.find('[data-cloudfile-size]').val('');
        form.find('[data-cloudfile-source]').text('分享信息已变更，请重新读取；保存时系统也会自动再次校验并更新文件名和大小。');
    };

    var initCloudInfoPreview = function (form) {
        form.find('[data-cloudfile-size]').each(function () {
            var bytes = $(this).data('bytes');
            if (bytes !== undefined && bytes !== '') {
                $(this).val(formatFileSizeMb(bytes));
            }
        });
    };


    var bindCloudInfoFetcher = function (form) {
        form.off('click.cloudfile', '.btn-fetch-cloudfile-info').on('click.cloudfile', '.btn-fetch-cloudfile-info', function () {
            Backend.api.ajax({
                url: 'platform/cloudfile/fetchinfo',
                data: form.serialize()
            }, function (data, ret) {
                fillCloudInfoPreview(form, data || {});
                return false;
            });

            return false;
        });

        form.off('input.cloudfile change.cloudfile', '[name="row[share_code]"], [name="row[access_code]"], [name="row[account_ids]"], [name="row[account_rule]"]')
            .on('input.cloudfile change.cloudfile', '[name="row[share_code]"], [name="row[access_code]"], [name="row[account_ids]"], [name="row[account_rule]"]', function () {
                clearCloudInfoPreview(form);
            });
    };

    var getGameName = function (value) {
        var gameList = Config.gameList || {};
        return gameList[value] || gameList[String(value)] || value || '';
    };

    var Controller = {

        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/cloudfile/index',
                    add_url: 'platform/cloudfile/add',
                    edit_url: 'platform/cloudfile/edit',
                    del_url: 'platform/cloudfile/del',
                    multi_url: 'platform/cloudfile/multi'
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
                    {field: 'name', title: '文件名', operate: 'LIKE'},
                    {field: 'remark', title: '文件备注', operate: 'LIKE'},
                    {field: 'file_size', title: '文件大小(MB)', formatter: function (value) { return formatFileSizeMb(value); }},
                    {field: 'game_id', title: '归属游戏', searchList: Config.gameList || {}, formatter: function (value) { return getGameName(value); }},
                    {field: 'createtime', title: '文件添加时间', formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange'},

                    {
                        field: 'custom_operate',
                        title: '操作',
                        operate: false,
                        formatter: function (value, row) {
                            return '<a href="javascript:;" class="btn btn-xs btn-success btn-cloud-download" data-id="' + row.id + '">下载</a> ' +
                                '<a href="javascript:;" class="btn btn-xs btn-warning btn-cloud-refresh" data-id="' + row.id + '">刷新</a>';
                        }
                    },
                    {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                ]]
            });

            $(document).off('click', '.btn-cloud-download').on('click', '.btn-cloud-download', function () {
                var id = Number($(this).data('id') || 0);
                if (!id) return false;
                var rows = table.bootstrapTable('getData') || [];
                var row = null;
                for (var i = 0; i < rows.length; i++) {
                    if (Number(rows[i].id) === id) {
                        row = rows[i];
                        break;
                    }
                }
                if (!row) return false;
                openDownloadDialog(row);
            });

            $(document).off('click', '.btn-cloud-refresh').on('click', '.btn-cloud-refresh', function () {
                table.bootstrapTable('refresh');
            });



            Table.api.bindevent(table);
        },
        add: function () {
            var form = $('form[role=form]');
            initCloudInfoPreview(form);
            bindCloudInfoFetcher(form);
            Form.api.bindevent(form);
        },
        edit: function () {
            var form = $('form[role=form]');
            initCloudInfoPreview(form);
            bindCloudInfoFetcher(form);
            Form.api.bindevent(form);
        }
    };
    return Controller;
});

