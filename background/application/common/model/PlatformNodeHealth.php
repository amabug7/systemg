<?php

namespace app\common\model;

use think\Model;

class PlatformNodeHealth extends Model
{
    protected $name = 'platform_node_health';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
}
