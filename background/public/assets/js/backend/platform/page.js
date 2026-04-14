define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/page/index',
                    add_url: 'platform/page/add',
                    edit_url: 'platform/page/edit',
                    del_url: 'platform/page/del',
                    multi_url: 'platform/page/multi'
                }
            });
            var table = $("#table");
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                columns: [
                    [
                        {field: 'state', checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'terminal', title: __('Terminal'), searchList: {"common": __('Common'), "client": __('Client'), "webset": __('Webset')}},
                        {field: 'page_key', title: __('Page_key'), operate: 'LIKE'},
                        {field: 'title', title: __('Title'), operate: 'LIKE'},
                        {field: 'version', title: __('Version')},
                        {field: 'weigh', title: __('Weigh')},
                        {field: 'status', title: __('Status'), searchList: {"normal": __('Normal'), "hidden": __('Hidden')}, formatter: Table.api.formatter.status},
                        {field: 'updatetime', title: __('Updatetime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange'},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });
            $(document).off('click', '.btn-sync-menu-zh').on('click', '.btn-sync-menu-zh', function () {
                Backend.api.ajax({
                    url: 'platform/page/syncmenuchinese'
                }, function () {
                    table.bootstrapTable('refresh');
                    return false;
                });
            });
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindRegisterCaptchaConfig($("form[role=form]"));
            Form.api.bindevent($("form[role=form]"));
        },
        edit: function () {
            Controller.api.bindRegisterCaptchaConfig($("form[role=form]"));
            Form.api.bindevent($("form[role=form]"));
        },
        api: {
            bindRegisterCaptchaConfig: function (form) {
                var pageKey = form.find('input[name="row[page_key]"]');
                var terminalField = form.find('[name="row[terminal]"]');
                var configField = form.find('textarea[name="row[config_json]"]');
                var required = form.find('#register-captcha-required');
                var channel = form.find('#register-captcha-channel');
                var eventInput = form.find('#register-captcha-event');
                var layoutBrandName = form.find('#layout-brand-name');
                var layoutBrandSub = form.find('#layout-brand-sub');
                var layoutBrandLogo = form.find('#layout-brand-logo');
                var layoutAnonEnabled = form.find('#layout-anon-enabled');
                var layoutAnonText = form.find('#layout-anon-text');
                var headerShowDownload = form.find('#header-show-download');
                var headerDownloadText = form.find('#header-download-text');
                var headerAccountAuthed = form.find('#header-account-authed');
                var headerAccountLogin = form.find('#header-account-login');
                var authbarEnabled = form.find('#authbar-enabled');
                var authbarShowRefresh = form.find('#authbar-show-refresh');
                var themePageBg = form.find('#theme-page-bg');
                var themeSidebarBg = form.find('#theme-sidebar-bg');
                var themeCardBg = form.find('#theme-card-bg');
                var themeCardBorder = form.find('#theme-card-border');
                var themeTextMain = form.find('#theme-text-main');
                var themeTextSub = form.find('#theme-text-sub');
                var themeBtnBg = form.find('#theme-btn-bg');
                var themeBtnBorder = form.find('#theme-btn-border');
                var descEnabled = form.find('#desc-enabled');
                var descText = form.find('#desc-text');
                var descPosition = form.find('#desc-position');
                var descBg = form.find('#desc-bg');
                var descColor = form.find('#desc-color');
                var descBorder = form.find('#desc-border');
                var descAlign = form.find('#desc-align');
                var repairEnabled = form.find('#repair-enabled');
                var repairButtonText = form.find('#repair-button-text');
                var repairPackageUrl = form.find('#repair-package-url');
                var repairExecutablePath = form.find('#repair-executable-path');
                var repairLaunchArgs = form.find('#repair-launch-args');
                var repairSilent = form.find('#repair-silent');
                var repairActionKey = form.find('#repair-action-key');
                var presetTemplate = form.find('#preset-template');
                var applyPresetButton = form.find('#apply-preset-template');
                var isLauncherSchemaKey = function (key) {
                    return String(key || '') === 'launcher_schema';
                };
                var isLauncherPresetKey = function (key) {
                    return /^launcher_preset/i.test(String(key || ''));
                };
                var supportsLauncherConfig = function (key) {
                    return isLauncherSchemaKey(key) || isLauncherPresetKey(key);
                };
                var presets = {
                    official_dark: {
                        layout: {brand_name: 'Systema Play', brand_sub: 'Game Launcher', brand_logo_text: 'S', anonymous_tip_enabled: true, anonymous_tip_text: '当前处于匿名模式，可浏览商城和公开内容，账户功能请先登录。'},
                        header: {show_download_button: true, download_button_text: '下载管理', account_text_authed: '账户中心', account_text_login: '登录'},
                        auth_bar: {enabled: true, show_refresh_button: true},
                        theme: {
                            page_background: 'linear-gradient(135deg, #0a1120, #101a30 45%, #0f1f3a)',
                            sidebar_background: 'rgba(7, 14, 28, 0.9)',
                            card_background: '#16233c',
                            card_border: '#2f4369',
                            text_color: '#eaf0fb',
                            sub_text_color: '#89a3cc',
                            button_background: '#101d34',
                            button_border: '#3a4e75'
                        },
                        description_box: {enabled: false, text: '', position: 'before_content', background: '', color: '', border_color: '', text_align: ''},
                        repair: {enabled: true, button_text: '一键修复', package_url: '', executable_path: '', launch_args: '/quiet /norestart', silent_install: true, report_action_key: 'dx_repair'}
                    },
                    festival_notice: {
                        layout: {brand_name: 'Systema 活动站', brand_sub: 'Festival Edition', brand_logo_text: 'F', anonymous_tip_enabled: true, anonymous_tip_text: '活动期间可匿名浏览，参与奖励请先登录。'},
                        header: {show_download_button: true, download_button_text: '活动资源下载', account_text_authed: '我的活动中心', account_text_login: '登录参与'},
                        auth_bar: {enabled: true, show_refresh_button: true},
                        theme: {
                            page_background: 'linear-gradient(120deg, #2a0d28, #441f43 48%, #1b2d57)',
                            sidebar_background: 'rgba(37, 10, 39, 0.9)',
                            card_background: '#2a1f46',
                            card_border: '#6b4dc5',
                            text_color: '#f6ebff',
                            sub_text_color: '#d8bff6',
                            button_background: '#5f3dc4',
                            button_border: '#8c6df0'
                        },
                        description_box: {enabled: true, text: '限时活动进行中：完成下载与安装后可领取活动礼包。', position: 'top', background: 'rgba(95,61,196,0.22)', color: '#f3e8ff', border_color: '#8f71f2', text_align: 'left'},
                        repair: {enabled: true, button_text: '活动环境修复', package_url: '', executable_path: '', launch_args: '/quiet /norestart', silent_install: true, report_action_key: 'event_repair'}
                    },
                    minimal_fast: {
                        layout: {brand_name: 'Systema Lite', brand_sub: 'Fast Access', brand_logo_text: 'L', anonymous_tip_enabled: false, anonymous_tip_text: ''},
                        header: {show_download_button: true, download_button_text: '下载', account_text_authed: '账户', account_text_login: '登录'},
                        auth_bar: {enabled: false, show_refresh_button: false},
                        theme: {
                            page_background: '#111827',
                            sidebar_background: '#0f172a',
                            card_background: '#1f2937',
                            card_border: '#334155',
                            text_color: '#f8fafc',
                            sub_text_color: '#94a3b8',
                            button_background: '#1d4ed8',
                            button_border: '#2563eb'
                        },
                        description_box: {enabled: false, text: '', position: 'before_content', background: '', color: '', border_color: '', text_align: ''},
                        repair: {enabled: true, button_text: '快速修复', package_url: '', executable_path: '', launch_args: '/quiet /norestart', silent_install: true, report_action_key: 'quick_repair'}
                    }
                };
                var renderPresetOptions = function (remoteList) {
                    var html = ['<option value="">不使用预设</option>'];
                    Object.keys(presets).forEach(function (key) {
                        var name = key;
                        if (key === 'official_dark') {
                            name = '官方暗色主题';
                        } else if (key === 'festival_notice') {
                            name = '活动公告主题';
                        } else if (key === 'minimal_fast') {
                            name = '极简高效主题';
                        }
                        html.push('<option value="' + key + '">[系统] ' + name + '</option>');
                    });
                    (remoteList || []).forEach(function (item) {
                        if (!item || !item.page_key) {
                            return;
                        }
                        html.push('<option value="' + item.page_key + '">[模板库] ' + (item.title || item.page_key) + '</option>');
                        presets[item.page_key] = item.config || {};
                    });
                    presetTemplate.html(html.join(''));
                };
                var loadPresetLibrary = function () {
                    Backend.api.ajax({
                        url: 'platform/page/presetlist',
                        data: {terminal: String(terminalField.val() || 'client')}
                    }, function (data) {
                        renderPresetOptions(data || []);
                        return false;
                    }, function () {
                        renderPresetOptions([]);
                        return false;
                    });
                };
                var readConfig = function () {
                    try {
                        var parsed = JSON.parse(configField.val() || '{}');
                        return parsed && typeof parsed === 'object' ? parsed : {};
                    } catch (e) {
                        return {};
                    }
                };
                var writeConfig = function () {
                    var cfg = readConfig();
                    if (!cfg.register || typeof cfg.register !== 'object') {
                        cfg.register = {};
                    }
                    cfg.register.captcha_required = String(required.val()) === '1';
                    cfg.register.captcha_channel = String(channel.val() || 'sms');
                    cfg.register.captcha_event = String(eventInput.val() || 'register');
                    if (!cfg.layout || typeof cfg.layout !== 'object') {
                        cfg.layout = {};
                    }
                    cfg.layout.brand_name = String(layoutBrandName.val() || 'Systema Play');
                    cfg.layout.brand_sub = String(layoutBrandSub.val() || 'Game Launcher');
                    cfg.layout.brand_logo_text = String(layoutBrandLogo.val() || 'S');
                    cfg.layout.anonymous_tip_enabled = String(layoutAnonEnabled.val() || '1') === '1';
                    cfg.layout.anonymous_tip_text = String(layoutAnonText.val() || '当前处于匿名模式，可浏览商城和公开内容，账户功能请先登录。');
                    if (!cfg.header || typeof cfg.header !== 'object') {
                        cfg.header = {};
                    }
                    cfg.header.show_download_button = String(headerShowDownload.val() || '1') === '1';
                    cfg.header.download_button_text = String(headerDownloadText.val() || '下载管理');
                    cfg.header.account_text_authed = String(headerAccountAuthed.val() || '账户中心');
                    cfg.header.account_text_login = String(headerAccountLogin.val() || '登录');
                    if (!cfg.auth_bar || typeof cfg.auth_bar !== 'object') {
                        cfg.auth_bar = {};
                    }
                    cfg.auth_bar.enabled = String(authbarEnabled.val() || '1') === '1';
                    cfg.auth_bar.show_refresh_button = String(authbarShowRefresh.val() || '1') === '1';
                    if (!cfg.theme || typeof cfg.theme !== 'object') {
                        cfg.theme = {};
                    }
                    cfg.theme.page_background = String(themePageBg.val() || '');
                    cfg.theme.sidebar_background = String(themeSidebarBg.val() || '');
                    cfg.theme.card_background = String(themeCardBg.val() || '');
                    cfg.theme.card_border = String(themeCardBorder.val() || '');
                    cfg.theme.text_color = String(themeTextMain.val() || '');
                    cfg.theme.sub_text_color = String(themeTextSub.val() || '');
                    cfg.theme.button_background = String(themeBtnBg.val() || '');
                    cfg.theme.button_border = String(themeBtnBorder.val() || '');
                    if (!cfg.description_box || typeof cfg.description_box !== 'object') {
                        cfg.description_box = {};
                    }
                    cfg.description_box.enabled = String(descEnabled.val() || '1') === '1';
                    cfg.description_box.text = String(descText.val() || '');
                    cfg.description_box.position = String(descPosition.val() || 'before_content');
                    cfg.description_box.background = String(descBg.val() || '');
                    cfg.description_box.color = String(descColor.val() || '');
                    cfg.description_box.border_color = String(descBorder.val() || '');
                    cfg.description_box.text_align = String(descAlign.val() || '');
                    if (!cfg.repair || typeof cfg.repair !== 'object') {
                        cfg.repair = {};
                    }
                    cfg.repair.enabled = String(repairEnabled.val() || '1') === '1';
                    cfg.repair.button_text = String(repairButtonText.val() || '一键修复');
                    cfg.repair.package_url = String(repairPackageUrl.val() || '');
                    cfg.repair.executable_path = String(repairExecutablePath.val() || '');
                    cfg.repair.launch_args = String(repairLaunchArgs.val() || '');
                    cfg.repair.silent_install = String(repairSilent.val() || '1') === '1';
                    cfg.repair.report_action_key = String(repairActionKey.val() || 'dx_repair');
                    configField.val(JSON.stringify(cfg));
                };
                var mergeObject = function (target, patch) {
                    if (!patch || typeof patch !== 'object') {
                        return target;
                    }
                    var base = target && typeof target === 'object' ? target : {};
                    Object.keys(patch).forEach(function (key) {
                        var value = patch[key];
                        if (value && typeof value === 'object' && !Array.isArray(value)) {
                            base[key] = mergeObject(base[key], value);
                        } else {
                            base[key] = value;
                        }
                    });
                    return base;
                };
                var applyPreset = function (key) {
                    if (!key || !presets[key]) {
                        return;
                    }
                    var cfg = readConfig();
                    cfg = mergeObject(cfg, presets[key]);
                    configField.val(JSON.stringify(cfg));
                    applyForm();
                };
                var applyForm = function () {
                    var cfg = readConfig();
                    var register = cfg.register && typeof cfg.register === 'object' ? cfg.register : {};
                    var layout = cfg.layout && typeof cfg.layout === 'object' ? cfg.layout : {};
                    var header = cfg.header && typeof cfg.header === 'object' ? cfg.header : {};
                    var authBar = cfg.auth_bar && typeof cfg.auth_bar === 'object' ? cfg.auth_bar : {};
                    var theme = cfg.theme && typeof cfg.theme === 'object' ? cfg.theme : {};
                    var desc = cfg.description_box && typeof cfg.description_box === 'object' ? cfg.description_box : {};
                    var repair = cfg.repair && typeof cfg.repair === 'object' ? cfg.repair : {};
                    required.val(register.captcha_required ? '1' : '0');
                    channel.val(register.captcha_channel || 'sms');
                    eventInput.val(register.captcha_event || 'register');
                    layoutBrandName.val(layout.brand_name || 'Systema Play');
                    layoutBrandSub.val(layout.brand_sub || 'Game Launcher');
                    layoutBrandLogo.val(layout.brand_logo_text || 'S');
                    layoutAnonEnabled.val(layout.anonymous_tip_enabled === false ? '0' : '1');
                    layoutAnonText.val(layout.anonymous_tip_text || '当前处于匿名模式，可浏览商城和公开内容，账户功能请先登录。');
                    headerShowDownload.val(header.show_download_button === false ? '0' : '1');
                    headerDownloadText.val(header.download_button_text || '下载管理');
                    headerAccountAuthed.val(header.account_text_authed || '账户中心');
                    headerAccountLogin.val(header.account_text_login || '登录');
                    authbarEnabled.val(authBar.enabled === false ? '0' : '1');
                    authbarShowRefresh.val(authBar.show_refresh_button === false ? '0' : '1');
                    themePageBg.val(theme.page_background || 'linear-gradient(135deg, #0a1120, #101a30 45%, #0f1f3a)');
                    themeSidebarBg.val(theme.sidebar_background || 'rgba(7, 14, 28, 0.9)');
                    themeCardBg.val(theme.card_background || '#16233c');
                    themeCardBorder.val(theme.card_border || '#2f4369');
                    themeTextMain.val(theme.text_color || '#eaf0fb');
                    themeTextSub.val(theme.sub_text_color || '#89a3cc');
                    themeBtnBg.val(theme.button_background || '#101d34');
                    themeBtnBorder.val(theme.button_border || '#3a4e75');
                    descEnabled.val(desc.enabled === false ? '0' : '1');
                    descText.val(desc.text || '');
                    descPosition.val(desc.position || 'before_content');
                    descBg.val(desc.background || '');
                    descColor.val(desc.color || '');
                    descBorder.val(desc.border_color || '');
                    descAlign.val(desc.text_align || '');
                    repairEnabled.val(repair.enabled === false ? '0' : '1');
                    repairButtonText.val(repair.button_text || '一键修复');
                    repairPackageUrl.val(repair.package_url || '');
                    repairExecutablePath.val(repair.executable_path || '');
                    repairLaunchArgs.val(repair.launch_args || '/quiet /norestart');
                    repairSilent.val(repair.silent_install === false ? '0' : '1');
                    repairActionKey.val(repair.report_action_key || 'dx_repair');
                };
                var syncVisibility = function () {
                    var key = String(pageKey.val() || '');
                    var enabled = supportsLauncherConfig(key);
                    var schemaMode = isLauncherSchemaKey(key);
                    required.closest('.form-group').toggle(enabled);
                    channel.closest('.form-group').toggle(enabled);
                    eventInput.closest('.form-group').toggle(enabled);
                    layoutBrandName.closest('.form-group').toggle(enabled);
                    layoutBrandSub.closest('.form-group').toggle(enabled);
                    layoutBrandLogo.closest('.form-group').toggle(enabled);
                    layoutAnonEnabled.closest('.form-group').toggle(enabled);
                    layoutAnonText.closest('.form-group').toggle(enabled);
                    headerShowDownload.closest('.form-group').toggle(enabled);
                    headerDownloadText.closest('.form-group').toggle(enabled);
                    headerAccountAuthed.closest('.form-group').toggle(enabled);
                    headerAccountLogin.closest('.form-group').toggle(enabled);
                    authbarEnabled.closest('.form-group').toggle(enabled);
                    authbarShowRefresh.closest('.form-group').toggle(enabled);
                    themePageBg.closest('.form-group').toggle(enabled);
                    themeSidebarBg.closest('.form-group').toggle(enabled);
                    themeCardBg.closest('.form-group').toggle(enabled);
                    themeCardBorder.closest('.form-group').toggle(enabled);
                    themeTextMain.closest('.form-group').toggle(enabled);
                    themeTextSub.closest('.form-group').toggle(enabled);
                    themeBtnBg.closest('.form-group').toggle(enabled);
                    themeBtnBorder.closest('.form-group').toggle(enabled);
                    descEnabled.closest('.form-group').toggle(enabled);
                    descText.closest('.form-group').toggle(enabled);
                    descPosition.closest('.form-group').toggle(enabled);
                    descBg.closest('.form-group').toggle(enabled);
                    descColor.closest('.form-group').toggle(enabled);
                    descBorder.closest('.form-group').toggle(enabled);
                    descAlign.closest('.form-group').toggle(enabled);
                    repairEnabled.closest('.form-group').toggle(enabled);
                    repairButtonText.closest('.form-group').toggle(enabled);
                    repairPackageUrl.closest('.form-group').toggle(enabled);
                    repairExecutablePath.closest('.form-group').toggle(enabled);
                    repairLaunchArgs.closest('.form-group').toggle(enabled);
                    repairSilent.closest('.form-group').toggle(enabled);
                    repairActionKey.closest('.form-group').toggle(enabled);
                    presetTemplate.closest('.form-group').toggle(schemaMode);
                };
                applyForm();
                loadPresetLibrary();
                syncVisibility();
                pageKey.on('input change', syncVisibility);
                terminalField.on('change', loadPresetLibrary);
                configField.on('input change', applyForm);
                required.on('change', writeConfig);
                channel.on('change', writeConfig);
                eventInput.on('input change', writeConfig);
                layoutBrandName.on('input change', writeConfig);
                layoutBrandSub.on('input change', writeConfig);
                layoutBrandLogo.on('input change', writeConfig);
                layoutAnonEnabled.on('change', writeConfig);
                layoutAnonText.on('input change', writeConfig);
                headerShowDownload.on('change', writeConfig);
                headerDownloadText.on('input change', writeConfig);
                headerAccountAuthed.on('input change', writeConfig);
                headerAccountLogin.on('input change', writeConfig);
                authbarEnabled.on('change', writeConfig);
                authbarShowRefresh.on('change', writeConfig);
                themePageBg.on('input change', writeConfig);
                themeSidebarBg.on('input change', writeConfig);
                themeCardBg.on('input change', writeConfig);
                themeCardBorder.on('input change', writeConfig);
                themeTextMain.on('input change', writeConfig);
                themeTextSub.on('input change', writeConfig);
                themeBtnBg.on('input change', writeConfig);
                themeBtnBorder.on('input change', writeConfig);
                descEnabled.on('change', writeConfig);
                descText.on('input change', writeConfig);
                descPosition.on('change', writeConfig);
                descBg.on('input change', writeConfig);
                descColor.on('input change', writeConfig);
                descBorder.on('input change', writeConfig);
                descAlign.on('change', writeConfig);
                repairEnabled.on('change', writeConfig);
                repairButtonText.on('input change', writeConfig);
                repairPackageUrl.on('input change', writeConfig);
                repairExecutablePath.on('input change', writeConfig);
                repairLaunchArgs.on('input change', writeConfig);
                repairSilent.on('change', writeConfig);
                repairActionKey.on('input change', writeConfig);
                applyPresetButton.on('click', function () {
                    applyPreset(String(presetTemplate.val() || ''));
                    writeConfig();
                });
                form.on('submit', function () {
                    writeConfig();
                });
            }
        }
    };
    return Controller;
});
