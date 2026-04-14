<?php

namespace app\common\model;

use think\Model;

class PlatformAbExperiment extends Model
{
    protected $name = 'platform_ab_experiment';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
}
