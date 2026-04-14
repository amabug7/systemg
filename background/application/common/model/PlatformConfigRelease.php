<?php

namespace app\common\model;

use think\Model;

class PlatformConfigRelease extends Model
{
    protected $name = 'platform_config_release';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
}
