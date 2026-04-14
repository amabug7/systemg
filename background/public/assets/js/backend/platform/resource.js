define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var bindAria2Options = function () {
        var form = $("form[role=form]");
        if (!form.length) {
            return;
        }
        var extraField = form.find("textarea[name='row[extra_json]']");
        if (!extraField.length) {
            return;
        }
        var connInput = form.find('#aria2-max-conn');
        var splitInput = form.find('#aria2-split');
        var minSplitInput = form.find('#aria2-min-split-size');
        var archiveTypeInput = form.find('#pkg-archive-type');
        var extractDirInput = form.find('#pkg-extract-dir');
        var installDirInput = form.find('#pkg-install-dir');
        var deployModeInput = form.find('#pkg-deploy-mode');
        var keepBackupsInput = form.find('#pkg-keep-backups');
        var extra = {};
        try {
            extra = JSON.parse(extraField.val() || '{}');
        } catch (e) {
            extra = {};
        }
        connInput.val(extra.aria2_max_connection_per_server || connInput.val() || 8);
        splitInput.val(extra.aria2_split || splitInput.val() || 8);
        minSplitInput.val(extra.aria2_min_split_size || minSplitInput.val() || '4M');
        archiveTypeInput.val(extra.archive_type || archiveTypeInput.val() || 'auto');
        extractDirInput.val(extra.extract_dir || extractDirInput.val() || '');
        installDirInput.val(extra.install_dir || installDirInput.val() || '');
        deployModeInput.val(extra.deploy_mode || deployModeInput.val() || 'atomic');
        keepBackupsInput.val(extra.keep_backups || keepBackupsInput.val() || 2);
        form.on('submit', function () {
            var latest = {};
            try {
                latest = JSON.parse(extraField.val() || '{}');
            } catch (e) {
                latest = {};
            }
            latest.aria2_max_connection_per_server = Number(connInput.val() || 8);
            latest.aria2_split = Number(splitInput.val() || 8);
            latest.aria2_min_split_size = String(minSplitInput.val() || '4M');
            latest.archive_type = String(archiveTypeInput.val() || 'auto');
            latest.extract_dir = String(extractDirInput.val() || '');
            latest.install_dir = String(installDirInput.val() || '');
            latest.deploy_mode = String(deployModeInput.val() || 'atomic');
            latest.keep_backups = Number(keepBackupsInput.val() || 2);
            latest.auto_extract = true;
            latest.verify_hash = true;
            extraField.val(JSON.stringify(latest));
        });
    };

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/resource/index',
                    add_url: 'platform/resource/add',
                    edit_url: 'platform/resource/edit',
                    del_url: 'platform/resource/del',
                    multi_url: 'platform/resource/multi'
                }
            });
            var table = $("#table");
            var indexUrl = $.fn.bootstrapTable.defaults.extend.index_url;
            var ref = (Backend.api && Backend.api.query) ? Backend.api.query('ref') : '';
            if (ref === 'direct') {
                var directFilter = encodeURIComponent(JSON.stringify({channel_key: 'direct,url'}));
                var directOp = encodeURIComponent(JSON.stringify({channel_key: 'IN'}));
                indexUrl += (indexUrl.indexOf('?') > -1 ? '&' : '?') + 'filter=' + directFilter + '&op=' + directOp;
            }
            table.bootstrapTable({
                url: indexUrl,
                pk: 'id',
                sortName: 'weigh',
                columns: [
                    [
                        {field: 'state', checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'game_id', title: __('Game_id')},
                        {field: 'resource_type', title: __('Resource_type'), searchList: {"game": __('Game'), "mod": __('Mod'), "plugin": __('Plugin'), "patch": __('Patch'), "tool": __('Tool')}},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'version', title: __('Version'), operate: 'LIKE'},
                        {field: 'channel_key', title: __('Channel_key'), operate: 'LIKE'},
                        {field: 'priority', title: __('Priority')},
                        {field: 'weigh', title: __('Weigh')},
                        {field: 'status', title: __('Status'), searchList: {"normal": __('Normal'), "hidden": __('Hidden')}, formatter: Table.api.formatter.status},
                        {field: 'updatetime', title: __('Updatetime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange'},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });
            Table.api.bindevent(table);
        },
        add: function () {
            bindAria2Options();
            Form.api.bindevent($("form[role=form]"));
        },
        edit: function () {
            bindAria2Options();
            Form.api.bindevent($("form[role=form]"));
        }
    };
    return Controller;
});
