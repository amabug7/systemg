<?php

namespace app\admin\controller\platform;

use app\common\controller\Backend;

class Overview extends Backend
{
    public function index()
    {
        return $this->view->fetch();
    }
}
