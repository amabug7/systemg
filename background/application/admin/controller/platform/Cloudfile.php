<?php

namespace app\admin\controller\platform;

use app\common\controller\Backend;
use app\common\library\Tianyiyun;
use app\common\model\PlatformCloudAccount;
use app\common\model\PlatformGame;
use Exception;
use think\Db;

class Cloudfile extends Backend
{
    protected $model = null;
    protected $excludeFields = ['name', 'file_size'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('app\\common\\model\\PlatformCloudFile');
        $this->view->assign('statusList', $this->model->getStatusList());
        $this->view->assign('accountRuleList', $this->model->getAccountRuleList());
        $gameList = PlatformGame::where('status', 'normal')->order('weigh desc,id desc')->column('title', 'id');
        $this->view->assign('gameList', $gameList);
        $this->assignconfig('gameList', $gameList);
        $this->ensureDirectResourceSubMenu();

    }

    protected function ensureDirectResourceSubMenu()
    {
        try {
            $parent = Db::name('auth_rule')->where('name', 'platform/cloudfile')->find();
            if (!$parent || empty($parent['id'])) {
                return;
            }
            $parallelPid = (int)$parent['pid'] > 0 ? (int)$parent['pid'] : (int)$parent['id'];
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
                $cloudIndex = Db::name('auth_rule')->where('name', 'platform/cloudfile/index')->value('id');
                $cloudMenuId = (int)$parent['id'];
                $cloudIndex = (int)$cloudIndex;
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
            \think\Log::record('ensureDirectResourceSubMenu failed: ' . $e->getMessage(), 'debug');
        }
    }



