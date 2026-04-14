<?php

namespace app\admin\controller\platform;

use app\admin\model\AuthRule;
use app\common\controller\Backend;
use think\Cache;
use think\Db;


class Page extends Backend
{
    protected $model = null;
    protected $noNeedRight = ['presetlist'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('app\common\model\PlatformPage');
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("terminalList", $this->model->getTerminalList());
    }

    public function presetlist()
    {
        $terminal = (string)$this->request->request('terminal', 'client');
        $list = $this->model
            ->where('terminal', $terminal)
            ->whereLike('page_key', 'launcher_preset%')
            ->where('status', 'normal')
            ->order('weigh', 'desc')
            ->order('id', 'desc')
            ->field('id,terminal,page_key,title,config_json,version,weigh,updatetime')
            ->select();
        $rows = [];
        foreach ($list as $item) {
            $config = $item['config_json'] ? json_decode($item['config_json'], true) : [];
            $rows[] = [
                'id' => (int)$item['id'],
                'terminal' => (string)$item['terminal'],
                'page_key' => (string)$item['page_key'],
                'title' => (string)$item['title'],
                'version' => (string)$item['version'],
                'weigh' => (int)$item['weigh'],
                'updatetime' => (int)$item['updatetime'],
                'config' => is_array($config) ? $config : []
            ];
        }
        $this->success('', null, $rows);
    }

    public function syncmenuchinese()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->syncCloudResourceTables();
        $titleMap = [
            'Dashboard' => '控制台',
            'General' => '常规管理',
            'Category' => '分类管理',
            'Addon' => '插件管理',
            'Auth' => '权限管理',
            'Config' => '系统配置',
            'Attachment' => '附件管理',
            'Profile' => '个人资料',
            'Admin' => '管理员管理',
            'Admin log' => '管理员日志',
            'Group' => '角色组',
            'Rule' => '菜单规则',
            'User' => '会员管理',
            'User group' => '会员分组',
            'User rule' => '会员规则',
            'Select attachment' => '选择附件',
            'Local addon' => '本地插件',
            'Update state' => '禁用启用',
            'Platform' => '平台管理',
            'Game' => '游戏管理',
            'Page' => '页面配置',
            'Download log' => '下载日志',
            'Install log' => '安装日志',
            'Repair log' => '修复日志',
            'Overview' => '平台概览',
            'Channel' => '渠道管理',
            'Resource' => '资源管理',
            'Pay channel' => '支付渠道',
            'Order' => '订单管理',
            'Asset' => '资产管理',
            'Offer' => '优惠活动',
            'Reconcile' => '对账管理',
            'Order alert' => '订单预警',
            'Repair template' => '修复模板',
            'Repair action' => '修复动作',
            'Version strategy' => '版本策略',
            'Config release' => '配置发布',
            'Material' => '素材管理',
            'Message' => '消息中心',
            'Workbench' => '运营工作台',
            'Summary' => '统计摘要',
            'Node health' => '节点健康',
            'Ops alert' => '运维告警',
            'Rollback task' => '回滚任务',
            'Event log' => '事件日志',
            'User segment' => '用户分群',
            'AB experiment' => 'AB实验',
            'Cloud resource' => '云盘资源',
            'Cloud account' => '天翼云账号',
            'Cloud file' => '天翼云文件'
        ];

        $menuGroups = [
            'platformproduct' => [
                'title' => '产品管理',
                'icon' => 'fa fa-cubes',
                'weigh' => 68,
                'children' => [
                    'platform/game' => 67,
                    'platform/resource' => 66,
                    'platform/material' => 65,
                    'platform/channel' => 64,
                    'platform/cloud' => 63,
                ],

            ],
            'platformoperate' => [
                'title' => '经营管理',
                'icon' => 'fa fa-briefcase',
                'weigh' => 67,
                'children' => [
                    'platform/workbench' => 63,
                    'platform/overview' => 62,
                    'platform/paychannel' => 61,
                    'platform/order' => 60,
                    'platform/reconcile' => 59,
                    'platform/asset' => 58,
                    'platform/offer' => 57,
                    'platform/orderalert' => 56,
                    'platform/message' => 55,
                    'platform/usersegment' => 54,
                    'platform/abexperiment' => 53,
                ],
            ],
            'platform' => [
                'title' => '平台管理',
                'icon' => 'fa fa-cogs',
                'weigh' => 66,
                'children' => [
                    'platform/page' => 52,
                    'platform/versionstrategy' => 51,
                    'platform/configrelease' => 50,
                    'platform/repairtemplate' => 49,
                    'platform/repairaction' => 48,
                    'platform/downloadlog' => 47,
                    'platform/installlog' => 46,
                    'platform/repairlog' => 45,
                    'platform/nodehealth' => 44,
                    'platform/opsalert' => 43,
                    'platform/rollbacktask' => 42,
                    'platform/eventlog' => 41,
                ],
            ],
        ];

