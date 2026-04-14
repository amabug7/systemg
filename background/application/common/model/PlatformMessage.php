<?php

namespace app\common\model;

use think\Model;

class PlatformMessage extends Model
{
    protected $name = 'platform_message';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getStatusList()
    {
        return ['pending' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected')];
    }
}
