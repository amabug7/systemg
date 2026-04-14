<?php

namespace app\common\model;

use think\Model;

class PlatformRollbackTask extends Model
{
    protected $name = 'platform_rollback_task';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
}
