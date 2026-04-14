define(['jquery', 'bootstrap', 'backend'], function ($, undefined, Backend) {
    var Controller = {
        index: function () {
            var render = function (data) {
                $('#pending-messages').text(data.pending_messages || 0);
                $('#open-alerts').text(data.open_alerts || 0);
                $('#published-today').text(data.published_today || 0);
                $('#active-pages').text(data.active_pages || 0);
            };
            var load = function () {
                Backend.api.ajax({url: 'platform/workbench/summary'}, function (ret) {
                    render(ret.data || {});
                    return false;
                });
            };
            $(document).on('click', '#reload-workbench', function () {
                load();
            });
            load();
        }
    };
    return Controller;
});
