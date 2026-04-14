<?php

namespace app\admin\controller\platform;

use app\common\controller\Backend;
use think\Response;

class Installlog extends Backend
{
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('app\common\model\PlatformInstallLog');
    }

    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            return json(["total" => $list->total(), "rows" => $list->items()]);
        }
        return $this->view->fetch();
    }

    public function add()
    {
        $this->error();
    }

    public function edit($ids = null)
    {
        $this->error();
    }

    public function del($ids = "")
    {
        $this->error();
    }

    public function multi($ids = "")
    {
        $this->error();
    }

    public function export()
    {
        $this->request->filter(['strip_tags', 'trim']);
        list($where, $sort, $order) = $this->buildparams();
        $rows = $this->model
            ->where($where)
            ->order($sort, $order)
            ->limit(5000)
            ->select()
            ->toArray();
        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, ['ID', 'user_id', 'game_id', 'status', 'install_path', 'pipeline_status', 'failed_step', 'total_ms', 'deploy_mode', 'rollback_applied', 'item_count', 'pipeline_error', 'device_id', 'client_version', 'error_code', 'createtime', 'meta_json']);
        foreach ($rows as $row) {
            $meta = [];
            if (!empty($row['meta_json'])) {
                $decoded = json_decode($row['meta_json'], true);
                $meta = is_array($decoded) ? $decoded : [];
            }
            fputcsv($fp, [
                $row['id'],
                $row['user_id'],
                $row['game_id'],
                $row['status'],
                $row['install_path'],
                isset($meta['status']) ? (string)$meta['status'] : '',
                isset($meta['failed_step']) ? (string)$meta['failed_step'] : '',
                isset($meta['total_ms']) ? (string)$meta['total_ms'] : '',
                isset($meta['deploy_mode']) ? (string)$meta['deploy_mode'] : '',
                !empty($meta['rollback_applied']) ? '1' : '0',
                isset($meta['count']) ? (string)$meta['count'] : (isset($meta['items']) && is_array($meta['items']) ? (string)count($meta['items']) : '0'),
                isset($meta['error']) ? (string)$meta['error'] : '',
                $row['device_id'],
                $row['client_version'],
                $row['error_code'],
                isset($row['createtime']) && $row['createtime'] ? date('Y-m-d H:i:s', (int)$row['createtime']) : '',
                isset($row['meta_json']) ? (string)$row['meta_json'] : ''
            ]);
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return Response::create($csv, 'html', 200)->header([
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="platform_install_log_' . date('Ymd_His') . '.csv"'
        ]);
    }
}
