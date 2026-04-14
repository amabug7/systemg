<?php

namespace app\common\model;

use think\Model;

class PlatformGameResource extends Model
{
    protected $name = 'platform_game_resource';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }

    public function getResourceTypeList()
    {
        return [
            'game' => __('Game'),
            'mod' => __('Mod'),
            'plugin' => __('Plugin'),
            'patch' => __('Patch'),
            'tool' => __('Tool')
        ];
    }
}
