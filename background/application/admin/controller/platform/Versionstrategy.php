<?php

namespace app\admin\controller\platform;

use app\common\controller\Backend;
use app\common\model\PlatformRollbackTask;
use think\Db;

class Versionstrategy extends Backend
{
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('app\common\model\PlatformVersionStrategy');
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    public function rollbackHot()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $ids = $this->request->post('ids', '');
        $idList = array_filter(array_map('intval', explode(',', (string)$ids)));
        if (!$idList) {
            $this->error(__('Invalid parameters'));
        }
        $row = $this->model->where('id', $idList[0])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $switch = $row['switch_json'] ? json_decode($row['switch_json'], true) : [];
        if (!is_array($switch)) {
            $switch = [];
        }
        $hot = isset($switch['hot_update']) && is_array($switch['hot_update']) ? $switch['hot_update'] : [];
        $prev = isset($switch['hot_update_prev']) && is_array($switch['hot_update_prev']) ? $switch['hot_update_prev'] : null;
        if (!$prev) {
            $this->error('未找到可回滚的热更配置');
        }
        $switch['hot_update'] = $prev;
        $switch['hot_update_prev'] = $hot;
        Db::startTrans();
        try {
            $row->save(['switch_json' => json_encode($switch, JSON_UNESCAPED_UNICODE)]);
            PlatformRollbackTask::create([
                'task_no' => 'RB' . date('YmdHis') . mt_rand(10000, 99999),
                'trigger_type' => 'hot_update_manual',
                'terminal' => (string)$row['terminal'],
                'from_release_no' => isset($hot['target_version']) ? (string)$hot['target_version'] : '',
                'to_release_no' => isset($prev['target_version']) ? (string)$prev['target_version'] : '',
                'status' => 'done',
                'detail' => 'manual_hot_update_rollback'
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error(__('Server is busy'));
        }
        $this->success();
    }
}
