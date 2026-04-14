<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Tianyiyun;
use app\common\model\PlatformCloudAccount;
use app\common\model\PlatformCloudFile;
use app\common\model\PlatformDownloadChannel;

use app\common\model\PlatformDownloadLog;
use app\common\model\PlatformGame;
use app\common\model\PlatformGameResource;
use app\common\model\PlatformInstallLog;
use app\common\model\PlatformOrder;
use app\common\model\PlatformRollbackTask;
use app\common\model\PlatformEventLog;
use app\common\model\PlatformUserSegment;
use app\common\model\PlatformAbExperiment;
use app\common\model\PlatformPage;
use app\common\model\PlatformMaterial;
use app\common\model\PlatformMessage;
use app\common\model\PlatformNodeHealth;
use app\common\model\PlatformOpsAlert;
use app\common\model\PlatformPaymentChannel;
use app\common\model\PlatformVersionStrategy;
use app\common\model\PlatformConfigRelease;
use app\common\model\PlatformProductOffer;
use app\common\model\PlatformReconcileTask;
use app\common\model\PlatformRepairLog;
use app\common\model\PlatformRepairAction;
use app\common\model\PlatformRepairTemplate;
use app\common\model\PlatformOrderAlert;
use app\common\model\PlatformUserAsset;
use app\common\model\User;
use app\common\model\Version;
use think\Db;

class Platform extends Api
{
    protected $noNeedLogin = ['bootstrap', 'games', 'game', 'downloadinfo', 'resources', 'channels', 'materials', 'messages', 'submitmessage', 'paychannels', 'downloadplan', 'nodehealth', 'versionpolicy', 'pricing', 'paymentnotify', 'reportdownload', 'reportinstall', 'reportrepair', 'reportevent', 'funnel', 'segments', 'experiments', 'reportnodehealth', 'guardcheck', 'stats', 'trends', 'trendshourly', 'gameoptions', 'clouddownloadurl'];

    protected $noNeedRight = '*';

    public function bootstrap()
    {
        $terminal = $this->request->request('terminal', 'client');
        $version = $this->request->request('version', '');
        $deviceId = trim((string)$this->request->request('device_id', ''));
        $cacheKey = 'platform:bootstrap:' . md5($terminal . '|' . $version . '|' . $deviceId);
        $cached = cache($cacheKey);
        if (is_array($cached) && $cached) {
            $this->success('', $cached);
        }
        $pages = PlatformPage::where('status', 'normal')
            ->where('terminal', 'in', ['common', $terminal])
            ->field('page_key,title,config_json,version')
            ->order('weigh desc,id desc')
            ->select();
        $configs = [];
        foreach ($pages as $row) {
            $config = json_decode($row['config_json'], true);
            if (!is_array($config)) {
                $config = [];
            }
            $configs[] = [
                'page_key' => $row['page_key'],
                'title' => $row['title'],
                'version' => (int)$row['version'],
                'config' => $config
            ];
        }
        $release = PlatformConfigRelease::where('terminal', $terminal)
            ->where('status', 'published')
            ->order('release_version desc,id desc')
            ->find();
        if ($release && $release['snapshot_json']) {
            $snapshot = json_decode($release['snapshot_json'], true);
            if (is_array($snapshot) && isset($snapshot['configs']) && is_array($snapshot['configs'])) {
                $configs = $snapshot['configs'];
            }
        }
        $policy = $this->buildVersionPolicy($terminal, $version, $deviceId);
        $experiments = $this->buildExperimentPayload($terminal, $deviceId);
        $userContext = $this->buildUserContext();
        $payload = [
            'terminal' => $terminal,
            'configs' => $configs,
            'user_context' => $userContext,
            'versiondata' => $version ? Version::check($version) : null,
            'policy' => $policy,
            'experiments' => $experiments
        ];
        cache($cacheKey, $payload, 30);
        $this->success('', $payload);
    }

    public function games()
    {
        $page = max(1, (int)$this->request->request('page', 1));
        $listRows = min(50, max(1, (int)$this->request->request('list_rows', 12)));
        $keyword = trim($this->request->request('keyword', ''));
        $cacheKey = 'platform:games:' . md5($page . '|' . $listRows . '|' . $keyword);
        $cached = cache($cacheKey);
        if (is_array($cached) && $cached) {
            $this->success('', $cached);
        }
        $query = PlatformGame::where('status', 'normal');
        if ($keyword !== '') {
            $query->where('title|subtitle|tags', 'like', "%{$keyword}%");
        }
        $total = PlatformGame::where('status', 'normal')
            ->where(function ($subQuery) use ($keyword) {
                if ($keyword !== '') {
                    $subQuery->where('title|subtitle|tags', 'like', "%{$keyword}%");
                }
            })->count();
        $list = $query->field('id,title,subtitle,slug,cover,tags,is_member_only,weigh,updatetime')
            ->order('weigh desc,id desc')
            ->page($page, $listRows)
            ->select()->toArray();
        $payload = [
            'total' => $total,
            'page' => $page,
            'list_rows' => $listRows,
            'list' => $list
        ];
        cache($cacheKey, $payload, 30);
        $this->success('', $payload);
    }

    public function game()
    {
        $id = (int)$this->request->request('id', 0);
        $slug = trim($this->request->request('slug', ''));
        if (!$id && !$slug) {
            $this->error(__('Invalid parameters'));
        }
        $cacheKey = 'platform:game:' . md5($id . '|' . $slug);
        $cached = cache($cacheKey);
        if (is_array($cached) && $cached) {
            $this->success('', $cached);
        }
        $query = PlatformGame::where('status', 'normal');
        if ($id) {
            $query->where('id', $id);
        } else {
            $query->where('slug', $slug);
        }
        $row = $query->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $data = $row->toArray();
        $data['carousel'] = $data['carousel'] ? json_decode($data['carousel'], true) : [];
        $data['resource_links'] = $data['resource_links'] ? json_decode($data['resource_links'], true) : [];
        $data['repair_profile'] = $data['repair_profile'] ? json_decode($data['repair_profile'], true) : [];
        if (!is_array($data['carousel'])) {
            $data['carousel'] = [];
        }
        if (!is_array($data['resource_links'])) {
            $data['resource_links'] = [];
        }
        if (!is_array($data['repair_profile'])) {
            $data['repair_profile'] = [];
        }
        $resourceRows = PlatformGameResource::where('game_id', (int)$row['id'])
            ->where('status', 'normal')
            ->field('id,resource_type,name,version,file_path,file_hash,file_size,channel_key,priority,extra_json')
            ->order('priority desc,weigh desc,id desc')
            ->select()
            ->toArray();
        $userContext = $this->buildUserContext();
        $channels = PlatformDownloadChannel::where('status', 'normal')->column('base_url', 'channel_key');
        $resources = [];
        foreach ($resourceRows as $item) {
            $item['extra_json'] = $item['extra_json'] ? json_decode($item['extra_json'], true) : [];
            if (!is_array($item['extra_json'])) {
                $item['extra_json'] = [];
            }
            $item['access'] = $this->buildResourceAccess($item['extra_json'], $userContext);
            $item['download_options'] = [
                'aria2' => $this->buildAria2Options($item['extra_json']),
                'package' => $this->buildPackageOptions($item['extra_json'])
            ];
            $item['download_url'] = $item['access']['visible'] ? $this->resolveDownloadUrl($item['channel_key'], $item['file_path'], $channels) : '';
            $resources[] = $item;
        }
        if ($resources) {
            $data['resources'] = $resources;
        }
        cache($cacheKey, $data, 30);
        $this->success('', $data);
    }

    /**
     * 游戏下载信息（独立端点，供前端 detail 页面并行调用）
     */
    public function downloadInfo()
    {
        $gameId = (int)$this->request->request('game_id', 0);
        $resourceId = (int)$this->request->request('resource_id', 0);
        $resourceType = trim((string)$this->request->request('resource_type', ''));

        if ($gameId <= 0) {
            $this->error(__('Invalid parameters'));
        }

        $query = PlatformGameResource::where('status', 'normal')->where('game_id', $gameId);
        if ($resourceId > 0) {
            $query->where('id', $resourceId);
        }
        if ($resourceType !== '') {
            $query->where('resource_type', $resourceType);
        }

        $rows = $query
            ->field('id,resource_type,name,version,file_path,file_hash,file_size,channel_key,priority,extra_json')
            ->order('priority desc,weigh desc,id desc')
            ->select()
            ->toArray();

        $userContext = $this->buildUserContext();
        $channels = PlatformDownloadChannel::where('status', 'normal')->column('base_url', 'channel_key');

        foreach ($rows as &$item) {
            $item['extra_json'] = $item['extra_json'] ? json_decode($item['extra_json'], true) : [];
            if (!is_array($item['extra_json'])) {
                $item['extra_json'] = [];
            }
            $item['access'] = $this->buildResourceAccess($item['extra_json'], $userContext);
            $item['download_options'] = [
                'aria2' => $this->buildAria2Options($item['extra_json']),
                'package' => $this->buildPackageOptions($item['extra_json'])
            ];
            $item['download_url'] = $item['access']['visible']
                ? $this->resolveDownloadUrl($item['channel_key'], $item['file_path'], $channels)
                : '';
        }
        unset($item);

        $this->success('', [
            'game_id' => $gameId,
            'resource_id' => $resourceId ?: null,
            'resource_type' => $resourceType,
            'list' => $rows
        ]);
    }

    public function resources()
    {
        $gameId = (int)$this->request->request('game_id', 0);
        $resourceType = trim($this->request->request('resource_type', ''));
        if ($gameId <= 0) {
            $this->error(__('Invalid parameters'));
        }
        $query = PlatformGameResource::where('status', 'normal')->where('game_id', $gameId);
        if ($resourceType !== '') {
            $query->where('resource_type', $resourceType);
        }
        $rows = $query->field('id,resource_type,name,version,file_path,file_hash,file_size,channel_key,priority,extra_json')
            ->order('priority desc,weigh desc,id desc')
            ->select()->toArray();
        $userContext = $this->buildUserContext();
        $channels = PlatformDownloadChannel::where('status', 'normal')->column('base_url', 'channel_key');
        foreach ($rows as &$item) {
            $item['extra_json'] = $item['extra_json'] ? json_decode($item['extra_json'], true) : [];
            if (!is_array($item['extra_json'])) {
                $item['extra_json'] = [];
            }
            $item['access'] = $this->buildResourceAccess($item['extra_json'], $userContext);
            $item['download_options'] = [
                'aria2' => $this->buildAria2Options($item['extra_json']),
                'package' => $this->buildPackageOptions($item['extra_json'])
            ];
            $item['download_url'] = $item['access']['visible'] ? $this->resolveDownloadUrl($item['channel_key'], $item['file_path'], $channels) : '';
        }
        unset($item);
        $this->success('', ['game_id' => $gameId, 'resource_type' => $resourceType, 'list' => $rows]);
    }

