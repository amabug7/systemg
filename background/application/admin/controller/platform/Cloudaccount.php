<?php

namespace app\admin\controller\platform;

use app\common\controller\Backend;
use app\common\library\Tianyiyun;
use Exception;
use think\Db;

class Cloudaccount extends Backend
{
    protected $model = null;
    protected $excludeFields = ['access_token', 'token_refresh_time', 'last_check_time', 'last_check_status'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('app\\common\\model\\PlatformCloudAccount');
        $this->view->assign('statusList', $this->model->getStatusList());
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
            $params = $this->buildAccountSaveData($params, null, true);
            $result = $this->model->allowField(true)->save($params);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success('账号验证成功并已保存');
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
            $params = $this->buildAccountSaveData($params, $row, false);
            $result = $row->allowField(true)->save($params);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success('账号信息已更新');
    }

    public function checktoken()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $id = (int)$this->request->post('id', 0);
        if ($id <= 0) {
            $this->error(__('Invalid parameters'));
        }
        $row = $this->model->where('id', $id)->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $token = trim((string)$row['access_token']);
        if ($token === '') {
            $this->error('该账号未保存AccessToken，请先刷新');
        }

        $ok = false;
        $msg = '';
        try {
            $client = new Tianyiyun();
            $ok = $client->checkUserInfo($token);
            $msg = $ok ? 'Token有效' : 'Token无效';
        } catch (\Throwable $e) {
            $ok = false;
            $msg = '检测失败: ' . $e->getMessage();
        }

        $row->save([
            'last_check_time' => time(),
            'last_check_status' => $ok ? 'valid' : 'invalid',
        ]);
        $this->success($msg, null, ['valid' => $ok]);
    }

    public function refreshtoken()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $id = (int)$this->request->post('id', 0);
        if ($id <= 0) {
            $this->error(__('Invalid parameters'));
        }
        $row = $this->model->where('id', $id)->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        try {
            $saveData = $this->buildAccountSaveData([], $row, true);
            $row->save($saveData);
            $this->success('Token刷新成功', null, ['access_token' => $saveData['access_token']]);
        } catch (\Throwable $e) {
            $row->save([
                'last_check_status' => 'invalid',
                'last_check_time' => time(),
            ]);
            $this->error('Token刷新失败: ' . $e->getMessage());
        }
    }

    protected function buildAccountSaveData(array $params, $row = null, $forceRefresh = false)
    {
        $username = trim((string)($params['username'] ?? ($row ? $row['username'] : '')));
        $password = trim((string)($params['login_password'] ?? ($row ? $row['login_password'] : '')));
        if ($username === '' || $password === '') {
            throw new Exception('请填写手机号和登录密码');
        }

        $params['username'] = $username;
        $params['login_password'] = $password;

        $storedToken = $row ? trim((string)$row['access_token']) : '';
        $storedUsername = $row ? trim((string)$row['username']) : '';
        $storedPassword = $row ? trim((string)$row['login_password']) : '';
        $credentialsChanged = !$row || $username !== $storedUsername || $password !== $storedPassword;
        if (!$forceRefresh && !$credentialsChanged && $storedToken !== '') {
            return $params;
        }

        $client = new Tianyiyun();
        $token = $client->loginH5($username, $password);
        if (!$client->checkUserInfo($token)) {
            throw new Exception('天翼云账号校验失败，请检查手机号或密码');
        }

        $params['access_token'] = $token;
        $params['token_refresh_time'] = time();
        $params['last_check_status'] = 'valid';
        $params['last_check_time'] = time();
        return $params;
    }
}

