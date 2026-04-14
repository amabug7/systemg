define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var getLayer = function () {
        return window.layer || (window.parent && window.parent.layer) || null;
    };

    var esc = function (v) {
        return String(v || '').replace(/[&<>"']/g, function (s) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[s];
        });
    };

    var parseFileSize = function (v) {
        var n = Number(v || 0);
        if (!isFinite(n) || n <= 0) return '';
        if (n >= 1024 * 1024 * 1024) return (n / 1024 / 1024 / 1024).toFixed(2) + 'GB';
        if (n >= 1024 * 1024) return (n / 1024 / 1024).toFixed(2) + 'MB';
        if (n >= 1024) return (n / 1024).toFixed(2) + 'KB';
        return n + 'B';
    };

    var toFileSizeNumber = function (v) {
        var n = Number(v || 0);
        return isFinite(n) && n > 0 ? Math.round(n) : 0;
    };

    var parseJsonArray = function (str) {
        try {
            var data = JSON.parse(str || '[]');
            return $.isArray(data) ? data : [];
        } catch (e) {
            return [];
        }
    };

    var ensureUnifiedStyle = function () {
        if ($('#game-form-unify-style').length) return;
        $('head').append(''
            + '<style id="game-form-unify-style">'
            + '.dynamic-item{display:block !important;padding:12px 14px !important;}'
            + '.dynamic-item .di-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;align-items:end;}'
            + '.dynamic-item .di-field label{display:block;font-size:11px;color:#888;margin-bottom:4px;}'
            + '.dynamic-item .di-field input,.dynamic-item .di-field select{width:100%;height:34px;line-height:34px;padding:6px 10px;box-sizing:border-box;}'
            + '.dynamic-item .di-field input[readonly]{background:#f7f8fa;color:#666;}'
            + '.dynamic-item .di-actions{margin-top:10px;display:flex;gap:8px;justify-content:flex-end;align-items:center;}'
            + '.btn-row-pick{border:1px solid #dcdfe6;background:#fff;color:#555;border-radius:6px;padding:5px 10px;font-size:12px;cursor:pointer;}'
            + '.btn-row-pick:hover{border-color:#6366f1;color:#6366f1;}'
            + '</style>');
    };

    var PICKER_SOURCES = {
        material: {label: '素材库', url: 'platform/game/materialoptions'},
        cloud: {label: '天翼云文件', url: 'platform/game/cloudfileoptions'},
        direct: {label: '直链资源', url: 'platform/game/directresourceoptions'}
    };

    var requestPickerData = function (source, page, keyword, extraParams, cb) {
        var cfg = PICKER_SOURCES[source];
        if (!cfg) {
            cb([], 0);
            return;
        }
        var params = $.extend({page: page, list_rows: 12, keyword: keyword || ''}, extraParams || {});
        $.getJSON(cfg.url, params, function (res) {
            if (!res || Number(res.code) !== 1 || !res.data) {
                cb([], 0);
                return;
            }
            cb(res.data.list || [], Number(res.data.total || 0));
        }).fail(function () {
            cb([], 0);
        });
    };

    var resolvePickedUrl = function (item, source) {
        if (source === 'cloud') return '/api/platform/clouddownloadurl?file_id=' + Number(item.id || 0);
        if (source === 'direct') return item.file_path || '';
        return item.url || '';
    };

    var resolveItemName = function (item) {
        return item.name || item.filename || item.title || '';
    };

    var openPicker = function (options) {
        options = options || {};
        var layer = getLayer();
        var sources = options.sources && options.sources.length ? options.sources : ['material'];
        var state = {source: options.defaultSource || sources[0], page: 1, keyword: ''};
        var sourceExtraParams = options.sourceExtraParams || {};

        if (!layer) {
            var pasted = window.prompt(options.promptText || '请输入地址');
            if (pasted && typeof options.onChoose === 'function') {
                options.onChoose({url: $.trim(pasted), file_path: $.trim(pasted), name: $.trim(pasted)}, state.source);
            }
            return;
        }

        var sourceOptions = $.map(sources, function (k) {
            return '<option value="' + k + '">' + (PICKER_SOURCES[k] ? PICKER_SOURCES[k].label : k) + '</option>';
        }).join('');

        var html = ''
            + '<div style="padding:12px 14px;">'
            + '  <div style="display:flex;gap:8px;margin-bottom:8px;">'
            + '    <select id="picker-source" class="form-control" style="width:170px;">' + sourceOptions + '</select>'
            + '    <input id="picker-keyword" class="form-control" placeholder="搜索名称/地址" />'
            + '    <button id="picker-search" class="btn btn-primary">搜索</button>'
            + '  </div>'
            + '  <div id="picker-list" style="max-height:340px;overflow:auto;border:1px solid #eee;border-radius:6px;padding:8px;"></div>'
            + '  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">'
            + '    <button id="picker-prev" class="btn btn-default btn-sm">上一页</button>'
            + '    <span id="picker-pageinfo" style="color:#666;"></span>'
            + '    <button id="picker-next" class="btn btn-default btn-sm">下一页</button>'
            + '  </div>'
            + '</div>';

        var pageSize = 12;
        var total = 0;
        var currentRows = [];

        var idx = layer.open({
            type: 1,
            title: options.title || '选择资源',
            area: ['920px', '560px'],
            content: html,
            success: function (layero) {
                var $layer = $(layero);
                $layer.find('#picker-source').val(state.source);

                var renderRows = function () {
                    var source = state.source;
                    var $list = $layer.find('#picker-list');
                    if (!currentRows.length) {
                        $list.html('<div style="padding:24px;color:#999;text-align:center;">暂无数据</div>');
                        return;
                    }
                    var rowsHtml = $.map(currentRows, function (item, i) {
                        var main = item.name || item.filename || '-';
                        var sub = source === 'cloud'
                            ? ('文件ID#' + (item.id || '-') + ' · ' + parseFileSize(item.file_size))
                            : (source === 'direct'
                                ? ((item.version || '-') + ' · ' + (item.file_path || ''))
                                : ((item.material_type || '-') + ' · ' + (item.url || '')));
                        var preview = resolvePickedUrl(item, source);
                        return ''
                            + '<div style="display:flex;align-items:center;justify-content:space-between;padding:8px;border-bottom:1px dashed #eee;gap:8px;">'
                            + '  <div style="min-width:0;">'
                            + '    <div style="font-weight:600;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:640px;">' + esc(main) + '</div>'
                            + '    <div style="font-size:12px;color:#888;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:640px;">' + esc(sub) + '</div>'
                            + '  </div>'
                            + '  <div style="display:flex;gap:6px;flex-shrink:0;">'
                            + '    <button class="btn btn-default btn-xs picker-preview" data-url="' + esc(preview) + '">预览</button>'
                            + '    <button class="btn btn-primary btn-xs picker-choose" data-i="' + i + '">选择</button>'
                            + '  </div>'
                            + '</div>';
                    }).join('');
                    $list.html(rowsHtml);
                };

                var load = function () {
                    requestPickerData(
                        state.source,
                        state.page,
                        state.keyword,
                        sourceExtraParams[state.source] || {},
                        function (rows, totalCount) {
                            currentRows = rows || [];
                            total = Number(totalCount || 0);
                            renderRows();
                            var pages = Math.max(1, Math.ceil(total / pageSize));
                            $layer.find('#picker-pageinfo').text('第 ' + state.page + ' / ' + pages + ' 页 · 共 ' + total + ' 条');
                            $layer.find('#picker-prev').prop('disabled', state.page <= 1);
                            $layer.find('#picker-next').prop('disabled', state.page >= pages);
                        }
                    );
                };

                $layer.off('click', '#picker-search').on('click', '#picker-search', function () {
                    state.keyword = $.trim($layer.find('#picker-keyword').val() || '');
                    state.page = 1;
                    load();
                });
                $layer.off('change', '#picker-source').on('change', '#picker-source', function () {
                    state.source = $(this).val();
                    state.page = 1;
                    load();
                });
                $layer.off('click', '#picker-prev').on('click', '#picker-prev', function () {
                    if (state.page > 1) {
                        state.page--;
                        load();
                    }
                });
                $layer.off('click', '#picker-next').on('click', '#picker-next', function () {
                    var pages = Math.max(1, Math.ceil(total / pageSize));
                    if (state.page < pages) {
                        state.page++;
                        load();
                    }
                });
                $layer.off('click', '.picker-preview').on('click', '.picker-preview', function () {
                    var url = $(this).data('url') || '';
                    if (url) window.open(url, '_blank');
                });
                $layer.off('click', '.picker-choose').on('click', '.picker-choose', function () {
                    var i = Number($(this).data('i') || 0);
                    var item = currentRows[i];
                    if (!item) return;
                    if (typeof options.onChoose === 'function') {
                        options.onChoose(item, state.source);
                    }
                    layer.close(idx);
                });

                load();
            }
        });
    };

    var syncAllJsons = function () {
        var parseList = function (selector) {
            var rows = [];
            $(selector + ' .dynamic-item').each(function () {
                var item = {};
                $(this).find('input,select,textarea').each(function () {
                    var key = $(this).data('key');
                    if (!key) return;
                    item[key] = $(this).val();
                });
                rows.push(item);
            });
            return rows;
        };

        var carousel = parseList('#carouselList').filter(function (x) { return x.url; });
        var resource = parseList('#resourceList').filter(function (x) { return x.name && x.url; });
        var repair = parseList('#repairList').filter(function (x) { return x.name || x.script_url; });

        $('#carouselJsonHidden').val(JSON.stringify(carousel));
        $('#resourceJsonHidden').val(JSON.stringify(resource));
        $('#repairJsonHidden').val(JSON.stringify(repair));
    };

    var addCarouselRow = function (data) {
        data = data || {};
        var url = data.url || data.image_url || '';
        var html = ''
            + '<div class="dynamic-item">'
            + '  <div class="di-grid">'
            + '    <div class="di-field">'
            + '      <label>图片 URL</label>'
            + '      <input type="url" data-key="url" placeholder="支持外部URL，或点击右侧从素材库选择" value="' + esc(url) + '" />'
            + '      <input type="hidden" data-key="title" value="' + esc(data.title || '') + '" />'
            + '      <input type="hidden" data-key="link_url" value="' + esc(data.link_url || '') + '" />'
            + '    </div>'
            + '  </div>'
            + '  <div class="di-actions">'
            + '    <button type="button" class="btn-row-pick btn-pick-carousel">选择素材</button>'
            + '    <button type="button" class="btn-row-pick btn-preview-carousel">预览</button>'
            + '    <button type="button" class="btn-remove">×</button>'
            + '  </div>'
            + '</div>';
        $('#carouselList').append($(html));
        syncAllJsons();
    };

    var addResourceRow = function (data) {
        data = data || {};
        var url = data.url || data.file_path || '';
        var sizeText = data.size || parseFileSize(data.file_size) || '';
        var sourceLabel = data.channel_key === 'cloud' ? '天翼云文件' : (data.channel_key === 'direct' ? '直链资源' : '');
        var html = ''
            + '<div class="dynamic-item">'
            + '  <div class="di-grid">'
            + '    <div class="di-field"><label>资源名称</label><input type="text" data-key="name" readonly value="' + esc(data.name || '') + '" /></div>'
            + '    <div class="di-field"><label>版本</label><input type="text" data-key="version" readonly value="' + esc(data.version || '') + '" /></div>'
            + '    <div class="di-field"><label>下载链接</label><input type="url" data-key="url" readonly value="' + esc(url) + '" /></div>'
            + '    <div class="di-field"><label>文件大小</label><input type="text" data-key="size" readonly value="' + esc(sizeText) + '" /></div>'
            + '    <div class="di-field"><label>来源</label><input type="text" data-key="source_label" readonly value="' + esc(sourceLabel) + '" /></div>'
            + '    <input type="hidden" data-key="file_size_bytes" value="' + esc(toFileSizeNumber(data.file_size_bytes || data.file_size || 0)) + '" />'
            + '    <input type="hidden" data-key="type" value="' + esc(data.type || data.resource_type || 'game') + '" />'
            + '    <input type="hidden" data-key="channel_key" value="' + esc(data.channel_key || '') + '" />'
            + '  </div>'
            + '  <div class="di-actions">'
            + '    <button type="button" class="btn-row-pick btn-pick-resource">选择天翼云/直链文件</button>'
            + '    <button type="button" class="btn-row-pick btn-preview-resource">预览</button>'
            + '    <button type="button" class="btn-remove">×</button>'
            + '  </div>'
            + '</div>';
        $('#resourceList').append($(html));
        syncAllJsons();
    };

    var addRepairRow = function (data) {
        data = data || {};
        var scriptUrl = data.script_url || data.url || data.file_path || '';
        var sourceLabel = data.channel_key === 'cloud' ? '天翼云文件' : (data.channel_key === 'direct' ? '直链资源' : '');
        var html = ''
            + '<div class="dynamic-item">'
            + '  <div class="di-grid">'
            + '    <div class="di-field"><label>修复方案名称</label><input type="text" data-key="name" readonly value="' + esc(data.name || '') + '" /></div>'
            + '    <div class="di-field"><label>脚本/命令地址</label><input type="url" data-key="script_url" readonly value="' + esc(scriptUrl) + '" /></div>'
            + '    <div class="di-field"><label>文件大小</label><input type="text" data-key="size" readonly value="' + esc(data.size || '') + '" /></div>'
            + '    <div class="di-field"><label>来源</label><input type="text" data-key="source_label" readonly value="' + esc(sourceLabel) + '" /></div>'
            + '    <input type="hidden" data-key="file_size_bytes" value="' + esc(toFileSizeNumber(data.file_size_bytes || data.file_size || 0)) + '" />'
            + '    <input type="hidden" data-key="description" value="' + esc(data.description || '') + '" />'
            + '    <input type="hidden" data-key="risk_level" value="' + esc(data.risk_level || 'low') + '" />'
            + '    <input type="hidden" data-key="channel_key" value="' + esc(data.channel_key || '') + '" />'
            + '  </div>'
            + '  <div class="di-actions">'
            + '    <button type="button" class="btn-row-pick btn-pick-repair">选择天翼云/直链文件</button>'
            + '    <button type="button" class="btn-row-pick btn-preview-repair">预览</button>'
            + '    <button type="button" class="btn-remove">×</button>'
            + '  </div>'
            + '</div>';
        $('#repairList').append($(html));
        syncAllJsons();
    };

    var collectRowsFromDom = function (listSelector) {
        var rows = [];
        $(listSelector + ' .dynamic-item').each(function () {
            var item = {};
            $(this).find('input,select,textarea').each(function () {
                var key = $(this).data('key');
                if (key) item[key] = $(this).val();
            });
            rows.push(item);
        });
        return rows;
    };

    var normalizeResourceItem = function (x) {
        x = x || {};
        var url = x.url || x.file_path || '';
        var name = x.name || '';
        var version = x.version || '';
        var channel = x.channel_key || '';
        if (!channel && /^\/api\/platform\/clouddownloadurl/i.test(url)) {
            channel = 'cloud';
        }
        if (!channel) {
            channel = 'direct';
        }
        return {
            name: name,
            version: version,
            url: url,
            size: x.size || parseFileSize(x.file_size_bytes || x.file_size) || '',
            file_size_bytes: toFileSizeNumber(x.file_size_bytes || x.file_size),
            type: x.type || x.resource_type || 'game',
            channel_key: channel
        };
    };

    var normalizeRepairItem = function (x) {
        x = x || {};
        var scriptUrl = x.script_url || x.url || x.file_path || '';
        var channel = x.channel_key || (/^\/api\/platform\/clouddownloadurl/i.test(scriptUrl) ? 'cloud' : 'direct');
        return {
            name: x.name || '',
            script_url: scriptUrl,
            size: x.size || parseFileSize(x.file_size_bytes || x.file_size) || '',
            file_size_bytes: toFileSizeNumber(x.file_size_bytes || x.file_size),
            description: x.description || '',
            risk_level: x.risk_level || 'low',
            channel_key: channel
        };
    };

    var ensureCoverActions = function () {
        var $cover = $('input[name="row[cover]"]');
        if (!$cover.length) return;

        if (!$('#btn-pick-cover').length) {
            $cover.after(' <button type="button" id="btn-pick-cover" class="btn btn-default btn-xs">选择素材</button> <button type="button" id="btn-preview-cover" class="btn btn-default btn-xs">预览</button>');
        }
        if (!$('#coverPreviewImg').length) {
            $cover.closest('.form-item').append('<div class="cover-preview-box"><img id="coverPreviewImg" src="" alt="封面预览" /></div>');
        }

        var renderPreview = function () {
            $('#coverPreviewImg').attr('src', $.trim($cover.val() || ''));
        };

        $(document).off('input.gamecover', 'input[name="row[cover]"]').on('input.gamecover', 'input[name="row[cover]"]', renderPreview);
        $(document).off('click.gamecover', '#btn-preview-cover').on('click.gamecover', '#btn-preview-cover', function () {
            var url = $.trim($cover.val() || '');
            if (url) window.open(url, '_blank');
            return false;
        });
        $(document).off('click.gamecover', '#btn-pick-cover').on('click.gamecover', '#btn-pick-cover', function () {
            openPicker({
                title: '选择封面素材',
                sources: ['material'],
                defaultSource: 'material',
                sourceExtraParams: {material: {material_type: 'image'}},
                onChoose: function (item, source) {
                    $cover.val(resolvePickedUrl(item, source));
                    renderPreview();
                }
            });
            return false;
        });

        renderPreview();
    };

    var initTagsInput = function () {
        var $tagsInputWrap = $('#tagsInput');
        var $hidden = $('#tagsHidden');
        if (!$tagsInputWrap.length || !$hidden.length) return;

        var tags = ($hidden.val() || '').split(',');
        tags = $.grep($.map(tags, function (t) { return $.trim(t); }), function (t) { return !!t; });

        var render = function () {
            $tagsInputWrap.find('.tag-chip').remove();
            $.each(tags, function (i, t) {
                $tagsInputWrap.find('input').before('<span class="tag-chip">' + esc(t) + '<span class="remove-tag" data-i="' + i + '">×</span></span>');
            });
            $hidden.val(tags.join(','));
        };

        $tagsInputWrap.off('keydown.gametag', 'input').on('keydown.gametag', 'input', function (e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                var val = $.trim($(this).val() || '');
                if (val && $.inArray(val, tags) < 0) tags.push(val);
                $(this).val('');
                render();
            }
        });

        $(document).off('click.gametag', '.remove-tag').on('click.gametag', '.remove-tag', function () {
            var i = Number($(this).data('i') || -1);
            if (i >= 0 && i < tags.length) {
                tags.splice(i, 1);
                render();
            }
        });

        render();
    };

    var mountRows = function () {
        var carouselRows = collectRowsFromDom('#carouselList');
        if (!carouselRows.length) {
            carouselRows = parseJsonArray($('#carouselJsonHidden').val());
        }
        $('#carouselList').empty();
        if (carouselRows.length) {
            $.each(carouselRows, function (_, item) { addCarouselRow(item || {}); });
        } else {
            addCarouselRow({});
        }

        var resourceRows = collectRowsFromDom('#resourceList');
        if (!resourceRows.length) {
            resourceRows = parseJsonArray($('#resourceJsonHidden').val());
        }
        $('#resourceList').empty();
        if (resourceRows.length) {
            $.each(resourceRows, function (_, item) { addResourceRow(normalizeResourceItem(item)); });
        } else {
            addResourceRow({});
        }

        var repairRows = collectRowsFromDom('#repairList');
        if (!repairRows.length) {
            repairRows = parseJsonArray($('#repairJsonHidden').val());
        }
        $('#repairList').empty();
        if (repairRows.length) {
            $.each(repairRows, function (_, item) { addRepairRow(normalizeRepairItem(item)); });
        } else {
            addRepairRow({});
        }
    };

    var bindDynamicEvents = function () {
        $(document).off('click.gamedyn', '.dynamic-item .btn-remove').on('click.gamedyn', '.dynamic-item .btn-remove', function () {
            $(this).closest('.dynamic-item').remove();
            syncAllJsons();
            return false;
        });

        $(document).off('click.gameadd', '.js-add-carousel').on('click.gameadd', '.js-add-carousel', function () {
            addCarouselRow({});
            return false;
        });
        $(document).off('click.gameadd', '.js-add-resource').on('click.gameadd', '.js-add-resource', function () {
            addResourceRow({});
            return false;
        });
        $(document).off('click.gameadd', '.js-add-repair').on('click.gameadd', '.js-add-repair', function () {
            addRepairRow({});
            return false;
        });

        $(document).off('click.gamerow', '.btn-pick-carousel').on('click.gamerow', '.btn-pick-carousel', function () {
            var $row = $(this).closest('.dynamic-item');
            var $input = $row.find('input[data-key="url"]');
            openPicker({
                title: '选择轮播图素材',
                sources: ['material'],
                defaultSource: 'material',
                sourceExtraParams: {material: {material_type: 'image'}},
                onChoose: function (item, source) {
                    $input.val(resolvePickedUrl(item, source));
                    syncAllJsons();
                }
            });
            return false;
        });
        $(document).off('click.gamerow', '.btn-preview-carousel').on('click.gamerow', '.btn-preview-carousel', function () {
            var url = $.trim($(this).closest('.dynamic-item').find('input[data-key="url"]').val() || '');
            if (url) window.open(url, '_blank');
            return false;
        });

        $(document).off('click.gamerow', '.btn-pick-resource').on('click.gamerow', '.btn-pick-resource', function () {
            var $row = $(this).closest('.dynamic-item');
            openPicker({
                title: '选择下载资源',
                sources: ['cloud', 'direct'],
                defaultSource: 'direct',
                onChoose: function (item, source) {
                    var url = resolvePickedUrl(item, source);
                    var name = resolveItemName(item);
                    var version = item.version || '';
                    var fileSize = toFileSizeNumber(item.file_size);
                    var sizeText = parseFileSize(fileSize);
                    $row.find('input[data-key="name"]').val(name);
                    $row.find('input[data-key="version"]').val(version);
                    $row.find('input[data-key="url"]').val(url);
                    $row.find('input[data-key="size"]').val(sizeText);
                    $row.find('input[data-key="file_size_bytes"]').val(fileSize);
                    $row.find('input[data-key="source_label"]').val(source === 'cloud' ? '天翼云文件' : '直链资源');
                    $row.find('input[data-key="channel_key"]').val(source === 'cloud' ? 'cloud' : 'direct');
                    syncAllJsons();
                }
            });
            return false;
        });
        $(document).off('click.gamerow', '.btn-preview-resource').on('click.gamerow', '.btn-preview-resource', function () {
            var url = $.trim($(this).closest('.dynamic-item').find('input[data-key="url"]').val() || '');
            if (url) window.open(url, '_blank');
            return false;
        });

        $(document).off('click.gamerow', '.btn-pick-repair').on('click.gamerow', '.btn-pick-repair', function () {
            var $row = $(this).closest('.dynamic-item');
            openPicker({
                title: '选择修复方案文件',
                sources: ['cloud', 'direct'],
                defaultSource: 'direct',
                onChoose: function (item, source) {
                    var url = resolvePickedUrl(item, source);
                    var name = resolveItemName(item);
                    var fileSize = toFileSizeNumber(item.file_size);
                    $row.find('input[data-key="name"]').val(name);
                    $row.find('input[data-key="script_url"]').val(url);
                    $row.find('input[data-key="size"]').val(parseFileSize(fileSize));
                    $row.find('input[data-key="file_size_bytes"]').val(fileSize);
                    $row.find('input[data-key="source_label"]').val(source === 'cloud' ? '天翼云文件' : '直链资源');
                    $row.find('input[data-key="channel_key"]').val(source === 'cloud' ? 'cloud' : 'direct');
                    syncAllJsons();
                }
            });
            return false;
        });
        $(document).off('click.gamerow', '.btn-preview-repair').on('click.gamerow', '.btn-preview-repair', function () {
            var url = $.trim($(this).closest('.dynamic-item').find('input[data-key="script_url"]').val() || '');
            if (url) window.open(url, '_blank');
            return false;
        });

        $(document).off('submit.gameform', 'form[role=form]').on('submit.gameform', 'form[role=form]', function () {
            syncAllJsons();
        });
    };

    var initGameForm = function () {
        if (!$('form[role=form]').length) return;
        ensureUnifiedStyle();
        window.__GAME_MODULE_READY__ = true;
        window.syncAllJsons = syncAllJsons;
        window.addCarouselRow = addCarouselRow;
        window.addResourceRow = addResourceRow;
        window.addRepairRow = addRepairRow;

        ensureCoverActions();
        initTagsInput();
        mountRows();
        bindDynamicEvents();
        syncAllJsons();
    };

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'platform/game/index',
                    add_url: 'platform/game/add',
                    edit_url: 'platform/game/edit',
                    del_url: 'platform/game/del',
                    multi_url: 'platform/game/multi'
                }
            });
            var table = $("#table");
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                columns: [[
                    {field: 'state', checkbox: true},
                    {field: 'id', title: __('Id')},
                    {field: 'title', title: __('Title'), operate: 'LIKE'},
                    {field: 'slug', title: __('Slug'), operate: 'LIKE'},
                    {field: 'tags', title: __('Tags'), operate: 'LIKE'},
                    {field: 'is_member_only', title: __('Is_member_only'), searchList: {'1': __('Yes'), '0': __('No')}, formatter: function (value) { return value == 1 ? __('Yes') : __('No'); }},
                    {field: 'weigh', title: __('Weigh')},
                    {field: 'status', title: __('Status'), searchList: {"normal": __('Normal'), "hidden": __('Hidden')}, formatter: Table.api.formatter.status},
                    {field: 'updatetime', title: __('Updatetime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange'},
                    {
                        field: 'quick_edit',
                        title: '快速编辑',
                        operate: false,
                        formatter: function (value, row) {
                            return '<a href="javascript:;" class="btn btn-xs btn-primary btn-game-edit" data-id="' + row.id + '"><i class="fa fa-pencil"></i> 编辑</a>';
                        }
                    },
                    {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                ]]
            });

            $(document).off('click', '.btn-game-edit').on('click', '.btn-game-edit', function () {
                var id = Number($(this).data('id') || 0);
                if (!id) return false;
                var url = 'platform/game/edit/ids/' + id;
                if (Backend.api && Backend.api.addtabs) {
                    Backend.api.addtabs(url, '编辑游戏 #' + id);
                } else if (Backend.api && Backend.api.open) {
                    Backend.api.open(url, '编辑游戏 #' + id);
                } else {
                    window.location.href = url;
                }
                return false;
            });

            Table.api.bindevent(table);
        },
        add: function () {
            initGameForm();
            Form.api.bindevent($("form[role=form]"));
        },
        edit: function () {
            initGameForm();
            Form.api.bindevent($("form[role=form]"));
        }
    };

    return Controller;
});
