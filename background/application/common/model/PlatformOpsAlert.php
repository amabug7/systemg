<?php

namespace app\common\model;

use think\Model;

class PlatformOpsAlert extends Model
{
    protected $name = 'platform_ops_alert';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getStatusList()
    {
        return ['open' => __('Open'), 'closed' => __('Closed')];
    }
}
