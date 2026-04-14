<?php

namespace app\admin\controller\platform;

use app\common\controller\Backend;
use app\common\model\PlatformDownloadChannel;
use think\Db;

class Resource extends Backend
{
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('app\common\model\PlatformGameResource');
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("resourceTypeList", $this->model->getResourceTypeList());
        $channels = PlatformDownloadChannel::where('status', 'normal')->column('name', 'channel_key');
        $this->view->assign("channelList", $channels ?: []);
        $this->ensureDirectResourceParallelMenu();
    }

    protected function ensureDirectResourceParallelMenu()
    {
        try {
            $cloudMenu = Db::name('auth_rule')->where('name', 'platform/cloudfile')->find();
            if (!$cloudMenu || empty($cloudMenu['id'])) {
                return;
            }
            $parallelPid = (int)$cloudMenu['pid'] > 0 ? (int)$cloudMenu['pid'] : (int)$cloudMenu['id'];
            $data = [
                'type'       => 'file',
                'pid'        => $parallelPid,
                'name'       => 'platform/resource/direct',
                'title'      => '直链资源',
                'icon'       => 'fa fa-link',
                'url'        => 'platform/resource/index?ref=direct',
                'condition'  => '',
                'remark'     => '',
                'ismenu'     => 1,
                'menutype'   => 'addtabs',
                'extend'     => '',
                'py'         => '',
                'pinyin'     => '',
                'updatetime' => time(),
                'weigh'      => -81,
                'status'     => 'normal',
            ];

            $row = Db::name('auth_rule')->where('name', 'platform/resource/direct')->find();
            if ($row) {
                Db::name('auth_rule')->where('id', (int)$row['id'])->update($data);
                $menuId = (int)$row['id'];
            } else {
                $data['createtime'] = time();
                Db::name('auth_rule')->insert($data);
                $menuId = (int)Db::name('auth_rule')->getLastInsID();
            }

            if ($menuId > 0) {
                $cloudIndex = (int)Db::name('auth_rule')->where('name', 'platform/cloudfile/index')->value('id');
                $cloudMenuId = (int)$cloudMenu['id'];
                $groups = Db::name('auth_group')->field('id,rules')->select();
                foreach ($groups as $group) {
                    $rules = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$group['rules'])))));
                    if (!$rules) {
                        continue;
                    }
                    $hasCloudPermission = in_array($cloudMenuId, $rules, true) || ($cloudIndex > 0 && in_array($cloudIndex, $rules, true));
                    if (!$hasCloudPermission || in_array($menuId, $rules, true)) {
                        continue;
                    }
                    $rules[] = $menuId;
                    Db::name('auth_group')->where('id', (int)$group['id'])->update(['rules' => implode(',', $rules)]);
                }
            }
        } catch (\Throwable $e) {
            \think\Log::record('ensureDirectResourceParallelMenu failed: ' . $e->getMessage(), 'debug');
        }
    }
}

