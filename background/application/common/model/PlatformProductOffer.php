<?php

namespace app\common\model;

use think\Model;

class PlatformProductOffer extends Model
{
    protected $name = 'platform_product_offer';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }
}
