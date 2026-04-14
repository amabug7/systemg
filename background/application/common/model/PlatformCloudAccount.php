<?php

namespace app\common\model;

use think\Model;

class PlatformCloudAccount extends Model
{
    protected $name = 'platform_cloud_account';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getStatusList()
    {
        return [
            'normal' => '开启',
            'hidden' => '隐藏',
            'limited' => '限速',
        ];
    }
}
