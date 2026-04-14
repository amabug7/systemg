<?php

namespace app\common\model;

use think\Model;

class PlatformRepairTemplate extends Model
{
    protected $name = 'platform_repair_template';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }
}
