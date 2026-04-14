<?php

namespace app\common\model;

use think\Model;

class PlatformRepairLog extends Model
{
    protected $name = 'platform_repair_log';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
}
