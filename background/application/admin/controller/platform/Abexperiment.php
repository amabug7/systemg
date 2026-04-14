<?php

namespace app\admin\controller\platform;

use app\common\controller\Backend;

class Abexperiment extends Backend
{
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('app\common\model\PlatformAbExperiment');
    }
}
