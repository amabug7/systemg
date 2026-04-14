<?php

namespace app\common\model;

use think\Model;

class PlatformDownloadChannel extends Model
{
    protected $name = 'platform_download_channel';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }
}
