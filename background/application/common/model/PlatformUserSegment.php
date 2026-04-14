<?php

namespace app\common\model;

use think\Model;

class PlatformUserSegment extends Model
{
    protected $name = 'platform_user_segment';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
}