    public function channels()
    {
        $rows = PlatformDownloadChannel::where('status', 'normal')
            ->field('id,name,channel_key,base_url,priority,weigh')
            ->order('priority desc,weigh desc,id desc')
            ->select()->toArray();
        $this->success('', ['list' => $rows]);
    }

    public function nodeHealth()
    {
        $channelKey = trim((string)$this->request->request('channel_key', ''));
        $query = PlatformNodeHealth::field('channel_key,endpoint,latency_ms,http_code,status,detail,createtime')
            ->order('id desc');
        if ($channelKey !== '') {
            $query->where('channel_key', $channelKey);
        }
        $rows = $query->limit(50)->select()->toArray();
        $this->success('', ['channel_key' => $channelKey, 'list' => $rows]);
    }

    public function materials()
    {
        $type = trim((string)$this->request->request('material_type', ''));
        $query = PlatformMaterial::where('status', 'normal');
        if ($type !== '') {
            $query->where('material_type', $type);
        }
        $rows = $query->field('id,name,material_type,url,thumb,size,mime,tags')
            ->order('weigh desc,id desc')
            ->select()
            ->toArray();
        $this->success('', ['material_type' => $type, 'list' => $rows]);
    }

    public function downloadPlan()
    {
        $gameId = (int)$this->request->request('game_id', 0);
        $resourceType = trim($this->request->request('resource_type', 'game'));
        $preferredChannel = trim($this->request->request('preferred_channel', ''));
        if ($gameId <= 0) {
            $this->error(__('Invalid parameters'));
        }
        $channels = PlatformDownloadChannel::where('status', 'normal')
            ->field('channel_key,name,base_url,priority,weigh')
            ->order('priority desc,weigh desc,id desc')
            ->select()->toArray();
        $channelMap = [];
        foreach ($channels as $item) {
            $channelMap[$item['channel_key']] = $item;
        }
        $resources = PlatformGameResource::where('status', 'normal')
            ->where('game_id', $gameId)
            ->where('resource_type', $resourceType)
            ->field('id,name,version,file_path,file_hash,file_size,channel_key,priority,extra_json')
            ->order('priority desc,weigh desc,id desc')
            ->select()->toArray();
        $userContext = $this->buildUserContext();
        $healthMap = $this->buildChannelHealthMap();
        $result = [];
        foreach ($resources as $item) {
            $item['extra_json'] = $item['extra_json'] ? json_decode($item['extra_json'], true) : [];
            if (!is_array($item['extra_json'])) {
                $item['extra_json'] = [];
            }
            $item['access'] = $this->buildResourceAccess($item['extra_json'], $userContext);
            $item['download_options'] = [
                'aria2' => $this->buildAria2Options($item['extra_json']),
                'package' => $this->buildPackageOptions($item['extra_json'])
            ];
            $item['download_url'] = $item['access']['visible'] ? $this->resolveDownloadUrl($item['channel_key'], $item['file_path'], array_column($channels, 'base_url', 'channel_key')) : '';
            $item['channel'] = isset($channelMap[$item['channel_key']]) ? $channelMap[$item['channel_key']] : null;
            $item['health_status'] = isset($healthMap[$item['channel_key']]) ? $healthMap[$item['channel_key']] : 'unknown';
            $result[] = $item;
        }
        usort($result, function ($a, $b) {
            $scoreA = $this->healthScore(isset($a['health_status']) ? $a['health_status'] : 'unknown');
            $scoreB = $this->healthScore(isset($b['health_status']) ? $b['health_status'] : 'unknown');
            if ($scoreA !== $scoreB) {
                return $scoreB - $scoreA;
            }
            return ((int)($b['priority'] ?? 0)) - ((int)($a['priority'] ?? 0));
        });
        if ($preferredChannel !== '') {
            usort($result, function ($a, $b) use ($preferredChannel) {
                if ($a['channel_key'] === $preferredChannel && $b['channel_key'] !== $preferredChannel) {
                    return -1;
                }
                if ($a['channel_key'] !== $preferredChannel && $b['channel_key'] === $preferredChannel) {
                    return 1;
                }
                return ($b['priority'] ?? 0) - ($a['priority'] ?? 0);
            });
        }
        $this->success('', ['game_id' => $gameId, 'resource_type' => $resourceType, 'preferred_channel' => $preferredChannel, 'user_context' => $userContext, 'list' => $result]);
    }

    public function payChannels()
    {
        $rows = PlatformPaymentChannel::where('status', 'normal')
            ->field('name,channel_key,app_id,merchant_id,priority,weigh')
            ->order('priority desc,weigh desc,id desc')
            ->select()
            ->toArray();
        $this->success('', ['list' => $rows]);
    }

    public function versionPolicy()
    {
        $terminal = trim((string)$this->request->request('terminal', 'client'));
        $version = trim((string)$this->request->request('version', ''));
        $deviceId = trim((string)$this->request->request('device_id', ''));
        $this->success('', $this->buildVersionPolicy($terminal, $version, $deviceId));
    }

