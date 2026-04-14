<?php

namespace app\admin\controller\platform;

use app\common\controller\Backend;

class Message extends Backend
{
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('app\common\model\PlatformMessage');
        $this->view->assign("statusList", $this->model->getStatusList());
    }
}
