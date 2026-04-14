<?php

namespace app\admin\controller\platform;

use app\common\controller\Backend;

class Repairaction extends Backend
{
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('app\common\model\PlatformRepairAction');
        $this->view->assign("statusList", $this->model->getStatusList());
    }
}
