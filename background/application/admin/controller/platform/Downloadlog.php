<?php

namespace app\admin\controller\platform;

use app\common\controller\Backend;
use think\Response;

class Downloadlog extends Backend
{
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('app\common\model\PlatformDownloadLog');
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
        fputcsv($fp, ['ID', 'user_id', 'game_id', 'channel', 'resource_type', 'resource_name', 'status', 'device_id', 'client_version', 'createtime']);
        foreach ($rows as $row) {
            fputcsv($fp, [
                $row['id'],
                $row['user_id'],
                $row['game_id'],
                $row['channel'],
                $row['resource_type'],
                $row['resource_name'],
                $row['status'],
                $row['device_id'],
                $row['client_version'],
                isset($row['createtime']) && $row['createtime'] ? date('Y-m-d H:i:s', (int)$row['createtime']) : ''
            ]);
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return Response::create($csv, 'html', 200)->header([
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="platform_download_log_' . date('Ymd_His') . '.csv"'
        ]);
    }
}
