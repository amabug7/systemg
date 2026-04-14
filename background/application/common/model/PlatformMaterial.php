<?php

namespace app\common\model;

use think\Model;

class PlatformMaterial extends Model
{
    protected $name = 'platform_material';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }
}
