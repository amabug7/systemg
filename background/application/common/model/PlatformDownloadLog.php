<?php

namespace app\common\model;

use think\Model;

class PlatformDownloadLog extends Model
{
    protected $name = 'platform_download_log';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
}
