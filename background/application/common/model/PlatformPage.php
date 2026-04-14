<?php

namespace app\common\model;

use think\Model;

class PlatformPage extends Model
{
    protected $name = 'platform_page';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }

    public function getTerminalList()
    {
        return ['common' => __('Common'), 'client' => __('Client'), 'webset' => __('Webset')];
    }
}