        Db::startTrans();
        try {
            $updated = 0;
            foreach ($titleMap as $from => $to) {
                $updated += AuthRule::where('title', $from)->update(['title' => $to]);
            }

            $parentIds = [];
            $childIdsByParent = [];
            foreach ($menuGroups as $name => $config) {
                $parent = AuthRule::where('name', $name)->find();
                $parentData = [
                    'type' => 'file',
                    'pid' => 0,
                    'name' => $name,
                    'title' => $config['title'],
                    'icon' => $config['icon'],
                    'url' => '',
                    'condition' => '',
                    'remark' => '',
                    'ismenu' => 1,
                    'weigh' => $config['weigh'],
                    'status' => 'normal',
                ];
                if ($parent) {
                    $updated += AuthRule::where('id', $parent['id'])->update($parentData);
                } else {
                    $parent = new AuthRule();
                    $parent->save($parentData);
                    $updated++;
                }
                $parentIds[$name] = (int)$parent['id'];
                $childIdsByParent[$name] = [];

                foreach ($config['children'] as $childName => $childWeigh) {
                    $child = AuthRule::where('name', $childName)->find();
                    if (!$child) {
                        continue;
                    }
                    $childIdsByParent[$name][] = (int)$child['id'];
                    $updated += AuthRule::where('id', $child['id'])->update([
                        'pid' => $parentIds[$name],
                        'weigh' => $childWeigh,
                        'status' => 'normal',
                    ]);
                }
            }

            $productId = isset($parentIds['platformproduct']) ? (int)$parentIds['platformproduct'] : 0;
            $cloudGrantIds = [];
            if ($productId > 0) {
                $cloud = AuthRule::where('name', 'platform/cloud')->find();
                if ($cloud) {
                    $updated += AuthRule::where('id', $cloud['id'])->update([
                        'pid' => $productId,
                        'title' => '云盘资源',
                        'icon' => 'fa fa-cloud',
                        'ismenu' => 1,
                        'weigh' => 63,
                        'status' => 'normal',
                    ]);
                } else {
                    $cloud = new AuthRule();
                    $cloud->save([
                        'type' => 'file',
                        'pid' => $productId,
                        'name' => 'platform/cloud',
                        'title' => '云盘资源',
                        'icon' => 'fa fa-cloud',
                        'url' => '',
                        'condition' => '',
                        'remark' => '',
                        'ismenu' => 1,
                        'weigh' => 63,
                        'status' => 'normal',
                    ]);
                    $updated++;
                }
                $cloudId = (int)$cloud['id'];
                $cloudGrantIds[] = $cloudId;
                $childIdsByParent['platformproduct'][] = $cloudId;
                $cloudChildren = [
                    ['name' => 'platform/cloudaccount', 'title' => '天翼云账号', 'icon' => 'fa fa-user', 'weigh' => 62, 'ismenu' => 1],
                    ['name' => 'platform/cloudfile', 'title' => '天翼云文件', 'icon' => 'fa fa-file', 'weigh' => 61, 'ismenu' => 1],
                    ['name' => 'platform/cloudaccount/index', 'title' => 'View', 'weigh' => -67, 'ismenu' => 0],
                    ['name' => 'platform/cloudaccount/add', 'title' => 'Add', 'weigh' => -68, 'ismenu' => 0],
                    ['name' => 'platform/cloudaccount/edit', 'title' => 'Edit', 'weigh' => -69, 'ismenu' => 0],
                    ['name' => 'platform/cloudaccount/del', 'title' => 'Delete', 'weigh' => -70, 'ismenu' => 0],
                    ['name' => 'platform/cloudaccount/multi', 'title' => 'Multi', 'weigh' => -71, 'ismenu' => 0],
                    ['name' => 'platform/cloudaccount/checktoken', 'title' => '检测Token', 'weigh' => -72, 'ismenu' => 0],
                    ['name' => 'platform/cloudaccount/refreshtoken', 'title' => '刷新Token', 'weigh' => -73, 'ismenu' => 0],
                    ['name' => 'platform/cloudfile/index', 'title' => 'View', 'weigh' => -74, 'ismenu' => 0],
                    ['name' => 'platform/cloudfile/add', 'title' => 'Add', 'weigh' => -75, 'ismenu' => 0],
                    ['name' => 'platform/cloudfile/edit', 'title' => 'Edit', 'weigh' => -76, 'ismenu' => 0],
                    ['name' => 'platform/cloudfile/del', 'title' => 'Delete', 'weigh' => -77, 'ismenu' => 0],
                    ['name' => 'platform/cloudfile/multi', 'title' => 'Multi', 'weigh' => -78, 'ismenu' => 0],
                    ['name' => 'platform/cloudfile/accountoptions', 'title' => '账号菜单', 'weigh' => -79, 'ismenu' => 0],
                    ['name' => 'platform/cloudfile/previewdownload', 'title' => '获取下载地址', 'weigh' => -80, 'ismenu' => 0],
                ];
                $cloudMenuId = 0;
                $cloudFileId = 0;
                foreach ($cloudChildren as $meta) {
                    $targetPid = $cloudId;
                    if (strpos($meta['name'], 'platform/cloudaccount/') === 0 && $cloudMenuId > 0) {
                        $targetPid = $cloudMenuId;
                    }
                    if (strpos($meta['name'], 'platform/cloudfile/') === 0 && $cloudFileId > 0) {
                        $targetPid = $cloudFileId;
                    }
                    $item = AuthRule::where('name', $meta['name'])->find();
                    $saveData = [
                        'pid' => $targetPid,
                        'title' => $meta['title'],
                        'weigh' => $meta['weigh'],
                        'ismenu' => $meta['ismenu'],
                        'status' => 'normal',
                    ];
                    if (!empty($meta['icon'])) {
                        $saveData['icon'] = $meta['icon'];
                    }
                    if ($item) {
                        $updated += AuthRule::where('id', $item['id'])->update($saveData);
                    } else {
                        $item = new AuthRule();
                        $item->save(array_merge([
                            'type' => 'file',
                            'name' => $meta['name'],
                            'url' => '',
                            'condition' => '',
                            'remark' => '',
                        ], $saveData));
                        $updated++;
                    }
                    $cloudGrantIds[] = (int)$item['id'];
                    if ($meta['name'] === 'platform/cloudaccount') {
                        $cloudMenuId = (int)$item['id'];
                    }
                    if ($meta['name'] === 'platform/cloudfile') {
                        $cloudFileId = (int)$item['id'];
                    }
                }
            }

            $obsoleteCloudIds = AuthRule::where('name', 'in', ['cloud', 'tianyiuser', 'tianyifiles'])->column('id');
            if ($obsoleteCloudIds) {
                $updated += AuthRule::where('id', 'in', $obsoleteCloudIds)->update([
                    'status' => 'hidden',
                    'ismenu' => 0,
                ]);
            }

            $authGroups = Db::name('auth_group')->field('id,name,rules')->select();

            foreach ($authGroups as $group) {
                $rawRules = trim((string)$group['rules']);
                if ($rawRules === '*') {
                    continue;
                }
                $rules = array_values(array_filter(array_map('intval', explode(',', $rawRules))));
                if ($obsoleteCloudIds) {
                    $rules = array_values(array_diff($rules, $obsoleteCloudIds));
                }
                $originalRules = $rules;
                foreach ($childIdsByParent as $name => $childIds) {
                    if ($childIds && array_intersect($rules, $childIds)) {
                        $rules[] = $parentIds[$name];
                    }
                }

                if ($cloudGrantIds) {
                    $productChildren = isset($childIdsByParent['platformproduct']) ? $childIdsByParent['platformproduct'] : [];
                    $hasProductAccess = ($productId > 0 && in_array($productId, $rules, true)) || ($productChildren && array_intersect($rules, $productChildren));
                    $hasCloudAccess = array_intersect($rules, $cloudGrantIds);
                    $groupName = strtolower(trim((string)$group['name']));
                    $isAdminGroup = (int)$group['id'] === 1
                        || strpos($groupName, 'admin') !== false
                        || in_array($groupName, ['administrator', 'superadmin', '超级管理员', '超级管理员组', '管理员组'], true);
                    if ($hasProductAccess || $hasCloudAccess || $isAdminGroup) {
                        $rules = array_merge($rules, $cloudGrantIds);
                        if ($productId > 0) {
                            $rules[] = $productId;
                        }
                    }
                }

                $rules = array_values(array_unique(array_filter($rules)));
                sort($rules);
                $originalSorted = $originalRules;
                sort($originalSorted);
                if ($rules !== $originalSorted) {
                    Db::name('auth_group')->where('id', $group['id'])->update(['rules' => implode(',', $rules)]);
                }
            }
            Db::commit();
            Cache::rm('__menu__');
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        $this->success('菜单同步完成', null, ['updated' => $updated]);
    }

