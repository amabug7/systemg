<?php

namespace app\common\model;

use think\Model;

class PlatformReconcileTask extends Model
{
    protected $name = 'platform_reconcile_task';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
}
