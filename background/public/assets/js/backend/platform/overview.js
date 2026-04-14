define(['jquery', 'bootstrap', 'backend', 'echarts', 'echarts-theme'], function ($, undefined, Backend, Echarts, undefined) {
    var Controller = {
        index: function () {
            var chart = Echarts.init(document.getElementById('trend-chart'), 'walden');
            var hourlyChart = Echarts.init(document.getElementById('hourly-chart'), 'walden');
            var formatDate = function (date) {
                var y = date.getFullYear();
                var m = ('0' + (date.getMonth() + 1)).slice(-2);
                var d = ('0' + date.getDate()).slice(-2);
                var h = ('0' + date.getHours()).slice(-2);
                var i = ('0' + date.getMinutes()).slice(-2);
                var s = ('0' + date.getSeconds()).slice(-2);
                return y + '-' + m + '-' + d + ' ' + h + ':' + i + ':' + s;
            };
            var buildLogUrl = function (moduleName, code, gameId, days) {
                var filter = {error_code: code};
                var op = {error_code: '='};
                var endDate = new Date();
                var startDate = new Date(endDate.getTime() - ((days > 0 ? days : 14) - 1) * 86400000);
                startDate.setHours(0, 0, 0, 0);
                filter.createtime = formatDate(startDate) + ' - ' + formatDate(endDate);
                op.createtime = 'RANGE';
                if (gameId > 0) {
                    filter.game_id = String(gameId);
                    op.game_id = '=';
                }
                return 'platform/' + moduleName + '?filter=' + encodeURIComponent(JSON.stringify(filter)) + '&op=' + encodeURIComponent(JSON.stringify(op));
            };
            var buildLogUrlByFilter = function (moduleName, filter, op) {
                return 'platform/' + moduleName + '?filter=' + encodeURIComponent(JSON.stringify(filter)) + '&op=' + encodeURIComponent(JSON.stringify(op));
            };
            var buildTimeRange = function (days) {
                var endDate = new Date();
                var startDate = new Date(endDate.getTime() - ((days > 0 ? days : 14) - 1) * 86400000);
                startDate.setHours(0, 0, 0, 0);
                return {
                    start: startDate,
                    end: endDate
                };
            };
            var buildBaseFilter = function (gameId, days) {
                var range = buildTimeRange(days);
                var filter = {
                    createtime: formatDate(range.start) + ' - ' + formatDate(range.end)
                };
                var op = {createtime: 'RANGE'};
                if (gameId > 0) {
                    filter.game_id = String(gameId);
                    op.game_id = '=';
                }
                return {filter: filter, op: op};
            };
            var buildErrorList = function (selector, rows, moduleName, gameId, days) {
                var $target = $(selector);
                $target.empty();
                if (!rows || !rows.length) {
                    $target.append('<li class="list-group-item">' + __('No data') + '</li>');
                    return;
                }
                $.each(rows, function (_, row) {
                    var $item = $('<li class="list-group-item"></li>');
                    var $link = $('<a href="javascript:;"></a>').text(row.code + ' - ' + __('Open logs')).on('click', function () {
                        Backend.api.addtabs(buildLogUrl(moduleName, row.code, gameId, days), __('Overview'));
                    });
                    $item.append('<span class="badge">' + row.count + '</span>').append($link);
                    $target.append($item);
                });
            };
            var buildSimpleTopList = function (selector, rows, itemBuilder) {
                var $target = $(selector);
                $target.empty();
                if (!rows || !rows.length) {
                    $target.append('<li class="list-group-item">' + __('No data') + '</li>');
                    return;
                }
                $.each(rows, function (_, row) {
                    $target.append(itemBuilder(row));
                });
            };
            var buildTopGames = function (selector, rows, days) {
                var $target = $(selector);
                $target.empty();
                if (!rows || !rows.length) {
                    $target.append('<li class="list-group-item">' + __('No data') + '</li>');
                    return;
                }
                $.each(rows, function (_, row) {
                    var base = buildBaseFilter(row.game_id, days);
                    var buildTypedFilter = function (status) {
                        var filter = $.extend({}, base.filter, {status: status});
                        var op = $.extend({}, base.op, {status: '='});
                        return {filter: filter, op: op};
                    };
                    var $item = $('<li class="list-group-item"></li>');
                    var $title = $('<span></span>').text(row.title + ' ');
                    var $download = $('<a href="javascript:;"></a>').text('D:' + row.download_success).on('click', function () {
                        var payload = buildTypedFilter('success');
                        Backend.api.addtabs(buildLogUrlByFilter('downloadlog', payload.filter, payload.op), __('Overview'));
                    });
                    var $install = $('<a href="javascript:;" style="margin-left:8px;"></a>').text('I:' + row.install_success).on('click', function () {
                        var payload = buildTypedFilter('success');
                        Backend.api.addtabs(buildLogUrlByFilter('installlog', payload.filter, payload.op), __('Overview'));
                    });
                    var $repair = $('<a href="javascript:;" style="margin-left:8px;"></a>').text('R:' + row.repair_success).on('click', function () {
                        var payload = buildTypedFilter('success');
                        Backend.api.addtabs(buildLogUrlByFilter('repairlog', payload.filter, payload.op), __('Overview'));
                    });
                    $item.append($title).append($download).append($install).append($repair);
                    $target.append($item);
                });
            };
            var loadFilters = function () {
                $.getJSON('/api/platform/gameoptions', function (ret) {
                    if (ret.code !== 1 || !ret.data || !ret.data.list) {
                        return;
                    }
                    var $select = $('#game-filter');
                    $.each(ret.data.list, function (_, row) {
                        $select.append('<option value="' + row.id + '">' + row.title + '</option>');
                    });
                });
            };
            var reloadOverview = function () {
                var days = parseInt($('#days-filter').val(), 10) || 14;
                var gameId = parseInt($('#game-filter').val(), 10) || 0;
                $.getJSON('/api/platform/stats', {days: days, game_id: gameId}, function (ret) {
                    if (ret.code === 1) {
                        $('#games-total').text(ret.data.games_total || 0);
                        $('#pages-total').text(ret.data.pages_total || 0);
                        $('#download-total').text((ret.data.downloads && ret.data.downloads.total) || 0);
                        $('#install-total').text((ret.data.installs && ret.data.installs.total) || 0);
                        $('#repair-total').text((ret.data.repairs && ret.data.repairs.total) || 0);
                        $('#download-failure-rate').text(((ret.data.failure_rate && ret.data.failure_rate.download) || 0) + '%');
                        $('#install-failure-rate').text(((ret.data.failure_rate && ret.data.failure_rate.install) || 0) + '%');
                        $('#repair-failure-rate').text(((ret.data.failure_rate && ret.data.failure_rate.repair) || 0) + '%');
                        $('#install-conversion').text(((ret.data.funnel && ret.data.funnel.install_conversion) || 0) + '%');
                        $('#repair-conversion').text(((ret.data.funnel && ret.data.funnel.repair_conversion) || 0) + '%');
                        $('#hot-update-total').text((ret.data.hot_update && ret.data.hot_update.counter && ret.data.hot_update.counter.total) || 0);
                        $('#hot-update-failure-rate').text(((ret.data.hot_update && ret.data.hot_update.failure_rate) || 0) + '%');
                        buildErrorList('#install-error-list', ret.data.top_error_codes ? ret.data.top_error_codes.install : [], 'installlog', gameId, days);
                        buildErrorList('#repair-error-list', ret.data.top_error_codes ? ret.data.top_error_codes.repair : [], 'repairlog', gameId, days);
                        buildSimpleTopList('#top-channel-list', ret.data.top_channels || [], function (row) {
                            var base = buildBaseFilter(gameId, days);
                            var filter = $.extend({}, base.filter, {channel: row.key, status: 'success'});
                            var op = $.extend({}, base.op, {channel: '=', status: '='});
                            var $item = $('<li class="list-group-item"></li>');
                            var $link = $('<a href="javascript:;"></a>').text(row.key).on('click', function () {
                                Backend.api.addtabs(buildLogUrlByFilter('downloadlog', filter, op), __('Overview'));
                            });
                            $item.append('<span class="badge">' + row.count + '</span>').append($link);
                            return $item;
                        });
                        buildTopGames('#top-game-list', ret.data.top_games || [], days);
                        buildSimpleTopList('#hot-update-failed-step-list', ret.data.hot_update ? ret.data.hot_update.top_failed_steps : [], function (row) {
                            var $item = $('<li class="list-group-item"></li>');
                            $item.append('<span class="badge">' + row.count + '</span>').append($('<span></span>').text(row.key));
                            return $item;
                        });
                        buildSimpleTopList('#hot-update-version-list', ret.data.hot_update ? ret.data.hot_update.top_target_versions : [], function (row) {
                            var $item = $('<li class="list-group-item"></li>');
                            $item.append('<span class="badge">' + row.count + '</span>').append($('<span></span>').text(row.key));
                            return $item;
                        });
                    }
                });
                $.getJSON('/api/platform/trends', {days: days, game_id: gameId}, function (ret) {
                    if (ret.code !== 1) {
                        return;
                    }
                    chart.setOption({
                        tooltip: {trigger: 'axis'},
                        legend: {data: [__('Download success'), __('Download failed'), __('Install success'), __('Install failed'), __('Repair success'), __('Repair failed'), __('Hot update success'), __('Hot update failed')]},
                        xAxis: {type: 'category', boundaryGap: false, data: ret.data.labels || []},
                        yAxis: {type: 'value'},
                        grid: [{left: 'left', top: 'top', right: '10', bottom: 30}],
                        series: [
                            {name: __('Download success'), type: 'line', smooth: true, data: ret.data.download_success || []},
                            {name: __('Download failed'), type: 'line', smooth: true, data: ret.data.download_failed || []},
                            {name: __('Install success'), type: 'line', smooth: true, data: ret.data.install_success || []},
                            {name: __('Install failed'), type: 'line', smooth: true, data: ret.data.install_failed || []},
                            {name: __('Repair success'), type: 'line', smooth: true, data: ret.data.repair_success || []},
                            {name: __('Repair failed'), type: 'line', smooth: true, data: ret.data.repair_failed || []},
                            {name: __('Hot update success'), type: 'line', smooth: true, data: ret.data.hot_update_success || []},
                            {name: __('Hot update failed'), type: 'line', smooth: true, data: ret.data.hot_update_failed || []}
                        ]
                    });
                });
                $.getJSON('/api/platform/trendshourly', {days: days, game_id: gameId}, function (ret) {
                    if (ret.code !== 1) {
                        return;
                    }
                    hourlyChart.setOption({
                        tooltip: {trigger: 'axis'},
                        legend: {data: [__('Download success'), __('Install success'), __('Repair success')]},
                        xAxis: {type: 'category', data: ret.data.labels || []},
                        yAxis: {type: 'value'},
                        grid: [{left: 'left', top: 'top', right: '10', bottom: 30}],
                        series: [
                            {name: __('Download success'), type: 'bar', data: ret.data.download_success || []},
                            {name: __('Install success'), type: 'bar', data: ret.data.install_success || []},
                            {name: __('Repair success'), type: 'bar', data: ret.data.repair_success || []}
                        ]
                    });
                });
            };
            loadFilters();
            reloadOverview();
            $('#reload-overview').on('click', function () {
                reloadOverview();
            });
            $(window).resize(function () {
                chart.resize();
                hourlyChart.resize();
            });
        }
    };
    return Controller;
});