    protected function syncCloudResourceTables()
    {
        $prefix = (string)config('database.prefix', 'fa_');
        $sqlAccount = <<<SQL
CREATE TABLE IF NOT EXISTS `{$prefix}platform_cloud_account` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(30) NOT NULL DEFAULT '',
  `login_password` varchar(255) NOT NULL DEFAULT '',
  `access_token` varchar(255) NOT NULL DEFAULT '',
  `status` varchar(30) NOT NULL DEFAULT 'normal',
  `token_refresh_time` bigint(16) DEFAULT NULL,
  `last_check_time` bigint(16) DEFAULT NULL,
  `last_check_status` varchar(30) NOT NULL DEFAULT '',
  `weigh` int(10) NOT NULL DEFAULT '0',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

        $sqlFile = <<<SQL
CREATE TABLE IF NOT EXISTS `{$prefix}platform_cloud_file` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `file_size` bigint(20) unsigned NOT NULL DEFAULT '0',
  `game_id` int(11) unsigned NOT NULL DEFAULT '0',
  `share_code` varchar(80) NOT NULL DEFAULT '',
  `access_code` varchar(30) NOT NULL DEFAULT '',
  `account_rule` varchar(30) NOT NULL DEFAULT 'ordered',
  `account_ids` varchar(255) NOT NULL DEFAULT '',
  `status` varchar(30) NOT NULL DEFAULT 'normal',
  `weigh` int(10) NOT NULL DEFAULT '0',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `game_id` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

        Db::execute($sqlAccount);
        Db::execute($sqlFile);
    }

}
