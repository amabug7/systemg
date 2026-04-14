<?php

namespace app\common\model;

use think\Model;

class PlatformGame extends Model
{
    protected $name = 'platform_game';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 新增字段白名单
    // 允许批量填充的字段（含新增的定价/显示字段）
    protected $field = true; // 允许所有字段，由控制器层面控制

    /**
     * 状态列表
     */
    public function getStatusList()
    {
        return [
            'normal' => __('Normal'),
            'hidden' => __('Hidden'),
            'draft'  => __('Draft'),
        ];
    }

    /**
     * 轮播图关联（一对多）
     */
    public function carousels()
    {
        return $this->hasMany(PlatformGameCarousel::class, 'game_id', 'id');
    }

    /**
     * 下载资源关联
     */
    public function resources()
    {
        return $this->hasMany(PlatformGameResource::class, 'game_id', 'id');
    }

    /**
     * 修复方案关联
     */
    public function repairs()
    {
        return $this->hasMany(PlatformGameRepair::class, 'game_id', 'id');
    }

    /**
     * 评论关联
     */
    public function comments()
    {
        return $this->hasMany(PlatformGameComment::class, 'game_id', 'id');
    }
}
