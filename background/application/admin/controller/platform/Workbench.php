<?php

namespace app\admin\controller\platform;

use app\common\controller\Backend;
use app\common\model\PlatformConfigRelease;
use app\common\model\PlatformMessage;
use app\common\model\PlatformOrderAlert;
use app\common\model\PlatformPage;

class Workbench extends Backend
{
    public function index()
    {
        return $this->view->fetch();
    }

    public function summary()
    {
        $todayStart = strtotime(date('Y-m-d'));
        $todayEnd = $todayStart + 86400 - 1;
        $data = [
            'pending_messages' => PlatformMessage::where('status', 'pending')->count(),
            'open_alerts' => PlatformOrderAlert::where('status', 'open')->count(),
            'published_today' => PlatformConfigRelease::where('status', 'published')->where('createtime', 'between', [$todayStart, $todayEnd])->count(),
            'active_pages' => PlatformPage::where('status', 'normal')->count()
        ];
        $this->success('', $data);
    }
}
