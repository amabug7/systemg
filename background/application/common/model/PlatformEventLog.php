<?php

namespace app\common\model;

use think\Model;

class PlatformEventLog extends Model
{
    protected $name = 'platform_event_log';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
}
