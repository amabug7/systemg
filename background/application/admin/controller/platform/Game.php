<?php

namespace app\admin\controller\platform;

use app\common\controller\Backend;
use think\Db;

class Game extends Backend
{
    protected $model = null;
    protected $noNeedRight = ['materialoptions', 'cloudfileoptions', 'directresourceoptions'];
    // 新增：动态列表字段（这些字段的数据以 JSON 存储到 hidden input，再写入对应子表或保留为JSON）
    protected $dynamicFields = ['carousel_json', 'resource_links_json', 'repair_profile_json'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('app\common\model\PlatformGame');
        $this->view->assign("statusList", $this->model->getStatusList());
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
            \think\Log::record('ensureDirectResourceParallelMenu@Game failed: ' . $e->getMessage(), 'debug');
        }
    }


    public function materialOptions()
    {
        $this->syncAttachmentToMaterialImages();

        $page = max(1, (int)$this->request->request('page', 1));
        $listRows = min(100, max(1, (int)$this->request->request('list_rows', 12)));
        $keyword = trim((string)$this->request->request('keyword', ''));
        $materialType = trim((string)$this->request->request('material_type', ''));

        $query = Db::name('platform_material')->where('status', 'normal');
        if ($materialType !== '') {
            $query->where('material_type', $materialType);
        }
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->where('name', 'like', "%{$keyword}%")
                    ->whereOr('url', 'like', "%{$keyword}%")
                    ->whereOr('thumb', 'like', "%{$keyword}%")
                    ->whereOr('tags', 'like', "%{$keyword}%")
                    ->whereOr('mime', 'like', "%{$keyword}%");
            });
        }

        $total = (clone $query)->count();
        $list = $query->field('id,name,material_type,url,thumb,size,mime,tags,createtime')
            ->order('id desc')
            ->page($page, $listRows)
            ->select();

        $this->success('', null, [
            'total' => $total,
            'page' => $page,
            'list_rows' => $listRows,
            'list' => collection($list)->toArray(),
        ]);
    }

    protected function syncAttachmentToMaterialImages()
    {
        try {
            $rows = Db::name('attachment')
                ->field('id,filename,url,mimetype,filesize,uploadtime,createtime')
                ->where('url', '<>', '')
                ->where('mimetype', 'like', 'image/%')
                ->order('id desc')
                ->limit(3000)
                ->select();
            if (!$rows) {
                return;
            }

            $rows = collection($rows)->toArray();
            $urls = array_values(array_unique(array_filter(array_column($rows, 'url'))));
            if (!$urls) {
                return;
            }

            $existing = Db::name('platform_material')->where('url', 'in', $urls)->column('url');
            $existingMap = array_flip($existing ?: []);
            $now = time();
            $insertRows = [];
            foreach ($rows as $item) {
                $url = trim((string)($item['url'] ?? ''));
                if ($url === '' || isset($existingMap[$url])) {
                    continue;
                }
                $filename = trim((string)($item['filename'] ?? ''));
                $path = parse_url($url, PHP_URL_PATH);
                $fallback = $path ? basename($path) : $url;
                $name = $filename !== '' ? $filename : $fallback;
                $mime = trim((string)($item['mimetype'] ?? 'image/*'));
                $created = (int)($item['uploadtime'] ?? 0);
                if ($created <= 0) {
                    $created = (int)($item['createtime'] ?? 0);
                }
                if ($created <= 0) {
                    $created = $now;
                }
                $insertRows[] = [
                    'name' => mb_substr($name, 0, 120),
                    'material_type' => 'image',
                    'url' => $url,
                    'thumb' => $url,
                    'size' => max(0, (int)($item['filesize'] ?? 0)),
                    'mime' => mb_substr($mime, 0, 120),
                    'tags' => '自动同步,附件库,图片',
                    'status' => 'normal',
                    'weigh' => 0,
                    'createtime' => $created,
                    'updatetime' => $now,
                ];
                $existingMap[$url] = 1;
            }

            if ($insertRows) {
                Db::name('platform_material')->insertAll($insertRows);
            }
        } catch (\Throwable $e) {
            \think\Log::record('syncAttachmentToMaterialImages failed: ' . $e->getMessage(), 'debug');
        }
    }

    public function cloudFileOptions()
    {
        $page = max(1, (int)$this->request->request('page', 1));
        $listRows = min(100, max(1, (int)$this->request->request('list_rows', 12)));
        $keyword = trim((string)$this->request->request('keyword', ''));
        $gameId = (int)$this->request->request('game_id', 0);

        $query = Db::name('platform_cloud_file')->where('status', 'normal');
        if ($gameId > 0) {
            $query->where('game_id', $gameId);
        }
        if ($keyword !== '') {
            $query->where('name|remark|share_code', 'like', "%{$keyword}%");
        }

        $total = (clone $query)->count();
        $list = $query->field('id,name,remark,file_size,game_id,share_code,access_code,account_rule,account_ids,createtime')
            ->order('id desc')
            ->page($page, $listRows)
            ->select();

        $this->success('', null, [
            'total' => $total,
            'page' => $page,
            'list_rows' => $listRows,
            'list' => collection($list)->toArray(),
        ]);
    }

    public function directResourceOptions()
    {
        $page = max(1, (int)$this->request->request('page', 1));
        $listRows = min(100, max(1, (int)$this->request->request('list_rows', 12)));
        $keyword = trim((string)$this->request->request('keyword', ''));
        $gameId = (int)$this->request->request('game_id', 0);

        $query = Db::name('platform_game_resource')
            ->where('status', 'normal')
            ->where(function ($sub) {
                $sub->where('channel_key', 'direct')
                    ->whereOr('channel_key', 'url')
                    ->whereOr('file_path', 'like', 'http%');
            });

        if ($gameId > 0) {
            $query->where('game_id', $gameId);
        }
        if ($keyword !== '') {
            $query->where('name|version|file_path', 'like', "%{$keyword}%");
        }

        $total = (clone $query)->count();
        $list = $query->field('id,game_id,resource_type,name,version,file_path,file_size,channel_key,priority,weigh,createtime')
            ->order('priority desc,weigh desc,id desc')
            ->page($page, $listRows)
            ->select();

        $this->success('', null, [
            'total' => $total,
            'page' => $page,
            'list_rows' => $listRows,
            'list' => collection($list)->toArray(),
        ]);
    }


    /**
     * 编辑页面 — 加载已有数据时，解析 JSON 字段传递给模板
     */
    public function edit($ids = null)
    {
        if ($this->request->isPost()) {
            return $this->saveWithRelations($ids);
        }

        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        // 将轮播/资源/修复的 JSON 数据传给模板（ThinkPHP 集合写法）
        $carouselRows = Db::name('platform_game_carousel')
            ->where('game_id', $ids)
            ->order('sort_weight', 'asc')
            ->select();
        $carouselData = json_encode(collection($carouselRows)->toArray(), JSON_UNESCAPED_UNICODE);

        $resourceRows = Db::name('platform_game_resource')
            ->where('game_id', $ids)
            ->order('weigh', 'desc')
            ->select();
        $resourceData = json_encode(collection($resourceRows)->toArray(), JSON_UNESCAPED_UNICODE);

        $repairRows = Db::name('platform_game_repair')
            ->where('game_id', $ids)
            ->order('sort_weight', 'asc')
            ->select();
        $repairData = json_encode(collection($repairRows)->toArray(), JSON_UNESCAPED_UNICODE);

        $this->view->assign('carouselData', $carouselData);
        $this->view->assign('resourceData', $resourceData);
        $this->view->assign('repairData', $repairData);

        $this->view->assign("row", $row);
        // 生成游戏类型选项
        $genreOptions = '<option value="">请选择</option>';
        $genres = ['RPG' => 'RPG 角色扮演', 'SLG' => 'SLG 策略模拟', 'ACT' => 'ACT 动作游戏', 'FPS' => 'FPS 射击游戏', 'RTS' => 'RTS 即时战略', 'AVG' => 'AVG 冒险解谜', 'SIM' => 'SIM 模拟经营', 'RAC' => 'RAC 赛车竞速', 'FTG' => 'FTG 格斗游戏', 'PUZ' => 'PUZ 益智休闲'];
        $currentGenre = $row['genre'] ?? '';
        foreach ($genres as $value => $label) {
            $selected = $value === $currentGenre ? 'selected' : '';
            $genreOptions .= "<option value=\"{$value}\" {$selected}>{$label}</option>";
        }
        $this->view->assign('genreOptions', $genreOptions);

        return $this->view->fetch();
    }


    /**
     * 添加/编辑提交 — 保存主表 + 同步子表数据
     */
    public function add()
    {
        if ($this->request->isPost()) {
            return $this->saveWithRelations();
        }
        return parent::add();
    }



    /**
     * 核心保存逻辑：主表字段 + 动态列表 → 子表
     */
    private function saveWithRelations($gameId = null)
    {
        $params = $this->request->post('row/a');

        if (!$params) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $allowFields = Db::name('platform_game')->getTableFields();
        $params = array_filter(array_intersect_key(
            $params,
            array_flip(is_array($allowFields) ? $allowFields : [])
        ));


        // 基础验证
        if (empty($params['title'])) {
            $this->error('游戏名称不能为空');
        }
        if (empty($params['slug'])) {
            $this->error('标识符不能为空');
        }
        if (empty($params['cover'])) {
            $this->error('封面图不能为空');
        }

        // 提取动态列表的 JSON 数据
        $carouselJson   = $this->request->post('row.carousel_json', '');
        $resourceJson   = $this->request->post('row.resource_links_json', '');
        $repairJson     = $this->request->post('row.repair_profile_json', '');

        // 移除不存在的字段（避免写入失败）
        foreach ($this->dynamicFields as $f) {
            unset($params[$f]);
        }

        Db::startTrans();
        try {
            if ($gameId) {
                // 更新
                $result = $this->model->where('id', $gameId)->update($params);
                if ($result === false) throw new \Exception('更新游戏信息失败');
            } else {
                // 新增
                $params['createtime'] = time();
                $params['updatetime']  = time();
                $result = $this->model->insertGetId($params);
                if (!$result) throw new \Exception('创建游戏失败');
                $gameId = $result;
            }

            // ---- 同步轮播图子表 ----
            $this->syncSubTable('platform_game_carousel', $gameId, $carouselJson, [
                'image_url' => 'url',
                'title' => 'title',
                'description' => '',
                'link_url' => 'link_url',
                'sort_weight' => 0,
                'status' => 'normal',
            ], ['image_url'], true);

            // ---- 同步下载资源子表（fa_platform_game_resource）----
            $this->syncSubTable('platform_game_resource', $gameId, $resourceJson, [
                'resource_type' => 'type',
                'name' => 'name',
                'version' => 'version',
                'file_path' => 'url',
                'file_hash' => '',
                'file_size' => 'file_size_bytes',
                'channel_key' => 'channel_key',
                'priority' => 0,
                'weigh' => 0,
                'status' => 'normal',
                'extra_json' => '',
            ], ['name', 'file_path']);

            // ---- 同步修复方案子表 ----
            $this->syncSubTable('platform_game_repair', $gameId, $repairJson, [
                'repair_type' => 'common',
                'name' => 'name',
                'description' => 'description',
                'script_url' => 'script_url',
                'risk_level' => 'risk_level',
                'auto_run' => 0,
                'sort_weight' => 0,
                'status' => 'normal',
            ], ['name', 'script_url']);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        $this->success($gameId ? '更新成功' : '添加成功', '');
    }

    /**
     * 通用子表同步方法：
     * 先删除旧记录，再批量插入新数据
     *
     * @param string $tableName 子表名（不含前缀）
     * @param int    $gameId    关联的游戏ID
     * @param string $jsonStr   前端传来的JSON字符串
     * @param array  $fieldMap  [数据库字段名 => 前端key]，前端没有则用默认值 ''
     * @param array  $requiredFields 必填字段（任一有值即可）
     * @param bool   $deleteOld 是否先删除旧记录
     */
    private function syncSubTable($tableName, $gameId, $jsonStr, $fieldMap, $requiredFields = [], $deleteOld = true)
    {
        if ($deleteOld) {

            Db::name($tableName)->where('game_id', $gameId)->delete();
        }

        $items = json_decode($jsonStr, true);
        if (!is_array($items)) $items = [];

        $now = time();
        $insertRows = [];

        foreach ($items as $item) {
            // 检查必填字段
            $hasRequired = empty($requiredFields);
            foreach ($requiredFields as $rf) {
                $fk = $fieldMap[$rf] ?? $rf;
                if (!empty($item[$fk])) { $hasRequired = true; break; }
            }
            if (!$hasRequired) continue;

            $row = [
                'game_id' => $gameId,
                'createtime' => $now,
                'updatetime' => $now,
            ];

            foreach ($fieldMap as $dbField => $frontKey) {
                $val = isset($item[$frontKey]) ? $item[$frontKey] : ($frontKey === '' ? '' : $frontKey);
                if (is_array($val) || is_object($val)) {
                    $val = json_encode($val, JSON_UNESCAPED_UNICODE);
                }
                $row[$dbField] = $val;
            }

            $insertRows[] = $row;
        }

        if (!empty($insertRows)) {
            Db::name($tableName)->insertAll($insertRows);
        }
    }
}
