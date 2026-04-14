<?php

namespace app\common\model;

use think\Model;

class PlatformGameRepair extends Model
{
    protected $name = 'platform_game_repair';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function game()
    {
        return $this->belongsTo(PlatformGame::class, 'game_id', 'id');
    }
}