    public function add()
    {
        if (false === $this->request->isPost()) {
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        $result = false;
        Db::startTrans();
        try {
            $params = $this->buildFileSaveData($params, null, true);
            $result = $this->model->allowField(true)->save($params);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success('云盘文件已读取并保存');
    }

    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        $result = false;
        Db::startTrans();
        try {
            $params = $this->buildFileSaveData($params, $row, false);
            $result = $row->allowField(true)->save($params);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success('云盘文件信息已更新');
    }

    public function accountoptions()
    {
        $rows = PlatformCloudAccount::where('status', 'in', ['normal', 'limited'])
            ->field('id,username,status,weigh')
            ->order('weigh desc,id asc')
            ->select();
        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int)$row['id'],
                'username' => (string)$row['username'],
                'status' => (string)$row['status'],
            ];
        }
        $this->success('', null, $list);
    }

    public function fetchinfo()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        $params['account_rule'] = $this->normalizeAccountRule($params['account_rule'] ?? 'ordered');
        $params['account_ids'] = $this->normalizeAccountIds($params['account_ids'] ?? '');
        $meta = $this->fetchShareFileMeta($params);
        $this->success('读取成功', null, $meta);


    }

    public function previewdownload()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $fileId = (int)$this->request->post('file_id', 0);
        $accountId = (int)$this->request->post('account_id', 0);
        if ($fileId <= 0) {
            $this->error(__('Invalid parameters'));
        }

        $file = $this->model->where('id', $fileId)->find();
        if (!$file) {
            $this->error(__('No Results were found'));
        }
        if ((string)$file['status'] !== 'normal') {
            $this->error('该文件未启用');
        }

        $accounts = $this->pickAccounts($file, $accountId > 0 ? $accountId : null);
        if (!$accounts) {
            $this->error('无可用天翼云账号');
        }

        $shareCode = trim((string)$file['share_code']);
        $accessCode = trim((string)$file['access_code']);
        if ($shareCode === '' || $accessCode === '') {
            $this->error('分享码或访问码为空');
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
                    $errors[] = '账号[' . $account['username'] . ']Token无效';
                    PlatformCloudAccount::where('id', (int)$account['id'])->update([
                        'last_check_time' => time(),
                        'last_check_status' => 'invalid',
                    ]);
                    continue;
                }
                $url = $client->getFileDownloadUrl($shareCode, $accessCode, $token);
                PlatformCloudAccount::where('id', (int)$account['id'])->update([
                    'last_check_time' => time(),
                    'last_check_status' => 'valid',
                ]);
                $successData = [
                    'download_url' => $url,
                    'url' => $url,
                    'account_id' => (int)$account['id'],
                    'username' => (string)$account['username'],
                ];

                break;
            } catch (\Throwable $e) {
                $message = trim((string)$e->getMessage());
                if ($message === '') {
                    $message = get_class($e);
                }
                $errors[] = '账号[' . $account['username'] . ']失败: ' . $message;
                PlatformCloudAccount::where('id', (int)$account['id'])->update([
                    'last_check_time' => time(),
                    'last_check_status' => 'invalid',
                ]);
            }
        }

        if ($successData !== null) {
            $this->success('获取成功', null, $successData);
        }


        $this->error('全部账号尝试失败: ' . implode('；', $errors));

    }

    protected function buildFileSaveData(array $params, $row = null, $forceRefresh = false)
    {
        $shareCode = trim((string)($params['share_code'] ?? ($row ? $row['share_code'] : '')));
        $accessCode = trim((string)($params['access_code'] ?? ($row ? $row['access_code'] : '')));
        if ($shareCode === '' || $accessCode === '') {
            throw new Exception('请填写分享码和访问码');
        }

        $params['share_code'] = $shareCode;
        $params['access_code'] = $accessCode;
        $params['account_rule'] = $this->normalizeAccountRule($params['account_rule'] ?? ($row ? $row['account_rule'] : 'ordered'));
        $params['account_ids'] = $this->normalizeAccountIds($params['account_ids'] ?? ($row ? $row['account_ids'] : ''));

        $storedShareCode = $row ? trim((string)$row['share_code']) : '';
        $storedAccessCode = $row ? trim((string)$row['access_code']) : '';
        $storedRule = $row ? $this->normalizeAccountRule($row['account_rule']) : 'ordered';
        $storedIds = $row ? $this->normalizeAccountIds($row['account_ids']) : '';
        $storedName = $row ? trim((string)$row['name']) : '';
        $storedSize = $row ? (int)$row['file_size'] : 0;
        $needRefresh = $forceRefresh || !$row || $shareCode !== $storedShareCode || $accessCode !== $storedAccessCode || $params['account_rule'] !== $storedRule || $params['account_ids'] !== $storedIds || $storedName === '' || $storedSize < 0;
        if (!$needRefresh) {
            return $params;
        }

        $meta = $this->fetchShareFileMeta($params);
        $params['name'] = (string)$meta['name'];
        $params['file_size'] = (int)$meta['file_size'];
        return $params;
    }

    protected function fetchShareFileMeta(array $fileData)
    {
        $shareCode = trim((string)($fileData['share_code'] ?? ''));
        $accessCode = trim((string)($fileData['access_code'] ?? ''));
        if ($shareCode === '' || $accessCode === '') {
            throw new Exception('请填写分享码和访问码');
        }

        $accounts = $this->pickAccounts($fileData);
        if (!$accounts) {
            throw new Exception('无可用天翼云账号');
        }

        $errors = [];
        foreach ($accounts as $account) {
            $accountId = (int)$account['id'];
            $username = (string)$account['username'];
            $token = trim((string)$account['access_token']);
            if ($token === '') {
                $errors[] = '账号[' . $username . ']未配置AccessToken';
                continue;
            }
            try {
                $client = new Tianyiyun();
                if (!$client->checkUserInfo($token)) {
                    $errors[] = '账号[' . $username . ']Token无效';
                    PlatformCloudAccount::where('id', $accountId)->update([
                        'last_check_time' => time(),
                        'last_check_status' => 'invalid',
                    ]);
                    continue;
                }
                $meta = $client->getShareFileMeta($shareCode, $accessCode, $token);
                PlatformCloudAccount::where('id', $accountId)->update([
                    'last_check_time' => time(),
                    'last_check_status' => 'valid',
                ]);
                return [
                    'name' => (string)$meta['name'],
                    'file_size' => (int)$meta['file_size'],
                    'account_id' => $accountId,
                    'account_username' => $username,
                ];
            } catch (\Throwable $e) {
                $errors[] = '账号[' . $username . ']失败: ' . $e->getMessage();
            }
        }

        $errors = array_values(array_unique(array_filter($errors)));
        throw new Exception($errors ? '读取云盘文件信息失败: ' . implode('；', $errors) : '读取云盘文件信息失败');
    }

    protected function pickAccounts($file, $forceAccountId = null)
    {
        if ($forceAccountId) {
            $row = PlatformCloudAccount::where('id', (int)$forceAccountId)
                ->where('status', 'in', ['normal', 'limited'])
                ->find();
            $row = $this->normalizeAccountRow($row);
            return $row ? [$row] : [];
        }

        $ids = array_values(array_filter(array_map('intval', explode(',', (string)$file['account_ids']))));
        $query = PlatformCloudAccount::where('status', 'in', ['normal', 'limited']);
        if ($ids) {
            $query->where('id', 'in', $ids);
        }
        $rows = $this->normalizeAccountRows($query->order('weigh desc,id asc')->select());
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

    protected function normalizeAccountRows($rows)
    {
        if (is_object($rows) && method_exists($rows, 'toArray')) {
            $rows = $rows->toArray();
        }
        return is_array($rows) ? $rows : [];
    }

    protected function normalizeAccountRow($row)
    {
        if (is_object($row) && method_exists($row, 'toArray')) {
            $row = $row->toArray();
        }
        return is_array($row) ? $row : [];
    }


    protected function normalizeAccountIds($value)
    {
        $ids = preg_split('/[^\d]+/', (string)$value);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        return $ids ? implode(',', $ids) : '';
    }

    protected function normalizeAccountRule($value)
    {
        return (string)$value === 'random' ? 'random' : 'ordered';
    }
}

