<?php

namespace app\common\model;

use think\Model;

class PlatformInstallLog extends Model
{
    protected $name = 'platform_install_log';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
}
