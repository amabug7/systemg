<?php

namespace app\common\model;

use think\Model;

class PlatformVersionStrategy extends Model
{
    protected $name = 'platform_version_strategy';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }
}
