<?php

namespace app\common\model;

use think\Model;

class PlatformUserAsset extends Model
{
    protected $name = 'platform_user_asset';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getStatusList()
    {
        return ['active' => __('Active'), 'expired' => __('Expired')];
    }
}
