<?php

namespace app\common\model;

use think\Model;

class PlatformOrderAlert extends Model
{
    protected $name = 'platform_order_alert';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getStatusList()
    {
        return ['open' => __('Open'), 'closed' => __('Closed')];
    }
}
