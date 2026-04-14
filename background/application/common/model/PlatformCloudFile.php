<?php

namespace app\common\model;

use think\Model;

class PlatformCloudFile extends Model
{
    protected $name = 'platform_cloud_file';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getStatusList()
    {
        return [
            'normal' => '开启',
            'hidden' => '隐藏',
        ];
    }

    public function getAccountRuleList()
    {
        return [
            'ordered' => '顺序',
            'random' => '随机',
        ];
    }
}
