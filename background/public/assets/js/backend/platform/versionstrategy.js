define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var bindHotUpdateConfig = function () {
        var form = $("form[role=form]");
        if (!form.length) {
            return;
        }
        var switchField = form.find("textarea[name='row[switch_json]']");
        if (!switchField.length) {
            return;
        }
        var enabled = form.find('#hot-enabled');
        var targetVersion = form.find('#hot-target-version');
        var packageUrl = form.find('#hot-package-url');
        var sha256 = form.find('#hot-sha256');
        var archiveType = form.find('#hot-archive-type');
        var extractSubdir = form.find('#hot-extract-subdir');
        var force = form.find('#hot-force');
        var grayRatio = form.find('#hot-gray-ratio');
        var grayGroupTag = form.find('#hot-gray-group-tag');
        var sw = {};
        try {
            sw = JSON.parse(switchField.val() || '{}');
        } catch (e) {
            sw = {};
        }
        var hot = sw.hot_update || {};
        enabled.val(hot.enabled ? '1' : '0');
        targetVersion.val(hot.target_version || '');
        packageUrl.val(hot.package_url || '');
        sha256.val(hot.sha256 || '');
        archiveType.val(hot.archive_type || 'zip');
        extractSubdir.val(hot.extract_subdir || '');
        force.val(hot.force ? '1' : '0');
        grayRatio.val(hot.gray_ratio || 0);
        grayGroupTag.val(hot.gray_group_tag || '');
        form.on('submit', function () {
            var latest = {};
            try {
                latest = JSON.parse(switchField.val() || '{}');
            } catch (e) {
                latest = {};
            }
            if (latest.hot_update && typeof latest.hot_update === 'object') {
                latest.hot_update_prev = latest.hot_update;
            }
            latest.hot_update = {
                enabled: String(enabled.val()) === '1',
                target_version: String(targetVersion.val() || ''),
                package_url: String(packageUrl.val() || ''),
                sha256: String(sha256.val() || '').toLowerCase(),
                archive_type: String(archiveType.val() || 'zip'),
                extract_subdir: String(extractSubdir.val() || ''),
                force: String(force.val()) === '1',
                gray_ratio: Number(grayRatio.val() || 0),
                gray_group_tag: String(grayGroupTag.val() || '')
            };
            switchField.val(JSON.stringify(latest));
        });
    };

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/versionstrategy/index',
                    add_url: 'platform/versionstrategy/add',
                    edit_url: 'platform/versionstrategy/edit',
                    del_url: 'platform/versionstrategy/del',
                    multi_url: 'platform/versionstrategy/multi'
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
                    {field: 'terminal', title: __('Terminal'), operate: 'LIKE'},
                    {field: 'min_version', title: __('Min_version'), operate: 'LIKE'},
                    {field: 'latest_version', title: __('Latest_version'), operate: 'LIKE'},
                    {field: 'force_version', title: __('Force_version'), operate: 'LIKE'},
                    {field: 'gray_ratio', title: __('Gray_ratio')},
                    {field: 'gray_group_tag', title: __('Gray_group_tag'), operate: 'LIKE'},
                    {field: 'status', title: __('Status'), searchList: {"normal": __('Normal'), "hidden": __('Hidden')}, formatter: Table.api.formatter.status},
                    {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                ]]
            });
            $(document).off('click', '.btn-rollback-hot').on('click', '.btn-rollback-hot', function () {
                var ids = Table.api.selectedids(table);
                if (!ids.length) {
                    Backend.api.toastr.error('请先选择一条记录');
                    return false;
                }
                Backend.api.ajax({
                    url: 'platform/versionstrategy/rollbackhot',
                    data: {ids: ids.join(',')}
                }, function () {
                    table.bootstrapTable('refresh');
                    return false;
                });
            });
            Table.api.bindevent(table);
        },
        add: function () { bindHotUpdateConfig(); Form.api.bindevent($("form[role=form]")); },
        edit: function () { bindHotUpdateConfig(); Form.api.bindevent($("form[role=form]")); }
    };
    return Controller;
});
