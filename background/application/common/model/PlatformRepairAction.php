<?php

namespace app\common\model;

use think\Model;

class PlatformRepairAction extends Model
{
    protected $name = 'platform_repair_action';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }
}
