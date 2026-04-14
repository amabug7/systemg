<?php

namespace app\admin\controller\platform;

use app\common\controller\Backend;

class Order extends Backend
{
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('app\common\model\PlatformOrder');
        $this->view->assign("payStatusList", $this->model->getPayStatusList());
    }
}
