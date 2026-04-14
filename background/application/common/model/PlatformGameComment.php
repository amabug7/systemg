<?php

namespace app\common\model;

use think\Model;

class PlatformGameComment extends Model
{
    protected $name = 'platform_game_comment';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function game()
    {
        return $this->belongsTo(PlatformGame::class, 'game_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id', 'id');
    }
}
