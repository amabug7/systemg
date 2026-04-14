<?php

namespace app\common\model;

use think\Model;

class PlatformOrder extends Model
{
    protected $name = 'platform_order';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getPayStatusList()
    {
        return [
            'created' => __('Created'),
            'paid' => __('Paid'),
            'closed' => __('Closed')
        ];
    }
}