    public function publishConfig()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->assertRateLimit('publishconfig', 20, 60);
        $terminal = trim((string)$this->request->post('terminal', 'client'));
        $remark = trim((string)$this->request->post('remark', ''));
        $pages = PlatformPage::where('status', 'normal')
            ->where('terminal', 'in', ['common', $terminal])
            ->field('page_key,title,config_json,version')
            ->order('weigh desc,id desc')
            ->select()
            ->toArray();
        $configs = [];
        $maxVersion = 1;
        foreach ($pages as $row) {
            $config = $row['config_json'] ? json_decode($row['config_json'], true) : [];
            if (!is_array($config)) {
                $config = [];
            }
            $configs[] = ['page_key' => $row['page_key'], 'title' => $row['title'], 'version' => (int)$row['version'], 'config' => $config];
            $maxVersion = max($maxVersion, (int)$row['version']);
        }
        $releaseNo = $this->generateReleaseNo();
        PlatformConfigRelease::create([
            'release_no' => $releaseNo,
            'terminal' => $terminal,
            'release_version' => $maxVersion,
            'status' => 'published',
            'snapshot_json' => json_encode(['terminal' => $terminal, 'configs' => $configs], JSON_UNESCAPED_UNICODE),
            'operator_id' => (int)$this->auth->id,
            'remark' => $remark
        ]);
        $this->success('', ['release_no' => $releaseNo, 'terminal' => $terminal, 'release_version' => $maxVersion]);
    }

    public function rollbackConfig()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->assertRateLimit('rollbackconfig', 20, 60);
        $releaseNo = trim((string)$this->request->post('release_no', ''));
        $release = PlatformConfigRelease::where('release_no', $releaseNo)->find();
        if (!$release) {
            $this->error(__('No Results were found'));
        }
        $snapshot = $release['snapshot_json'] ? json_decode($release['snapshot_json'], true) : [];
        if (!is_array($snapshot) || !isset($snapshot['configs']) || !is_array($snapshot['configs'])) {
            $this->error(__('Invalid parameters'));
        }
        Db::startTrans();
        try {
            foreach ($snapshot['configs'] as $cfg) {
                if (!isset($cfg['page_key']) || !isset($cfg['config']) || !is_array($cfg['config'])) {
                    continue;
                }
                PlatformPage::where('page_key', (string)$cfg['page_key'])
                    ->where('terminal', 'in', ['common', (string)$release['terminal']])
                    ->update([
                        'config_json' => json_encode($cfg['config'], JSON_UNESCAPED_UNICODE),
                        'version' => isset($cfg['version']) ? (int)$cfg['version'] : 1
                    ]);
            }
            PlatformConfigRelease::create([
                'release_no' => $this->generateReleaseNo(),
                'terminal' => (string)$release['terminal'],
                'release_version' => (int)$release['release_version'],
                'status' => 'rollback',
                'snapshot_json' => $release['snapshot_json'],
                'operator_id' => (int)$this->auth->id,
                'remark' => 'rollback_from:' . $releaseNo
            ]);
            PlatformRollbackTask::create([
                'task_no' => 'RB' . date('YmdHis') . mt_rand(10000, 99999),
                'trigger_type' => 'manual',
                'terminal' => (string)$release['terminal'],
                'from_release_no' => $releaseNo,
                'to_release_no' => '',
                'status' => 'done',
                'detail' => 'manual_rollback'
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error(__('Server is busy'));
        }
        $this->success('', ['release_no' => $releaseNo, 'status' => 'rollback']);
    }

    public function repairPlan()
    {
        $gameId = (int)$this->request->request('game_id', 0);
        $repairType = trim((string)$this->request->request('repair_type', 'common'));
        $query = PlatformRepairTemplate::where('status', 'normal')->where('repair_type', $repairType);
        if ($gameId > 0) {
            $query->where('game_id', 'in', [0, $gameId]);
        } else {
            $query->where('game_id', 0);
        }
        $templates = $query->field('id,name,template_key,game_id,repair_type,steps_json,version')
            ->order('game_id desc,version desc,id desc')
            ->select()
            ->toArray();
        foreach ($templates as &$item) {
            $item['steps'] = $item['steps_json'] ? json_decode($item['steps_json'], true) : [];
            if (!is_array($item['steps'])) {
                $item['steps'] = [];
            }
            unset($item['steps_json']);
        }
        unset($item);
        $this->success('', ['game_id' => $gameId, 'repair_type' => $repairType, 'list' => $templates]);
    }

    public function pricing()
    {
        $gameId = (int)$this->request->request('game_id', 0);
        $itemType = trim((string)$this->request->request('item_type', 'game'));
        $itemName = trim((string)$this->request->request('item_name', ''));
        if ($gameId <= 0) {
            $this->error(__('Invalid parameters'));
        }
        $itemName = $itemName ?: (string)PlatformGame::where('id', $gameId)->value('title');
        $priceRet = $this->calcPrice($gameId, $itemType, $itemName, (int)$this->auth->id);
        $this->success('', [
            'game_id' => $gameId,
            'item_type' => $itemType,
            'item_name' => $itemName,
            'price' => $priceRet['price'],
            'member_price' => $priceRet['member_price'],
            'level_discount' => $priceRet['level_discount']
        ]);
    }

    public function createOrder()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $gameId = (int)$this->request->post('game_id', 0);
        $itemType = trim((string)$this->request->post('item_type', 'game'));
        $itemName = trim((string)$this->request->post('item_name', ''));
        $channelKey = trim((string)$this->request->post('channel_key', ''));
        $idemKey = trim((string)$this->request->post('idem_key', ''));
        if ($gameId <= 0 || $channelKey === '') {
            $this->error(__('Invalid parameters'));
        }
        $channel = PlatformPaymentChannel::where('status', 'normal')->where('channel_key', $channelKey)->find();
        if (!$channel) {
            $this->error(__('No Results were found'));
        }
        $game = PlatformGame::where('status', 'normal')->where('id', $gameId)->find();
        if (!$game) {
            $this->error(__('No Results were found'));
        }
        if ($itemName === '') {
            $itemName = (string)$game['title'];
        }
        if ($idemKey !== '' && (int)$this->auth->id > 0) {
            $exists = PlatformOrder::where('user_id', (int)$this->auth->id)->where('idem_key', $idemKey)->find();
            if ($exists) {
                $this->success('', [
                    'order_no' => (string)$exists['order_no'],
                    'pay_status' => (string)$exists['pay_status'],
                    'notify_payload' => []
                ]);
            }
        }
        $priceRet = $this->calcPrice($gameId, $itemType, $itemName, (int)$this->auth->id);
        $amount = round((float)$this->request->post('amount', 0), 2);
        if ($amount <= 0) {
            $amount = (float)$priceRet['price'];
        }
        if ($amount <= 0) {
            $this->error(__('Invalid parameters'));
        }
        $orderNo = $this->generateOrderNo();
        $order = new PlatformOrder();
        $order->save([
            'order_no' => $orderNo,
            'idem_key' => $idemKey !== '' ? $idemKey : null,
            'user_id' => (int)$this->auth->id,
            'game_id' => $gameId,
            'item_type' => $itemType,
            'item_name' => $itemName,
            'channel_key' => $channelKey,
            'amount' => $amount,
            'pay_status' => 'created',
            'meta_json' => json_encode($this->extractMeta($this->request->post('meta', '')), JSON_UNESCAPED_UNICODE)
        ]);
        $payload = [
            'order_no' => $orderNo,
            'channel_key' => $channelKey,
            'amount' => (string)$amount,
            'timestamp' => (string)time()
        ];
        $payload['sign'] = $this->buildNotifySign($payload, (string)$channel['notify_secret']);
        $this->success('', [
            'order_no' => $orderNo,
            'pay_status' => 'created',
            'price' => $priceRet['price'],
            'notify_payload' => $payload
        ]);
    }

    public function myOrders()
    {
        $page = max(1, (int)$this->request->request('page', 1));
        $listRows = min(50, max(1, (int)$this->request->request('list_rows', 20)));
        $query = PlatformOrder::where('user_id', (int)$this->auth->id);
        $total = PlatformOrder::where('user_id', (int)$this->auth->id)->count();
        $list = $query->field('order_no,game_id,item_type,item_name,channel_key,amount,pay_status,paid_at,createtime')
            ->order('id desc')
            ->page($page, $listRows)
            ->select()
            ->toArray();
        $this->success('', ['total' => $total, 'page' => $page, 'list_rows' => $listRows, 'list' => $list]);
    }

    public function myAssets()
    {
        $rows = PlatformUserAsset::where('user_id', (int)$this->auth->id)
            ->field('game_id,asset_type,asset_name,status,expiretime,source_order_no,createtime')
            ->order('id desc')
            ->select()
            ->toArray();
        $this->success('', ['list' => $rows]);
    }

    public function myProfile()
    {
        $user = User::get((int)$this->auth->id);
        if (!$user) {
            $this->error(__('No Results were found'));
        }
        $assets = PlatformUserAsset::where('user_id', (int)$this->auth->id)
            ->field('game_id,asset_type,asset_name,status,expiretime,source_order_no,createtime')
            ->order('id desc')
            ->limit(100)
            ->select()
            ->toArray();
        $memberExpire = $this->getUserViptimeValue($user);
        $this->success('', [

            'user' => [
                'id' => (int)$user['id'],
                'username' => (string)$user['username'],
                'nickname' => (string)$user['nickname'],
                'group_id' => (int)$user['group_id'],
                'level' => (int)$user['level'],
                'score' => (int)$user['score'],
                'is_member' => $memberExpire > time(),
                'member_expire' => $memberExpire
            ],
            'assets' => $assets
        ]);
    }

    public function reconcileOrders()
    {
        $bizDate = trim((string)$this->request->request('biz_date', date('Y-m-d')));
        $channelKey = trim((string)$this->request->request('channel_key', ''));
        $start = strtotime($bizDate . ' 00:00:00');
        $end = strtotime($bizDate . ' 23:59:59');
        if ($start <= 0 || $end <= 0) {
            $this->error(__('Invalid parameters'));
        }
        $query = PlatformOrder::where('createtime', 'between', [$start, $end]);
        if ($channelKey !== '') {
            $query->where('channel_key', $channelKey);
        }
        $orders = $query->field('order_no,pay_status,channel_key')->select()->toArray();
        $orderTotal = count($orders);
        $paidOrders = array_filter($orders, function ($row) {
            return isset($row['pay_status']) && $row['pay_status'] === 'paid';
        });
        $paidTotal = count($paidOrders);
        $mismatchTotal = 0;
        foreach ($paidOrders as $row) {
            $assetExists = PlatformUserAsset::where('source_order_no', $row['order_no'])->count();
            if (!$assetExists) {
                $mismatchTotal++;
                $this->createOrderAlert($row['order_no'], 'asset_missing', 'warning', 'paid_order_without_asset');
            }
        }
        $taskNo = $this->generateTaskNo();
        PlatformReconcileTask::create([
            'task_no' => $taskNo,
            'biz_date' => $bizDate,
            'channel_key' => $channelKey,
            'order_total' => $orderTotal,
            'paid_total' => $paidTotal,
            'mismatch_total' => $mismatchTotal,
            'status' => 'done',
            'result_json' => json_encode(['order_total' => $orderTotal, 'paid_total' => $paidTotal, 'mismatch_total' => $mismatchTotal], JSON_UNESCAPED_UNICODE)
        ]);
        $this->success('', ['task_no' => $taskNo, 'order_total' => $orderTotal, 'paid_total' => $paidTotal, 'mismatch_total' => $mismatchTotal]);
    }

    public function paymentNotify()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->assertRateLimit('paymentnotify', 120, 60);
        $orderNo = trim((string)$this->request->post('order_no', ''));
        $channelKey = trim((string)$this->request->post('channel_key', ''));
        $amount = (string)$this->request->post('amount', '');
        $timestamp = (string)$this->request->post('timestamp', '');
        $sign = trim((string)$this->request->post('sign', ''));
        if ($orderNo === '' || $channelKey === '' || $amount === '' || $timestamp === '' || $sign === '') {
            $this->error(__('Invalid parameters'));
        }
        $channel = PlatformPaymentChannel::where('status', 'normal')->where('channel_key', $channelKey)->find();
        if (!$channel) {
            $this->error(__('No Results were found'));
        }
        $payload = ['order_no' => $orderNo, 'channel_key' => $channelKey, 'amount' => $amount, 'timestamp' => $timestamp];
        $expectSign = $this->buildNotifySign($payload, (string)$channel['notify_secret']);
        if (!hash_equals($expectSign, $sign)) {
            $target = (string)$this->request->ip();
            $this->createOpsAlert('payment_sign_invalid', 'critical', $target, 'order:' . $orderNo);
            $this->detectAnomalyBurst('payment_sign_invalid', $target, 10, 600);
            $this->error(__('Invalid parameters'));
        }
        Db::startTrans();
        try {
            $order = PlatformOrder::where('order_no', $orderNo)->lock(true)->find();
            if (!$order) {
                Db::rollback();
                $this->error(__('No Results were found'));
            }
            if ((string)$order['channel_key'] !== $channelKey) {
                $this->createOrderAlert($orderNo, 'channel_mismatch', 'critical', 'notify_channel_not_match');
                Db::rollback();
                $this->error(__('Invalid parameters'));
            }
            if ((float)$amount !== round((float)$order['amount'], 2)) {
                $this->createOrderAlert($orderNo, 'amount_mismatch', 'critical', 'notify_amount_not_match');
                Db::rollback();
                $this->error(__('Invalid parameters'));
            }
            if ($order['pay_status'] !== 'paid') {
                $order->save([
                    'pay_status' => 'paid',
                    'paid_at' => time()
                ]);
                PlatformUserAsset::update([
                    'user_id' => (int)$order['user_id'],
                    'game_id' => (int)$order['game_id'],
                    'asset_type' => (string)$order['item_type'],
                    'asset_name' => (string)$order['item_name'],
                    'source_order_no' => (string)$order['order_no'],
                    'status' => 'active',
                    'meta_json' => (string)$order['meta_json']
                ], [
                    'user_id' => (int)$order['user_id'],
                    'game_id' => (int)$order['game_id'],
                    'asset_type' => (string)$order['item_type'],
                    'asset_name' => (string)$order['item_name']
                ]);
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->createOrderAlert($orderNo, 'notify_exception', 'warning', $e->getMessage());
            $target = (string)$this->request->ip();
            $this->createOpsAlert('payment_notify_exception', 'warning', $target, $e->getMessage());
            $this->detectAnomalyBurst('payment_notify_exception', $target, 10, 600);
            $this->error(__('Server is busy'));
        }
        $this->success('', ['order_no' => $orderNo, 'pay_status' => 'paid']);
    }

    public function reportDownload()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->assertRateLimit('reportdownload', 200, 60);
        $log = new PlatformDownloadLog();
        $log->save([
            'user_id' => (int)$this->auth->id,
            'game_id' => (int)$this->request->post('game_id', 0),
            'channel' => (string)$this->request->post('channel', ''),
            'resource_type' => (string)$this->request->post('resource_type', 'game'),
            'resource_name' => (string)$this->request->post('resource_name', ''),
            'status' => (string)$this->request->post('status', 'started'),
            'device_id' => (string)$this->request->post('device_id', ''),
            'client_version' => (string)$this->request->post('client_version', ''),
            'meta_json' => json_encode($this->extractMeta($this->request->post('meta', '')), JSON_UNESCAPED_UNICODE)
        ]);
        if ((string)$this->request->post('status', 'started') === 'failed') {
            $this->createOpsAlert('download_failed', 'warning', (string)$this->request->post('channel', ''), 'download_failed_report');
            $this->detectAnomalyBurst('download_failed', (string)$this->request->post('device_id', ''), 20, 600);
            $this->runAutoGuard('download');
        }
        $this->success('', ['id' => $log->id]);
    }

    public function reportInstall()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->assertRateLimit('reportinstall', 200, 60);
        $log = new PlatformInstallLog();
        $log->save([
            'user_id' => (int)$this->auth->id,
            'game_id' => (int)$this->request->post('game_id', 0),
            'status' => (string)$this->request->post('status', 'started'),
            'install_path' => (string)$this->request->post('install_path', ''),
            'device_id' => (string)$this->request->post('device_id', ''),
            'client_version' => (string)$this->request->post('client_version', ''),
            'error_code' => (string)$this->request->post('error_code', ''),
            'meta_json' => json_encode($this->extractMeta($this->request->post('meta', '')), JSON_UNESCAPED_UNICODE)
        ]);
        if ((string)$this->request->post('status', 'started') === 'failed') {
            $this->createOpsAlert('install_failed', 'warning', (string)$this->request->post('game_id', ''), 'install_failed_report');
            $this->detectAnomalyBurst('install_failed', (string)$this->request->post('device_id', ''), 20, 600);
            $this->runAutoGuard('install');
        }
        $this->success('', ['id' => $log->id]);
    }

    public function reportRepair()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->assertRateLimit('reportrepair', 200, 60);
        $gameId = (int)$this->request->post('game_id', 0);
        $repairType = (string)$this->request->post('repair_type', 'common');
        $actionKey = (string)$this->request->post('action_key', '');
        if ($actionKey !== '') {
            $allow = PlatformRepairAction::where('status', 'normal')
                ->where('action_key', $actionKey)
                ->where('repair_type', $repairType)
                ->where('game_id', 'in', [0, $gameId])
                ->count();
            if (!$allow) {
                $this->error(__('Invalid parameters'));
            }
        }
        $log = new PlatformRepairLog();
        $log->save([
            'user_id' => (int)$this->auth->id,
            'game_id' => $gameId,
            'repair_type' => $repairType,
            'action_key' => $actionKey,
            'status' => (string)$this->request->post('status', 'started'),
            'device_id' => (string)$this->request->post('device_id', ''),
            'client_version' => (string)$this->request->post('client_version', ''),
            'error_code' => (string)$this->request->post('error_code', ''),
            'meta_json' => json_encode($this->extractMeta($this->request->post('meta', '')), JSON_UNESCAPED_UNICODE)
        ]);
        if ((string)$this->request->post('status', 'started') === 'failed') {
            $this->createOpsAlert('repair_failed', 'warning', (string)$this->request->post('game_id', ''), 'repair_failed_report');
            $this->detectAnomalyBurst('repair_failed', (string)$this->request->post('device_id', ''), 20, 600);
            $this->runAutoGuard('repair');
        }
        $this->success('', ['id' => $log->id]);
    }

    public function reportNodeHealth()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->assertRateLimit('reportnodehealth', 120, 60);
        $row = new PlatformNodeHealth();
        $row->save([
            'channel_key' => trim((string)$this->request->post('channel_key', '')),
            'endpoint' => trim((string)$this->request->post('endpoint', '')),
            'latency_ms' => max(0, (int)$this->request->post('latency_ms', 0)),
            'http_code' => max(0, (int)$this->request->post('http_code', 0)),
            'status' => trim((string)$this->request->post('status', 'ok')),
            'detail' => trim((string)$this->request->post('detail', ''))
        ]);
        if ((string)$row['status'] !== 'ok') {
            $this->createOpsAlert('node_unhealthy', 'critical', (string)$row['channel_key'], (string)$row['detail']);
        }
        $this->success('', ['id' => $row->id]);
    }

    public function guardCheck()
    {
        $metric = trim((string)$this->request->request('metric', 'download'));
        $terminal = trim((string)$this->request->request('terminal', 'client'));
        $ret = $this->runAutoGuard($metric, $terminal);
        $this->success('', $ret);
    }

    public function stats()
    {
        $days = min(90, max(1, (int)$this->request->request('days', 7)));
        $gameId = (int)$this->request->request('game_id', 0);
        $startTime = strtotime(date('Y-m-d', time() - ($days - 1) * 86400));
        $downloadQuery = PlatformDownloadLog::where('createtime', '>=', $startTime);
        $installQuery = PlatformInstallLog::where('createtime', '>=', $startTime);
        $repairQuery = PlatformRepairLog::where('createtime', '>=', $startTime);
        $hotQuery = PlatformInstallLog::where('createtime', '>=', $startTime);
        if ($gameId > 0) {
            $downloadQuery->where('game_id', $gameId);
            $installQuery->where('game_id', $gameId);
            $repairQuery->where('game_id', $gameId);
            $hotQuery->where('game_id', $gameId);
        }
        $downloadCounter = $this->buildStatusCounter($downloadQuery->field('status')->select()->toArray());
        $installCounter = $this->buildStatusCounter($installQuery->field('status')->select()->toArray());
        $repairCounter = $this->buildStatusCounter($repairQuery->field('status')->select()->toArray());
        $downloadRows = $downloadQuery->field('game_id,channel,status')->select()->toArray();
        $installRows = $installQuery->field('game_id,status,error_code')->select()->toArray();
        $repairRows = $repairQuery->field('game_id,status,error_code')->select()->toArray();
        $hotRows = $hotQuery->field('createtime,status,meta_json')->select()->toArray();
        $hotRows = $this->filterHotUpdateRows($hotRows);
        $hotCounter = $this->buildStatusCounter($hotRows);
        $installErrorRows = $installQuery->where('status', 'failed')->field('error_code')->select()->toArray();
        $repairErrorRows = $repairQuery->where('status', 'failed')->field('error_code')->select()->toArray();
        $downloadSuccess = isset($downloadCounter['success']) ? (int)$downloadCounter['success'] : 0;
        $installSuccess = isset($installCounter['success']) ? (int)$installCounter['success'] : 0;
        $repairSuccess = isset($repairCounter['success']) ? (int)$repairCounter['success'] : 0;
        $nodeRows = PlatformNodeHealth::where('createtime', '>=', $startTime)->field('status,channel_key')->select()->toArray();
        $nodeCounter = $this->buildStatusCounter($nodeRows);
        $this->success('', [
            'days' => $days,
            'game_id' => $gameId,
            'games_total' => PlatformGame::where('status', 'normal')->count(),
            'games_member_only' => PlatformGame::where('status', 'normal')->where('is_member_only', 1)->count(),
            'pages_total' => PlatformPage::where('status', 'normal')->count(),
            'downloads' => $downloadCounter,
            'installs' => $installCounter,
            'repairs' => $repairCounter,
            'failure_rate' => [
                'download' => $this->buildFailureRate($downloadCounter),
                'install' => $this->buildFailureRate($installCounter),
                'repair' => $this->buildFailureRate($repairCounter)
            ],
            'top_error_codes' => [
                'install' => $this->buildTopErrorCodes($installErrorRows, 5),
                'repair' => $this->buildTopErrorCodes($repairErrorRows, 5)
            ],
            'funnel' => [
                'download_success' => $downloadSuccess,
                'install_success' => $installSuccess,
                'repair_success' => $repairSuccess,
                'install_conversion' => $downloadSuccess > 0 ? round($installSuccess / $downloadSuccess * 100, 2) : 0,
                'repair_conversion' => $installSuccess > 0 ? round($repairSuccess / $installSuccess * 100, 2) : 0
            ],
            'top_channels' => $this->buildTopByField($downloadRows, 'channel', 'success', 5),
            'top_games' => $this->buildTopGames($downloadRows, $installRows, $repairRows, 5),
            'hot_update' => [
                'counter' => $hotCounter,
                'failure_rate' => $this->buildFailureRate($hotCounter),
                'top_failed_steps' => $this->buildHotUpdateTop($hotRows, 'failed_step', 5),
                'top_target_versions' => $this->buildHotUpdateTop($hotRows, 'target_version', 5)
            ],
            'node_health' => $nodeCounter,
            'ops_alerts' => [
                'open' => PlatformOpsAlert::where('status', 'open')->count(),
                'critical_open' => PlatformOpsAlert::where('status', 'open')->where('level', 'critical')->count()
            ]
        ]);
    }

    public function trends()
    {
        $days = min(90, max(1, (int)$this->request->request('days', 14)));
        $gameId = (int)$this->request->request('game_id', 0);
        $startTime = strtotime(date('Y-m-d', time() - ($days - 1) * 86400));
        $downloadQuery = PlatformDownloadLog::where('createtime', '>=', $startTime);
        $installQuery = PlatformInstallLog::where('createtime', '>=', $startTime);
        $repairQuery = PlatformRepairLog::where('createtime', '>=', $startTime);
        $hotQuery = PlatformInstallLog::where('createtime', '>=', $startTime);
        if ($gameId > 0) {
            $downloadQuery->where('game_id', $gameId);
            $installQuery->where('game_id', $gameId);
            $repairQuery->where('game_id', $gameId);
            $hotQuery->where('game_id', $gameId);
        }
        $downloadRows = $downloadQuery->field('createtime,status')->select()->toArray();
        $installRows = $installQuery->field('createtime,status')->select()->toArray();
        $repairRows = $repairQuery->field('createtime,status')->select()->toArray();
        $hotRows = $hotQuery->field('createtime,status,meta_json')->select()->toArray();
        $hotRows = $this->filterHotUpdateRows($hotRows);
        $this->success('', [
            'days' => $days,
            'game_id' => $gameId,
            'labels' => $this->buildDateLabels($startTime, $days),
            'download_success' => $this->buildDailyCounter($downloadRows, $startTime, $days, 'success'),
            'download_failed' => $this->buildDailyCounter($downloadRows, $startTime, $days, 'failed'),
            'install_success' => $this->buildDailyCounter($installRows, $startTime, $days, 'success'),
            'install_failed' => $this->buildDailyCounter($installRows, $startTime, $days, 'failed'),
            'repair_success' => $this->buildDailyCounter($repairRows, $startTime, $days, 'success'),
            'repair_failed' => $this->buildDailyCounter($repairRows, $startTime, $days, 'failed'),
            'hot_update_success' => $this->buildDailyCounter($hotRows, $startTime, $days, 'success'),
            'hot_update_failed' => $this->buildDailyCounter($hotRows, $startTime, $days, 'failed')
        ]);
    }

    public function trendsHourly()
    {
        $days = min(30, max(1, (int)$this->request->request('days', 7)));
        $gameId = (int)$this->request->request('game_id', 0);
        $startTime = strtotime(date('Y-m-d', time() - ($days - 1) * 86400));
        $downloadQuery = PlatformDownloadLog::where('createtime', '>=', $startTime);
        $installQuery = PlatformInstallLog::where('createtime', '>=', $startTime);
        $repairQuery = PlatformRepairLog::where('createtime', '>=', $startTime);
        if ($gameId > 0) {
            $downloadQuery->where('game_id', $gameId);
            $installQuery->where('game_id', $gameId);
            $repairQuery->where('game_id', $gameId);
        }
        $downloadRows = $downloadQuery->field('createtime,status')->select()->toArray();
        $installRows = $installQuery->field('createtime,status')->select()->toArray();
        $repairRows = $repairQuery->field('createtime,status')->select()->toArray();
        $this->success('', [
            'days' => $days,
            'game_id' => $gameId,
            'labels' => $this->buildHourLabels(),
            'download_success' => $this->buildHourlyCounter($downloadRows, 'success'),
            'install_success' => $this->buildHourlyCounter($installRows, 'success'),
            'repair_success' => $this->buildHourlyCounter($repairRows, 'success')
        ]);
    }

    public function messages()
    {
        $page = max(1, (int)$this->request->request('page', 1));
        $listRows = min(50, max(1, (int)$this->request->request('list_rows', 20)));
        $query = PlatformMessage::where('status', 'approved');
        $total = PlatformMessage::where('status', 'approved')->count();
        $list = $query->field('id,nickname,content,createtime')
            ->order('id desc')
            ->page($page, $listRows)
            ->select()
            ->toArray();
        $this->success('', ['total' => $total, 'page' => $page, 'list_rows' => $listRows, 'list' => $list]);
    }

    public function submitMessage()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->assertRateLimit('submitmessage', 30, 60);
        $nickname = trim((string)$this->request->post('nickname', ''));
        $contact = trim((string)$this->request->post('contact', ''));
        $content = trim((string)$this->request->post('content', ''));
        if ($nickname === '' || $content === '') {
            $this->error(__('Invalid parameters'));
        }
        $row = new PlatformMessage();
        $row->save([
            'user_id' => (int)$this->auth->id,
            'nickname' => $nickname,
            'contact' => $contact,
            'content' => $content,
            'status' => 'pending'
        ]);
        $this->success('', ['id' => $row->id, 'status' => 'pending']);
    }

    public function reportEvent()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->assertRateLimit('reportevent', 240, 60);
        $eventName = trim((string)$this->request->post('event_name', ''));
        if ($eventName === '') {
            $this->error(__('Invalid parameters'));
        }
        $props = $this->extractMeta($this->request->post('props', ''));
        $row = new PlatformEventLog();
        $row->save([
            'user_id' => (int)$this->auth->id,
            'device_id' => trim((string)$this->request->post('device_id', '')),
            'terminal' => trim((string)$this->request->post('terminal', 'client')),
            'event_name' => $eventName,
            'page_key' => trim((string)$this->request->post('page_key', '')),
            'channel' => trim((string)$this->request->post('channel', '')),
            'game_id' => (int)$this->request->post('game_id', 0),
            'session_id' => trim((string)$this->request->post('session_id', '')),
            'trace_id' => trim((string)$this->request->post('trace_id', '')),
            'props_json' => json_encode($props, JSON_UNESCAPED_UNICODE)
        ]);
        $this->refreshUserSegment((int)$this->auth->id);
        $this->success('', ['id' => $row->id]);
    }

    public function funnel()
    {
        $days = min(90, max(1, (int)$this->request->request('days', 7)));
        $startTime = strtotime(date('Y-m-d', time() - ($days - 1) * 86400));
        $browse = PlatformEventLog::where('createtime', '>=', $startTime)->where('event_name', 'browse')->count();
        $detail = PlatformEventLog::where('createtime', '>=', $startTime)->where('event_name', 'detail_view')->count();
        $download = PlatformDownloadLog::where('createtime', '>=', $startTime)->where('status', 'success')->count();
        $install = PlatformInstallLog::where('createtime', '>=', $startTime)->where('status', 'success')->count();
        $pay = PlatformOrder::where('createtime', '>=', $startTime)->where('pay_status', 'paid')->count();
        $this->success('', [
            'days' => $days,
            'funnel' => [
                'browse' => $browse,
                'detail_view' => $detail,
                'download_success' => $download,
                'install_success' => $install,
                'pay_success' => $pay
            ]
        ]);
    }

    public function segments()
    {
        $tag = trim((string)$this->request->request('segment_tag', ''));
        $query = PlatformUserSegment::order('id desc');
        if ($tag !== '') {
            $query->where('segment_tag', $tag);
        }
        $list = $query->field('user_id,segment_tag,score,active_days_30,pay_amount_30,last_event_time,snapshot_date')
            ->limit(100)
            ->select()
            ->toArray();
        $this->success('', ['segment_tag' => $tag, 'list' => $list]);
    }

    public function experiments()
    {
        $terminal = trim((string)$this->request->request('terminal', 'client'));
        $deviceId = trim((string)$this->request->request('device_id', ''));
        $list = PlatformAbExperiment::where('terminal', $terminal)->where('status', 'running')->select()->toArray();
        $result = [];
        foreach ($list as $row) {
            $variant = $this->pickExperimentVariant($row, $deviceId);
            if ($variant === '') {
                continue;
            }
            $result[] = [
                'experiment_key' => (string)$row['experiment_key'],
                'variant' => $variant,
                'metric_event' => (string)$row['metric_event']
            ];
        }
        $this->success('', ['terminal' => $terminal, 'list' => $result]);
    }

    public function gameOptions()
    {
        $list = PlatformGame::where('status', 'normal')
            ->field('id,title')
            ->order('weigh desc,id desc')
            ->select()
            ->toArray();
        $this->success('', ['list' => $list]);
    }

    public function cloudDownloadUrl()
    {
        $fileId = (int)$this->request->request('file_id', 0);
        $accountId = (int)$this->request->request('account_id', 0);
        if ($fileId <= 0) {
            $this->error(__('Invalid parameters'));
        }

        $file = PlatformCloudFile::where('id', $fileId)->where('status', 'normal')->find();
        if (!$file) {
            $this->error(__('No Results were found'));
        }

        $shareCode = trim((string)$file['share_code']);
        $accessCode = trim((string)$file['access_code']);
        if ($shareCode === '' || $accessCode === '') {
            $this->error('分享码或访问码为空');
        }

        $accounts = $this->pickCloudAccounts($file, $accountId > 0 ? $accountId : null);
        if (!$accounts) {
            $this->error('无可用天翼云账号');
        }

        $errors = [];
        $successData = null;
        foreach ($accounts as $account) {
            $token = trim((string)$account['access_token']);
            if ($token === '') {
                $errors[] = '账号[' . $account['username'] . ']未配置AccessToken';
                continue;
            }
            try {
                $client = new Tianyiyun();
                if (!$client->checkUserInfo($token)) {
                    PlatformCloudAccount::where('id', (int)$account['id'])->update([
                        'last_check_time' => time(),
                        'last_check_status' => 'invalid',
                    ]);
                    $errors[] = '账号[' . $account['username'] . ']Token无效';
                    continue;
                }
                $url = $client->getFileDownloadUrl($shareCode, $accessCode, $token);
                PlatformCloudAccount::where('id', (int)$account['id'])->update([
                    'last_check_time' => time(),
                    'last_check_status' => 'valid',
                ]);
                $successData = [
                    'file_id' => (int)$file['id'],
                    'file_name' => (string)$file['name'],
                    'download_url' => $url,
                    'account_id' => (int)$account['id'],
                    'username' => (string)$account['username'],
                    'account_rule' => (string)$file['account_rule'],
                ];
                break;
            } catch (\Throwable $e) {
                PlatformCloudAccount::where('id', (int)$account['id'])->update([
                    'last_check_time' => time(),
                    'last_check_status' => 'invalid',
                ]);
                $message = trim((string)$e->getMessage());
                if ($message === '') {
                    $message = get_class($e);
                }
                $errors[] = '账号[' . $account['username'] . ']失败: ' . $message;
            }
        }

        if ($successData !== null) {
            $this->success('', $successData);
        }
        $this->error('全部账号尝试失败: ' . implode('；', $errors));

    }

    protected function pickCloudAccounts($file, $forceAccountId = null)
    {
        if ($forceAccountId) {
            $row = PlatformCloudAccount::where('id', (int)$forceAccountId)
                ->where('status', 'in', ['normal', 'limited'])
                ->find();
            return $row ? [$row] : [];
        }

        $ids = array_values(array_filter(array_map('intval', explode(',', (string)$file['account_ids']))));
        $query = PlatformCloudAccount::where('status', 'in', ['normal', 'limited']);
        if ($ids) {
            $query->where('id', 'in', $ids);
        }
        $rows = $query->order('weigh desc,id asc')->select()->toArray();
        if (!$rows) {
            return [];
        }

        if ($ids) {
            $indexed = [];
            foreach ($rows as $row) {
                $indexed[(int)$row['id']] = $row;
            }
            $ordered = [];
            foreach ($ids as $id) {
                if (isset($indexed[$id])) {
                    $ordered[] = $indexed[$id];
                    unset($indexed[$id]);
                }
            }
            foreach ($indexed as $item) {
                $ordered[] = $item;
            }
            $rows = $ordered;
        }

        if ((string)$file['account_rule'] === 'random') {
            shuffle($rows);
        }
        return $rows;
    }

    protected function extractMeta($meta)

    {

        if (!$meta) {
            return [];
        }
        if (is_array($meta)) {
            return $meta;
        }
        $decoded = json_decode($meta, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function buildStatusCounter($rows)
    {
        $counter = ['started' => 0, 'success' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $status = isset($row['status']) ? $row['status'] : 'started';
            if (!isset($counter[$status])) {
                $counter[$status] = 0;
            }
            $counter[$status]++;
        }
        $counter['total'] = array_sum($counter);
        return $counter;
    }

    protected function buildFailureRate($counter)
    {
        $total = isset($counter['total']) ? (int)$counter['total'] : 0;
        $failed = isset($counter['failed']) ? (int)$counter['failed'] : 0;
        if ($total <= 0) {
            return 0;
        }
        return round(($failed / $total) * 100, 2);
    }

    protected function buildTopErrorCodes($rows, $limit = 5)
    {
        $counter = [];
        foreach ($rows as $row) {
            $code = isset($row['error_code']) ? trim((string)$row['error_code']) : '';
            if ($code === '') {
                continue;
            }
            if (!isset($counter[$code])) {
                $counter[$code] = 0;
            }
            $counter[$code]++;
        }
        arsort($counter);
        $result = [];
        $i = 0;
        foreach ($counter as $code => $count) {
            $result[] = ['code' => $code, 'count' => $count];
            $i++;
            if ($i >= $limit) {
                break;
            }
        }
        return $result;
    }

    protected function buildTopByField($rows, $field, $status = '', $limit = 5)
    {
        $counter = [];
        foreach ($rows as $row) {
            if ($status !== '' && (!isset($row['status']) || $row['status'] !== $status)) {
                continue;
            }
            $key = isset($row[$field]) ? trim((string)$row[$field]) : '';
            if ($key === '') {
                continue;
            }
            if (!isset($counter[$key])) {
                $counter[$key] = 0;
            }
            $counter[$key]++;
        }
        arsort($counter);
        $result = [];
        $i = 0;
        foreach ($counter as $key => $count) {
            $result[] = ['key' => $key, 'count' => $count];
            $i++;
            if ($i >= $limit) {
                break;
            }
        }
        return $result;
    }

    protected function buildTopGames($downloadRows, $installRows, $repairRows, $limit = 5)
    {
        $downloadCounter = $this->buildTopByField($downloadRows, 'game_id', 'success', 100);
        $installCounter = $this->buildTopByField($installRows, 'game_id', 'success', 100);
        $repairCounter = $this->buildTopByField($repairRows, 'game_id', 'success', 100);
        $map = [];
        foreach ($downloadCounter as $row) {
            $id = (int)$row['key'];
            if ($id <= 0) {
                continue;
            }
            $map[$id] = isset($map[$id]) ? $map[$id] : ['game_id' => $id, 'download_success' => 0, 'install_success' => 0, 'repair_success' => 0];
            $map[$id]['download_success'] = (int)$row['count'];
        }
        foreach ($installCounter as $row) {
            $id = (int)$row['key'];
            if ($id <= 0) {
                continue;
            }
            $map[$id] = isset($map[$id]) ? $map[$id] : ['game_id' => $id, 'download_success' => 0, 'install_success' => 0, 'repair_success' => 0];
            $map[$id]['install_success'] = (int)$row['count'];
        }
        foreach ($repairCounter as $row) {
            $id = (int)$row['key'];
            if ($id <= 0) {
                continue;
            }
            $map[$id] = isset($map[$id]) ? $map[$id] : ['game_id' => $id, 'download_success' => 0, 'install_success' => 0, 'repair_success' => 0];
            $map[$id]['repair_success'] = (int)$row['count'];
        }
        if (!$map) {
            return [];
        }
        $rows = array_values($map);
        foreach ($rows as &$row) {
            $row['score'] = $row['download_success'] + $row['install_success'] + $row['repair_success'];
        }
        unset($row);
        usort($rows, function ($a, $b) {
            if ($a['score'] == $b['score']) {
                return $b['game_id'] - $a['game_id'];
            }
            return $b['score'] - $a['score'];
        });
        $rows = array_slice($rows, 0, $limit);
        $ids = array_column($rows, 'game_id');
        $titleMap = $ids ? PlatformGame::where('id', 'in', $ids)->column('title', 'id') : [];
        foreach ($rows as &$row) {
            $row['title'] = isset($titleMap[$row['game_id']]) ? $titleMap[$row['game_id']] : (string)$row['game_id'];
        }
        unset($row);
        return $rows;
    }

    protected function buildDateLabels($startTime, $days)
    {
        $labels = [];
        for ($i = 0; $i < $days; $i++) {
            $labels[] = date('m-d', $startTime + $i * 86400);
        }
        return $labels;
    }

    protected function buildDailyCounter($rows, $startTime, $days, $status)
    {
        $series = array_fill(0, $days, 0);
        foreach ($rows as $row) {
            if (!isset($row['createtime']) || !isset($row['status']) || $row['status'] !== $status) {
                continue;
            }
            $index = (int)floor(((int)$row['createtime'] - $startTime) / 86400);
            if ($index >= 0 && $index < $days) {
                $series[$index]++;
            }
        }
        return $series;
    }

    protected function buildHourLabels()
    {
        $labels = [];
        for ($i = 0; $i < 24; $i++) {
            $labels[] = str_pad((string)$i, 2, '0', STR_PAD_LEFT) . ':00';
        }
        return $labels;
    }

    protected function buildHourlyCounter($rows, $status)
    {
        $series = array_fill(0, 24, 0);
        foreach ($rows as $row) {
            if (!isset($row['createtime']) || !isset($row['status']) || $row['status'] !== $status) {
                continue;
            }
            $hour = (int)date('G', (int)$row['createtime']);
            if ($hour >= 0 && $hour < 24) {
                $series[$hour]++;
            }
        }
        return $series;
    }

    protected function buildUserContext()
    {
        $uid = (int)$this->auth->id;
        if ($uid <= 0) {
            return ['user_id' => 0, 'level' => 0, 'is_member' => false];
        }

        $userFields = ['id', 'level', 'group_id'];
        if ($this->userHasViptimeField()) {
            $userFields[] = 'viptime';
        }
        $user = User::where('id', $uid)->field(implode(',', $userFields))->find();
        if (!$user) {
            return ['user_id' => 0, 'level' => 0, 'is_member' => false];
        }

        $memberExpire = $this->getUserViptimeValue($user);
        $isMember = ((int)$user['group_id'] > 1) || ($memberExpire > time());
        return ['user_id' => (int)$user['id'], 'level' => (int)$user['level'], 'is_member' => $isMember];
    }

    protected function userHasViptimeField()
    {
        static $hasViptime = null;
        if ($hasViptime !== null) {
            return $hasViptime;
        }
        $fields = Db::name('user')->getTableFields();
        $hasViptime = is_array($fields) && in_array('viptime', $fields, true);
        return $hasViptime;
    }

    protected function getUserViptimeValue($user)
    {
        if (!$user || !$this->userHasViptimeField()) {
            return 0;
        }
        return isset($user['viptime']) ? (int)$user['viptime'] : 0;
    }


    protected function buildResourceAccess($extra, $userContext)
    {
        $extra = is_array($extra) ? $extra : [];
        $ctx = is_array($userContext) ? $userContext : ['level' => 0, 'is_member' => false];
        $level = (int)($ctx['level'] ?? 0);
        $isMember = (bool)($ctx['is_member'] ?? false);
        $minLevel = isset($extra['min_level']) ? (int)$extra['min_level'] : 0;
        $maxLevel = isset($extra['max_level']) ? (int)$extra['max_level'] : 0;
        $memberOnly = !empty($extra['member_only']);
        $visibleLevels = [];
        if (isset($extra['visible_levels']) && is_array($extra['visible_levels'])) {
            $visibleLevels = array_map('intval', $extra['visible_levels']);
        } elseif (isset($extra['visible_levels']) && is_string($extra['visible_levels']) && trim($extra['visible_levels']) !== '') {
            $visibleLevels = array_map('intval', array_filter(array_map('trim', explode(',', $extra['visible_levels'])), function ($v) {
                return $v !== '';
            }));
        }
        $visible = true;
        $reason = '';
        if ($memberOnly && !$isMember) {
            $visible = false;
            $reason = 'member_only';
        }
        if ($visible && $minLevel > 0 && $level < $minLevel) {
            $visible = false;
            $reason = 'level_low';
        }
        if ($visible && $maxLevel > 0 && $level > $maxLevel) {
            $visible = false;
            $reason = 'level_high';
        }
        if ($visible && $visibleLevels && !in_array($level, $visibleLevels, true)) {
            $visible = false;
            $reason = 'level_not_match';
        }
        return [
            'visible' => $visible,
            'reason' => $reason,
            'user_level' => $level,
            'is_member' => $isMember,
            'min_level' => $minLevel,
            'max_level' => $maxLevel,
            'member_only' => $memberOnly,
            'visible_levels' => $visibleLevels
        ];
    }

    protected function buildAria2Options($extra)
    {
        $extra = is_array($extra) ? $extra : [];
        $split = isset($extra['aria2_split']) ? (int)$extra['aria2_split'] : 8;
        $maxConn = isset($extra['aria2_max_connection_per_server']) ? (int)$extra['aria2_max_connection_per_server'] : 8;
        $minSplitSize = isset($extra['aria2_min_split_size']) ? trim((string)$extra['aria2_min_split_size']) : '4M';
        $maxTries = isset($extra['aria2_max_tries']) ? (int)$extra['aria2_max_tries'] : 5;
        $retryWait = isset($extra['aria2_retry_wait']) ? (int)$extra['aria2_retry_wait'] : 2;
        $split = max(1, min(64, $split));
        $maxConn = max(1, min(64, $maxConn));
        $maxTries = max(1, min(30, $maxTries));
        $retryWait = max(0, min(30, $retryWait));
        if ($minSplitSize === '') {
            $minSplitSize = '4M';
        }
        return [
            'split' => $split,
            'max_connection_per_server' => $maxConn,
            'min_split_size' => $minSplitSize,
            'max_tries' => $maxTries,
            'retry_wait' => $retryWait
        ];
    }

    protected function buildPackageOptions($extra)
    {
        $extra = is_array($extra) ? $extra : [];
        $archiveType = strtolower(trim((string)($extra['archive_type'] ?? 'auto')));
        if ($archiveType === '') {
            $archiveType = 'auto';
        }
        $extractDir = trim((string)($extra['extract_dir'] ?? ''));
        $installDir = trim((string)($extra['install_dir'] ?? ''));
        $deployMode = strtolower(trim((string)($extra['deploy_mode'] ?? 'atomic')));
        if ($deployMode === '') {
            $deployMode = 'atomic';
        }
        $keepBackups = isset($extra['keep_backups']) ? (int)$extra['keep_backups'] : 2;
        $keepBackups = max(0, min(10, $keepBackups));
        $autoExtract = array_key_exists('auto_extract', $extra) ? (bool)$extra['auto_extract'] : true;
        $verifyHash = array_key_exists('verify_hash', $extra) ? (bool)$extra['verify_hash'] : true;
        return [
            'archive_type' => $archiveType,
            'extract_dir' => $extractDir,
            'install_dir' => $installDir,
            'deploy_mode' => $deployMode,
            'keep_backups' => $keepBackups,
            'auto_extract' => $autoExtract,
            'verify_hash' => $verifyHash
        ];
    }

    protected function resolveDownloadUrl($channelKey, $filePath, $channels)
    {
        $filePath = trim((string)$filePath);
        if ($filePath === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $filePath)) {
            return $filePath;
        }
        $base = isset($channels[$channelKey]) ? rtrim((string)$channels[$channelKey], '/') : '';
        if ($base === '') {
            return $filePath;
        }
        return $base . '/' . ltrim($filePath, '/');
    }

    protected function generateOrderNo()
    {
        return date('YmdHis') . mt_rand(100000, 999999);
    }

    protected function generateTaskNo()
    {
        return 'RC' . date('YmdHis') . mt_rand(10000, 99999);
    }

    protected function calcPrice($gameId, $itemType, $itemName, $userId = 0)
    {
        $offer = PlatformProductOffer::where('status', 'normal')
            ->where('game_id', (int)$gameId)
            ->where('item_type', (string)$itemType)
            ->where('item_name', (string)$itemName)
            ->find();
        $price = $offer ? (float)$offer['base_price'] : 0;
        $memberPrice = $offer ? (float)$offer['member_price'] : 0;
        $levelDiscount = [];
        if ($offer && $offer['level_discount_json']) {
            $levelDiscount = json_decode($offer['level_discount_json'], true);
            if (!is_array($levelDiscount)) {
                $levelDiscount = [];
            }
        }
        $user = $userId > 0 ? User::get($userId) : null;
        if ($user && (int)$user['group_id'] > 1 && $memberPrice > 0) {
            $price = $memberPrice;
        }
        if ($user && isset($levelDiscount[(string)$user['level']])) {
            $ratio = (float)$levelDiscount[(string)$user['level']];
            if ($ratio > 0 && $ratio <= 1) {
                $price = round($price * $ratio, 2);
            }
        }
        return ['price' => $price, 'member_price' => $memberPrice, 'level_discount' => $levelDiscount];
    }

    protected function createOrderAlert($orderNo, $alertType, $level, $detail)
    {
        if ($orderNo === '') {
            return;
        }
        $exists = PlatformOrderAlert::where('order_no', $orderNo)->where('alert_type', $alertType)->where('status', 'open')->find();
        if ($exists) {
            return;
        }
        PlatformOrderAlert::create([
            'order_no' => (string)$orderNo,
            'alert_type' => (string)$alertType,
            'level' => (string)$level,
            'detail' => (string)$detail,
            'status' => 'open',
            'workorder_no' => 'WK' . date('YmdHis') . mt_rand(10000, 99999)
        ]);
    }

    protected function buildVersionPolicy($terminal, $clientVersion, $deviceId)
    {
        $strategy = PlatformVersionStrategy::where('status', 'normal')->where('terminal', $terminal)->find();
        if (!$strategy) {
            return [
                'terminal' => $terminal,
                'min_version' => '',
                'latest_version' => '',
                'force_version' => '',
                'need_update' => false,
                'force_update' => false,
                'gray_hit' => false,
                'switch' => ['hot_update' => $this->normalizeHotUpdate([], '')],
                'hot_update' => $this->normalizeHotUpdate([], '')
            ];
        }
        $minVersion = trim((string)$strategy['min_version']);
        $latestVersion = trim((string)$strategy['latest_version']);
        $forceVersion = trim((string)$strategy['force_version']);
        $needUpdate = $latestVersion !== '' && $clientVersion !== '' && $this->compareVersion($clientVersion, $latestVersion) < 0;
        $forceUpdate = false;
        if ($forceVersion !== '' && $clientVersion !== '' && $this->compareVersion($clientVersion, $forceVersion) < 0) {
            $forceUpdate = true;
        }
        if ($minVersion !== '' && $clientVersion !== '' && $this->compareVersion($clientVersion, $minVersion) < 0) {
            $forceUpdate = true;
        }
        $grayRatio = max(0, min(100, (int)$strategy['gray_ratio']));
        $grayHit = false;
        if ($grayRatio > 0 && $deviceId !== '') {
            $seed = abs(crc32($deviceId . '|' . (string)$terminal . '|' . (string)$strategy['gray_group_tag'])) % 100;
            $grayHit = $seed < $grayRatio;
        }
        $switch = $strategy['switch_json'] ? json_decode($strategy['switch_json'], true) : [];
        if (!is_array($switch)) {
            $switch = [];
        }
        $switch['hot_update'] = $this->normalizeHotUpdate(isset($switch['hot_update']) && is_array($switch['hot_update']) ? $switch['hot_update'] : [], $latestVersion);
        $hot = $switch['hot_update'];
        $hotGrayRatio = max(0, min(100, (int)($hot['gray_ratio'] ?? 0)));
        $hotGrayTag = trim((string)($hot['gray_group_tag'] ?? ''));
        $hotGrayHit = $this->buildGrayHit($terminal, $deviceId, $hotGrayRatio, $hotGrayTag);
        if (!empty($hot['force'])) {
            $hotGrayHit = true;
        }
        $hot['gray_hit'] = $hotGrayHit;
        $hot['enabled'] = !empty($hot['enabled']) && $hotGrayHit;
        $switch['hot_update'] = $hot;
        return [
            'terminal' => $terminal,
            'min_version' => $minVersion,
            'latest_version' => $latestVersion,
            'force_version' => $forceVersion,
            'need_update' => $needUpdate,
            'force_update' => $forceUpdate,
            'gray_hit' => $grayHit,
            'gray_ratio' => $grayRatio,
            'switch' => $switch,
            'hot_update' => $switch['hot_update']
        ];
    }

    protected function normalizeHotUpdate($hotUpdate, $latestVersion)
    {
        $hotUpdate = is_array($hotUpdate) ? $hotUpdate : [];
        return [
            'enabled' => !empty($hotUpdate['enabled']),
            'target_version' => trim((string)($hotUpdate['target_version'] ?? $latestVersion ?? '')),
            'package_url' => trim((string)($hotUpdate['package_url'] ?? '')),
            'sha256' => strtolower(trim((string)($hotUpdate['sha256'] ?? ''))),
            'archive_type' => strtolower(trim((string)($hotUpdate['archive_type'] ?? 'zip'))),
            'extract_subdir' => trim((string)($hotUpdate['extract_subdir'] ?? '')),
            'force' => !empty($hotUpdate['force']),
            'gray_ratio' => max(0, min(100, (int)($hotUpdate['gray_ratio'] ?? 0))),
            'gray_group_tag' => trim((string)($hotUpdate['gray_group_tag'] ?? '')),
            'gray_hit' => false,
            'downgrade_reason' => trim((string)($hotUpdate['downgrade_reason'] ?? '')),
            'auto_disabled_at' => (int)($hotUpdate['auto_disabled_at'] ?? 0)
        ];
    }

    protected function buildGrayHit($terminal, $deviceId, $grayRatio, $grayGroupTag)
    {
        $ratio = max(0, min(100, (int)$grayRatio));
        if ($ratio <= 0) {
            return true;
        }
        if ($deviceId === '') {
            return false;
        }
        $seed = abs(crc32($deviceId . '|' . (string)$terminal . '|' . (string)$grayGroupTag)) % 100;
        return $seed < $ratio;
    }

    protected function filterHotUpdateRows($rows)
    {
        $result = [];
        foreach ($rows as $row) {
            $meta = $this->extractMeta(isset($row['meta_json']) ? $row['meta_json'] : '');
            if (isset($meta['pipeline']) && $meta['pipeline'] === 'hot_update') {
                $row['hot_meta'] = $meta;
                $result[] = $row;
            }
        }
        return $result;
    }

    protected function buildHotUpdateTop($rows, $field, $limit = 5)
    {
        $counter = [];
        foreach ($rows as $row) {
            $meta = isset($row['hot_meta']) && is_array($row['hot_meta']) ? $row['hot_meta'] : [];
            $key = isset($meta[$field]) ? trim((string)$meta[$field]) : '';
            if ($key === '') {
                continue;
            }
            if (!isset($counter[$key])) {
                $counter[$key] = 0;
            }
            $counter[$key]++;
        }
        arsort($counter);
        $result = [];
        $i = 0;
        foreach ($counter as $key => $count) {
            $result[] = ['key' => $key, 'count' => $count];
            $i++;
            if ($i >= $limit) {
                break;
            }
        }
        return $result;
    }

    protected function compareVersion($v1, $v2)
    {
        $a = array_map('intval', explode('.', trim((string)$v1)));
        $b = array_map('intval', explode('.', trim((string)$v2)));
        $len = max(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $x = isset($a[$i]) ? $a[$i] : 0;
            $y = isset($b[$i]) ? $b[$i] : 0;
            if ($x < $y) {
                return -1;
            }
            if ($x > $y) {
                return 1;
            }
        }
        return 0;
    }

    protected function generateReleaseNo()
    {
        return 'RL' . date('YmdHis') . mt_rand(10000, 99999);
    }

    protected function buildChannelHealthMap()
    {
        $rows = PlatformNodeHealth::field('channel_key,status,id')
            ->order('id desc')
            ->limit(500)
            ->select()
            ->toArray();
        $map = [];
        foreach ($rows as $row) {
            $key = isset($row['channel_key']) ? (string)$row['channel_key'] : '';
            if ($key === '' || isset($map[$key])) {
                continue;
            }
            $map[$key] = isset($row['status']) ? (string)$row['status'] : 'unknown';
        }
        return $map;
    }

    protected function healthScore($status)
    {
        if ($status === 'ok') {
            return 3;
        }
        if ($status === 'degraded') {
            return 2;
        }
        if ($status === 'unknown') {
            return 1;
        }
        return 0;
    }

    protected function createOpsAlert($alertType, $level, $target, $detail)
    {
        $exists = PlatformOpsAlert::where('alert_type', (string)$alertType)
            ->where('target', (string)$target)
            ->where('status', 'open')
            ->find();
        if ($exists) {
            return;
        }
        PlatformOpsAlert::create([
            'alert_type' => (string)$alertType,
            'level' => (string)$level,
            'target' => (string)$target,
            'detail' => (string)$detail,
            'status' => 'open',
            'workorder_no' => 'OP' . date('YmdHis') . mt_rand(10000, 99999)
        ]);
    }

    protected function runAutoGuard($metric = 'download', $terminal = 'client')
    {
        $windowStart = time() - 3600;
        $rows = [];
        if ($metric === 'download') {
            $rows = PlatformDownloadLog::where('createtime', '>=', $windowStart)->field('status')->select()->toArray();
        } elseif ($metric === 'install') {
            $rows = PlatformInstallLog::where('createtime', '>=', $windowStart)->field('status')->select()->toArray();
        } elseif ($metric === 'repair') {
            $rows = PlatformRepairLog::where('createtime', '>=', $windowStart)->field('status')->select()->toArray();
        } elseif ($metric === 'hot_update') {
            $rows = PlatformInstallLog::where('createtime', '>=', $windowStart)->field('status,meta_json')->select()->toArray();
            $rows = $this->filterHotUpdateRows($rows);
        }
        $counter = $this->buildStatusCounter($rows);
        $failed = isset($counter['failed']) ? (int)$counter['failed'] : 0;
        $total = array_sum($counter);
        $rate = $total > 0 ? round($failed / $total * 100, 2) : 0;
        $rollbackTriggered = false;
        $rollbackInfo = [];
        if ($metric !== 'hot_update' && $total >= 10 && $rate >= 60) {
            $this->createOpsAlert('chain_failure', 'critical', $metric, 'failure_rate:' . $rate);
            $rollbackInfo = $this->rollbackLatestRelease($metric, $rate);
            $rollbackTriggered = isset($rollbackInfo['rollback']) ? (bool)$rollbackInfo['rollback'] : false;
        }
        if ($metric === 'hot_update' && $total >= 10 && $rate >= 60) {
            $rollbackInfo = $this->autoDisableHotUpdate($terminal, $rate);
            $rollbackTriggered = isset($rollbackInfo['rollback']) ? (bool)$rollbackInfo['rollback'] : false;
        }
        return [
            'metric' => $metric,
            'window_minutes' => 60,
            'total' => $total,
            'failed' => $failed,
            'failure_rate' => $rate,
            'rollback_triggered' => $rollbackTriggered,
            'rollback_info' => $rollbackInfo
        ];
    }

    protected function autoDisableHotUpdate($terminal, $rate)
    {
        $row = PlatformVersionStrategy::where('status', 'normal')->where('terminal', (string)$terminal)->find();
        if (!$row) {
            return ['rollback' => false, 'reason' => 'strategy_not_found'];
        }
        $switch = $row['switch_json'] ? json_decode($row['switch_json'], true) : [];
        if (!is_array($switch)) {
            $switch = [];
        }
        $hot = isset($switch['hot_update']) && is_array($switch['hot_update']) ? $switch['hot_update'] : [];
        $hot['enabled'] = false;
        $hot['force'] = false;
        $hot['downgrade_reason'] = 'auto_downgrade_failure_rate';
        $hot['auto_disabled_at'] = time();
        $switch['hot_update'] = $hot;
        $row->save(['switch_json' => json_encode($switch, JSON_UNESCAPED_UNICODE)]);
        PlatformRollbackTask::create([
            'task_no' => 'RB' . date('YmdHis') . mt_rand(10000, 99999),
            'trigger_type' => 'hot_update_auto_downgrade',
            'terminal' => (string)$terminal,
            'from_release_no' => isset($hot['target_version']) ? (string)$hot['target_version'] : '',
            'to_release_no' => '',
            'status' => 'done',
            'detail' => 'failure_rate:' . $rate
        ]);
        $this->createOpsAlert('hot_update_downgrade', 'critical', (string)$terminal, 'failure_rate:' . $rate);
        return ['rollback' => true, 'reason' => 'auto_downgrade', 'rate' => $rate];
    }

    protected function rollbackLatestRelease($triggerType, $rate)
    {
        $latest = PlatformConfigRelease::where('status', 'published')->order('id desc')->find();
        if (!$latest) {
            return ['rollback' => false];
        }
        $fromReleaseNo = (string)$latest['release_no'];
        $terminal = (string)$latest['terminal'];
        $previous = PlatformConfigRelease::where('status', 'published')->where('terminal', $terminal)->where('id', '<', (int)$latest['id'])->order('id desc')->find();
        if (!$previous) {
            return ['rollback' => false, 'reason' => 'no_previous_release'];
        }
        $snapshot = $previous['snapshot_json'] ? json_decode($previous['snapshot_json'], true) : [];
        if (!is_array($snapshot) || !isset($snapshot['configs']) || !is_array($snapshot['configs'])) {
            return ['rollback' => false, 'reason' => 'invalid_snapshot'];
        }
        Db::startTrans();
        try {
            foreach ($snapshot['configs'] as $cfg) {
                if (!isset($cfg['page_key']) || !isset($cfg['config']) || !is_array($cfg['config'])) {
                    continue;
                }
                PlatformPage::where('page_key', (string)$cfg['page_key'])
                    ->where('terminal', 'in', ['common', $terminal])
                    ->update([
                        'config_json' => json_encode($cfg['config'], JSON_UNESCAPED_UNICODE),
                        'version' => isset($cfg['version']) ? (int)$cfg['version'] : 1
                    ]);
            }
            $toReleaseNo = $this->generateReleaseNo();
            PlatformConfigRelease::create([
                'release_no' => $toReleaseNo,
                'terminal' => $terminal,
                'release_version' => (int)$previous['release_version'],
                'status' => 'rollback',
                'snapshot_json' => $previous['snapshot_json'],
                'operator_id' => 0,
                'remark' => 'auto_rollback:' . $triggerType . ':' . $rate
            ]);
            PlatformRollbackTask::create([
                'task_no' => 'RB' . date('YmdHis') . mt_rand(10000, 99999),
                'trigger_type' => (string)$triggerType,
                'terminal' => $terminal,
                'from_release_no' => $fromReleaseNo,
                'to_release_no' => $toReleaseNo,
                'status' => 'done',
                'detail' => 'failure_rate:' . $rate
            ]);
            Db::commit();
            $this->createOpsAlert('auto_rollback', 'critical', $terminal, 'from:' . $fromReleaseNo . ',to:' . $toReleaseNo);
            return ['rollback' => true, 'from_release_no' => $fromReleaseNo, 'to_release_no' => $toReleaseNo];
        } catch (\Throwable $e) {
            Db::rollback();
            $this->createOpsAlert('rollback_failed', 'critical', $terminal, $e->getMessage());
            return ['rollback' => false, 'reason' => 'rollback_exception'];
        }
    }

    protected function refreshUserSegment($userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return;
        }
        $since = time() - 30 * 86400;
        $activeDays = PlatformEventLog::where('user_id', $userId)->where('createtime', '>=', $since)->group('FROM_UNIXTIME(createtime,"%Y-%m-%d")')->count();
        $payAmount = (float)PlatformOrder::where('user_id', $userId)->where('pay_status', 'paid')->where('createtime', '>=', $since)->sum('amount');
        $lastEventTime = (int)PlatformEventLog::where('user_id', $userId)->max('createtime');
        $segment = 'normal';
        if ($payAmount >= 500) {
            $segment = 'high_value';
        } elseif ($activeDays >= 10) {
            $segment = 'active';
        } elseif ($activeDays <= 1) {
            $segment = 'sleeping';
        }
        $score = round($activeDays * 2 + $payAmount * 0.1, 2);
        PlatformUserSegment::update([
            'user_id' => $userId,
            'segment_tag' => $segment,
            'score' => $score,
            'active_days_30' => (int)$activeDays,
            'pay_amount_30' => $payAmount,
            'last_event_time' => $lastEventTime,
            'snapshot_date' => date('Y-m-d')
        ], ['user_id' => $userId, 'snapshot_date' => date('Y-m-d')], true);
    }

    protected function buildExperimentPayload($terminal, $deviceId)
    {
        $list = PlatformAbExperiment::where('terminal', (string)$terminal)->where('status', 'running')->select()->toArray();
        $result = [];
        foreach ($list as $row) {
            $variant = $this->pickExperimentVariant($row, $deviceId);
            if ($variant === '') {
                continue;
            }
            $result[] = [
                'experiment_key' => (string)$row['experiment_key'],
                'variant' => $variant,
                'metric_event' => (string)$row['metric_event']
            ];
        }
        return $result;
    }

    protected function pickExperimentVariant($row, $deviceId)
    {
        $variants = isset($row['variants_json']) && $row['variants_json'] ? json_decode($row['variants_json'], true) : [];
        if (!is_array($variants) || !$variants) {
            return '';
        }
        $pool = [];
        foreach ($variants as $item) {
            if (!is_array($item) || !isset($item['name'])) {
                continue;
            }
            $weight = isset($item['weight']) ? (int)$item['weight'] : 0;
            if ($weight <= 0) {
                continue;
            }
            for ($i = 0; $i < $weight; $i++) {
                $pool[] = (string)$item['name'];
            }
        }
        if (!$pool) {
            return '';
        }
        $seedKey = (string)$deviceId . '|' . (isset($row['experiment_key']) ? (string)$row['experiment_key'] : '');
        $idx = abs(crc32($seedKey)) % count($pool);
        return $pool[$idx];
    }

    protected function assertRateLimit($action, $limit, $windowSeconds)
    {
        $ip = (string)$this->request->ip();
        $deviceId = trim((string)$this->request->request('device_id', ''));
        if ($deviceId === '') {
            $deviceId = trim((string)$this->request->post('device_id', ''));
        }
        $uid = (int)$this->auth->id;
        $finger = $uid > 0 ? 'u:' . $uid : ($deviceId !== '' ? 'd:' . $deviceId : 'ip:' . $ip);
        $key = 'platform:rl:' . $action . ':' . md5($finger);
        $count = (int)cache($key);
        if ($count >= (int)$limit) {
            $this->error(__('Server is busy'));
        }
        cache($key, $count + 1, (int)$windowSeconds);
    }

    protected function detectAnomalyBurst($type, $target, $threshold, $windowSeconds)
    {
        $target = trim((string)$target);
        if ($target === '') {
            return;
        }
        $key = 'platform:anomaly:' . $type . ':' . md5($target);
        $count = (int)cache($key) + 1;
        cache($key, $count, (int)$windowSeconds);
        if ($count >= (int)$threshold) {
            $this->createOpsAlert('anomaly_burst', 'critical', $target, $type . ':count=' . $count);
        }
    }

    protected function buildNotifySign($payload, $secret)
    {
        ksort($payload);
        $pairs = [];
        foreach ($payload as $key => $value) {
            $pairs[] = $key . '=' . (string)$value;
        }
        return hash_hmac('sha256', implode('&', $pairs), (string)$secret);
    }
}
