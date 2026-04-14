<?php

namespace app\common\library;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\HandlerStack;

class Tianyiyun
{
    protected $client = null;
    protected $cookies = null;
    protected $handler = null;

    public function __construct()
    {
        $this->handler = HandlerStack::create();
        $this->handler->remove('http_errors');
    }

    protected function createClient(array $options = [])
    {
        return new Client(array_merge([
            'handler' => $this->handler,
            'verify' => false,
        ], $options));
    }

    public function loginH5($username, $password)
    {
        $username = trim((string)$username);
        $password = trim((string)$password);
        if ($username === '' || $password === '') {
            throw new Exception('账号或密码不能为空');
        }

        $this->cookies = new CookieJar();
        $this->client = $this->createClient([
            'cookies' => $this->cookies,
            'allow_redirects' => [
                'max' => 0,
                'protocols' => ['http', 'https'],
                'strict' => false,
                'referer' => false,
                'track_redirects' => false,
            ]
        ]);

        $response = $this->client->get('https://api.cloud.189.cn/open/oauth2/ssoH5.action', [
            'headers' => ['Referer' => 'https://h5.cloud.189.cn/'],
            'cookies' => $this->cookies,
        ]);

        $headers = [];
        while ($response->getStatusCode() == 302) {
            $headers = $response->getHeaders();
            $location = isset($headers['Location'][0]) ? $headers['Location'][0] : '';
            if ($location === '') {
                throw new Exception('登录跳转失败');
            }
            $response = $this->client->get($location, [
                'headers' => ['Referer' => 'https://h5.cloud.189.cn/'],
                'cookies' => $this->cookies,
            ]);
        }

        if ($response->getStatusCode() != 200 || empty($headers['Location'][0])) {
            throw new Exception('登录初始化失败');
        }

        $url = $headers['Location'][0];
        $params = $this->parseUrlParams($url);
        $params['protocol'] = 'https';
        $params['showTip'] = 'true';

        $this->client = $this->createClient([
            'cookies' => $this->cookies,
        ]);

        $this->client->get('https://open.e.189.cn/api/logbox/separate/wap/login.html', [
            'headers' => ['Referer' => $url],
            'query' => $params,
            'cookies' => $this->cookies,
        ]);

        $response = $this->client->get('https://open.e.189.cn/api/logbox/oauth2/wap/appConf.do', [
            'headers' => [
                'content-type' => 'application/x-www-form-urlencoded',
                'Referer' => 'https://open.e.189.cn/api/logbox/separate/wap/login.html?' . http_build_query($params),
            ],
            'query' => $params,
            'cookies' => $this->cookies,
        ]);
        $appConf = json_decode((string)$response->getBody(), true);
        if (!is_array($appConf) || !isset($appConf['data'])) {
            throw new Exception('获取app配置失败');
        }

        $response = $this->client->post('https://open.e.189.cn/api/logbox/config/encryptConf.do', [
            'headers' => [
                'content-type' => 'application/x-www-form-urlencoded',
                'Referer' => 'https://open.e.189.cn/api/logbox/separate/wap/login.html?' . http_build_query($params),
            ],
            'form_params' => ['appId' => $params['appId']],
            'cookies' => $this->cookies,
        ]);
        $encryptConf = json_decode((string)$response->getBody(), true);
        if (!is_array($encryptConf) || empty($encryptConf['data']['pubKey']) || empty($encryptConf['data']['pre'])) {
            throw new Exception('获取加密配置失败');
        }

        $jRsaKey = "-----BEGIN PUBLIC KEY-----\n" . $encryptConf['data']['pubKey'] . "\n-----END PUBLIC KEY-----";
        $rsaUsername = $encryptConf['data']['pre'] . $this->rsaEncrypt($jRsaKey, $username);
        $rsaPassword = $encryptConf['data']['pre'] . $this->rsaEncrypt($jRsaKey, $password);

        $captchaResponse = $this->client->post('https://open.e.189.cn/api/logbox/oauth2/needcaptcha.do', [
            'headers' => [
                'Accept' => 'application/x-www-form-urlencoded',
                'Referer' => 'https://open.e.189.cn/api/logbox/separate/wap/login.html?' . http_build_query($params),
                'Reqid' => (string)$appConf['data']['reqId'],
            ],
            'form_params' => [
                'appKey' => (string)$params['appId'],
                'userName' => $rsaUsername,
            ]
        ]);
        $needCaptcha = trim((string)$captchaResponse->getBody());
        if ($needCaptcha !== '0') {
            throw new Exception('当前账号登录需要验证码，请更换账号');
        }

        $loginResponse = $this->client->get('https://open.e.189.cn/api/logbox/oauth2/loginSubmit.do', [
            'headers' => [
                'Referer' => 'https://open.e.189.cn/api/logbox/separate/wap/login.html?' . http_build_query($params),
            ],
            'query' => [
                'apptype' => $appConf['data']['infix'],
                'appKey' => $appConf['data']['appKey'],
                'accountType' => $appConf['data']['accountType'],
                'dynamicCheck' => 'false',
                'userName' => $rsaUsername,
                'epd' => $rsaPassword,
                'validateCode' => '',
                'captchaToken' => '',
                'returnUrl' => $appConf['data']['returnUrl'],
                'isConfigurable' => 'true',
                'isOauth2' => 'true',
                'state' => '',
                'paramId' => $appConf['data']['paramId'],
                'lt' => $appConf['data']['lt'],
                'REQID' => $appConf['data']['reqId'],
                'callbackMsg' => 'callbackMsg',
            ],
            'cookies' => $this->cookies,
        ]);

        $loginInfo = $this->extractFromJsonp((string)$loginResponse->getBody());
        if (!is_array($loginInfo) || !isset($loginInfo['result'])) {
            throw new Exception('登录失败');
        }
        if ((int)$loginInfo['result'] !== 0) {
            throw new Exception('登录失败: ' . (string)($loginInfo['msg'] ?? '未知错误'));
        }

        $this->client = $this->createClient([
            'cookies' => $this->cookies,
            'allow_redirects' => [
                'max' => 0,
                'protocols' => ['http', 'https'],
                'strict' => false,
                'referer' => false,
                'track_redirects' => false,
            ]
        ]);

        $response = $this->client->get((string)$loginInfo['toUrl'], [
            'headers' => ['Referer' => 'https://open.e.189.cn/'],
            'cookies' => $this->cookies,
        ]);

        $headers = [];
        while ($response->getStatusCode() == 302) {
            $headers = $response->getHeaders();
            $location = isset($headers['Location'][0]) ? $headers['Location'][0] : '';
            if ($location === '') {
                throw new Exception('登录跳转失败');
            }
            $response = $this->client->get($location, [
                'headers' => ['Referer' => 'https://open.e.189.cn/'],
                'cookies' => $this->cookies,
            ]);
        }

        if ($response->getStatusCode() != 200 || empty($headers['Location'][0])) {
            throw new Exception('获取AccessToken失败');
        }

        $userinfo = $this->parseUrlParams($headers['Location'][0]);
        $accessToken = isset($userinfo['accessToken']) ? trim((string)$userinfo['accessToken']) : '';
        if ($accessToken === '') {
            throw new Exception('AccessToken为空');
        }
        return $accessToken;
    }

    public function checkUserInfo($accessToken)
    {
        $accessToken = trim((string)$accessToken);
        if ($accessToken === '') {
            return false;
        }

        $this->cookies = new CookieJar();
        $this->client = $this->createClient([
            'cookies' => $this->cookies,
        ]);

        $timestamp = time() * 1000 + 999;
        $response = $this->client->get('https://api.cloud.189.cn/open/user/getUserInfo.action', [
            'headers' => [
                'Accept' => 'application/json;charset=UTF-8',
                'Referer' => 'https://h5.cloud.189.cn/',
                'Accesstoken' => $accessToken,
                'Timestamp' => $timestamp,
                'Sign-Type' => 1,
                'Signature' => md5('AccessToken=' . $accessToken . '&Timestamp=' . $timestamp),
            ],
            'cookies' => $this->cookies,
        ]);

        if ((int)$response->getStatusCode() !== 200) {
            return false;
        }
        $json = json_decode((string)$response->getBody(), true);
        if (!is_array($json)) {
            return false;
        }
        if (isset($json['res_code']) && (string)$json['res_code'] !== '0') {
            return false;
        }
        if (isset($json['success']) && !$json['success']) {
            return false;
        }
        return true;
    }

    public function getShareFileMeta($shareCode, $accessCode, $accessToken)
    {
        $shareCode = trim((string)$shareCode);
        $accessCode = trim((string)$accessCode);
        $accessToken = trim((string)$accessToken);
        if ($shareCode === '' || $accessCode === '' || $accessToken === '') {
            throw new Exception('参数不完整');
        }

        $this->cookies = new CookieJar();
        $this->client = $this->createClient([
            'cookies' => $this->cookies,
        ]);

        $timestamp = time() * 1000 + 999;
        $response = $this->client->post('https://api.cloud.189.cn/open/share/getShareInfoByCodeV2.action', [
            'headers' => [
                'Accept' => 'application/json;charset=UTF-8',
                'Referer' => 'https://h5.cloud.189.cn/',
                'Accesstoken' => $accessToken,
                'Timestamp' => $timestamp,
                'Sign-Type' => 1,
                'Signature' => md5('AccessToken=' . $accessToken . '&Timestamp=' . $timestamp . '&shareCode=' . $shareCode),
            ],
            'form_params' => ['shareCode' => $shareCode],
            'cookies' => $this->cookies,
        ]);
        $fileInfo = json_decode((string)$response->getBody(), true);
        if (!is_array($fileInfo)) {
            throw new Exception('获取分享文件信息失败');
        }

        $timestamp = time() * 1000 + 999;
        $response = $this->client->post('https://api.cloud.189.cn/open/share/checkAccessCode.action', [
            'headers' => [
                'Accept' => 'application/json;charset=UTF-8',
                'Referer' => 'https://h5.cloud.189.cn/',
                'Accesstoken' => $accessToken,
                'Timestamp' => $timestamp,
                'Sign-Type' => 1,
                'Signature' => md5('AccessToken=' . $accessToken . '&Timestamp=' . $timestamp . '&shareCode=' . $shareCode . '&accessCode=' . $accessCode),
            ],
            'form_params' => [
                'shareCode' => $shareCode,
                'accessCode' => $accessCode,
            ],
            'cookies' => $this->cookies,
        ]);
        $shareInfo = json_decode((string)$response->getBody(), true);
        $shareId = $this->extractShareId($shareInfo);
        if ($shareId === '') {
            throw new Exception('访问码校验失败');
        }

        $name = $this->extractShareFileName($fileInfo);
        if ($name === '') {
            throw new Exception('获取文件名失败');
        }
        $fileSize = $this->extractShareFileSize($fileInfo);
        if ($fileSize === null) {
            throw new Exception('获取文件大小失败');
        }

        return [
            'name' => $name,
            'file_size' => $fileSize,
            'file_id' => $this->extractShareFileId($fileInfo),
            'share_id' => $shareId,
        ];
    }

    public function getFileDownloadUrl($shareCode, $accessCode, $accessToken)
    {
        $shareCode = trim((string)$shareCode);
        $accessCode = trim((string)$accessCode);
        $accessToken = trim((string)$accessToken);
        if ($shareCode === '' || $accessCode === '' || $accessToken === '') {
            throw new Exception('参数不完整');
        }

        $this->cookies = new CookieJar();
        $this->client = $this->createClient([
            'cookies' => $this->cookies,
        ]);

        $timestamp = time() * 1000 + 999;
        $response = $this->client->post('https://api.cloud.189.cn/open/share/getShareInfoByCodeV2.action', [
            'headers' => [
                'Accept' => 'application/json;charset=UTF-8',
                'Referer' => 'https://h5.cloud.189.cn/',
                'Accesstoken' => $accessToken,
                'Timestamp' => $timestamp,
                'Sign-Type' => 1,
                'Signature' => md5('AccessToken=' . $accessToken . '&Timestamp=' . $timestamp . '&shareCode=' . $shareCode),
            ],
            'form_params' => ['shareCode' => $shareCode],
            'cookies' => $this->cookies,
        ]);
        $fileInfo = json_decode((string)$response->getBody(), true);
        $fileId = $this->extractShareFileId($fileInfo);
        if ($fileId === '') {
            throw new Exception('获取分享文件失败');
        }

        $timestamp = time() * 1000 + 999;
        $response = $this->client->post('https://api.cloud.189.cn/open/share/checkAccessCode.action', [
            'headers' => [
                'Accept' => 'application/json;charset=UTF-8',
                'Referer' => 'https://h5.cloud.189.cn/',
                'Accesstoken' => $accessToken,
                'Timestamp' => $timestamp,
                'Sign-Type' => 1,
                'Signature' => md5('AccessToken=' . $accessToken . '&Timestamp=' . $timestamp . '&shareCode=' . $shareCode . '&accessCode=' . $accessCode),
            ],
            'form_params' => [
                'shareCode' => $shareCode,
                'accessCode' => $accessCode,
            ],
            'cookies' => $this->cookies,
        ]);
        $shareInfo = json_decode((string)$response->getBody(), true);
        $shareId = $this->extractShareId($shareInfo);
        if ($shareId === '') {
            throw new Exception('访问码校验失败');
        }

        $timestamp = time() * 1000 + 999;
        $response = $this->client->post('https://api.cloud.189.cn/open/file/getFileDownloadUrl.action', [
            'headers' => [
                'Accept' => 'application/json;charset=UTF-8',
                'Referer' => 'https://h5.cloud.189.cn/',
                'Accesstoken' => $accessToken,
                'Timestamp' => $timestamp,
                'Sign-Type' => 1,
                'Signature' => md5('AccessToken=' . $accessToken . '&Timestamp=' . $timestamp . '&dt=1&fileId=' . $fileId . '&shareId=' . $shareId),
            ],
            'form_params' => [
                'fileId' => $fileId,
                'dt' => '1',
                'shareId' => $shareId,
            ],
            'cookies' => $this->cookies,
        ]);
        $downInfo = json_decode((string)$response->getBody(), true);
        if (!is_array($downInfo)) {
            throw new Exception('获取下载地址失败');
        }
        $url = $this->extractDownloadUrl($downInfo);
        if ($url === '') {
            $message = $this->extractApiErrorMessage($downInfo);
            throw new Exception($message !== '' ? '获取下载地址失败: ' . $message : '获取下载地址失败');
        }

        return $this->resolveDownloadUrl($url, $accessToken);

    }

    protected function rsaEncrypt($publicKey, $origData)
    {
        $publicKey = openssl_pkey_get_public($publicKey);
        if ($publicKey === false) {
            throw new Exception('公钥解析失败');
        }

        $keyDetails = openssl_pkey_get_details($publicKey);
        if ($keyDetails === false || empty($keyDetails['bits'])) {
            throw new Exception('公钥详情获取失败');
        }

        $encrypted = '';
        $chunkSize = (int)$keyDetails['bits'] / 8 - 11;
        $offset = 0;
        $length = strlen($origData);
        while ($offset < $length) {
            $chunk = substr($origData, $offset, $chunkSize);
            $encryptedChunk = '';
            if (openssl_public_encrypt($chunk, $encryptedChunk, $publicKey, OPENSSL_PKCS1_PADDING) === false) {
                throw new Exception('RSA加密失败');
            }
            $encrypted .= $encryptedChunk;
            $offset += $chunkSize;
        }

        return strtoupper(bin2hex($encrypted));
    }

    protected function extractShareFileId($fileInfo)
    {
        $value = $this->extractPreferredValue((array)$fileInfo, [
            ['fileId'],
            ['data', 'fileId'],
            ['fileVO', 'fileId'],
            ['fileInfo', 'fileId'],
            ['fileListAO', 'fileList', 0, 'fileId'],
            ['fileList', 0, 'fileId'],
        ], ['fileId']);
        return $value === null ? '' : trim((string)$value);
    }

    protected function extractDownloadUrl($downInfo)
    {
        $value = $this->extractPreferredValue((array)$downInfo, [
            ['fileDownloadUrl'],
            ['data', 'fileDownloadUrl'],
            ['data', 'downloadUrl'],
            ['downloadUrl'],
            ['url'],
        ], ['fileDownloadUrl', 'downloadUrl', 'url']);
        return $value === null ? '' : trim((string)$value);
    }

    protected function resolveDownloadUrl($url, $accessToken)
    {
        $url = trim((string)$url);
        if ($url === '') {
            throw new Exception('下载地址为空');
        }

        $this->client = $this->createClient([
            'cookies' => $this->cookies,
            'allow_redirects' => [
                'max' => 0,
                'protocols' => ['http', 'https'],
                'strict' => false,
                'referer' => false,
                'track_redirects' => false,
            ]
        ]);

        $response = $this->client->get($url, [
            'headers' => [
                'Referer' => 'https://h5.cloud.189.cn/',
                'Accesstoken' => $accessToken,
                'Accept' => '*/*',
            ],
            'cookies' => $this->cookies,
        ]);

        $statusCode = (int)$response->getStatusCode();
        if (in_array($statusCode, [301, 302, 303, 307, 308], true)) {
            $headers = $response->getHeaders();
            $finalUrl = isset($headers['Location'][0]) ? trim((string)$headers['Location'][0]) : '';
            if ($finalUrl !== '') {
                return $finalUrl;
            }
        }

        if ($statusCode >= 200 && $statusCode < 400) {
            return $url;
        }

        $body = trim((string)$response->getBody());
        if ($body !== '') {
            $json = json_decode($body, true);
            if (is_array($json)) {
                $message = $this->extractApiErrorMessage($json);
                if ($message !== '') {
                    throw new Exception($message);
                }
            }
        }

        throw new Exception('下载地址解析失败');
    }

    protected function extractApiErrorMessage($data)
    {
        $value = $this->extractPreferredValue((array)$data, [
            ['errorMsg'],
            ['error_msg'],
            ['message'],
            ['msg'],
            ['res_message'],
            ['res_msg'],
            ['data', 'errorMsg'],
            ['data', 'message'],
            ['data', 'msg'],
        ], ['errorMsg', 'error_msg', 'message', 'msg', 'res_message', 'res_msg']);
        return $value === null ? '' : trim((string)$value);
    }


    protected function extractShareId($shareInfo)
    {
        $value = $this->extractPreferredValue((array)$shareInfo, [
            ['shareId'],
            ['data', 'shareId'],
        ], ['shareId']);
        return $value === null ? '' : trim((string)$value);
    }

    protected function extractShareFileName($fileInfo)
    {
        $value = $this->extractPreferredValue((array)$fileInfo, [
            ['fileName'],
            ['data', 'fileName'],
            ['data', 'name'],
            ['fileVO', 'fileName'],
            ['fileInfo', 'fileName'],
            ['fileListAO', 'fileList', 0, 'fileName'],
            ['fileList', 0, 'fileName'],
            ['fileVO', 'name'],
            ['fileInfo', 'name'],
            ['fileListAO', 'fileList', 0, 'name'],
            ['fileList', 0, 'name'],
            ['name'],
        ], ['fileName', 'filename']);
        return $value === null ? '' : trim((string)$value);
    }

    protected function extractShareFileSize($fileInfo)
    {
        $value = $this->extractPreferredValue((array)$fileInfo, [
            ['fileSize'],
            ['data', 'fileSize'],
            ['data', 'size'],
            ['fileVO', 'fileSize'],
            ['fileInfo', 'fileSize'],
            ['fileListAO', 'fileList', 0, 'fileSize'],
            ['fileList', 0, 'fileSize'],
            ['fileVO', 'size'],
            ['fileInfo', 'size'],
            ['fileListAO', 'fileList', 0, 'size'],
            ['fileList', 0, 'size'],
            ['size'],
        ], ['fileSize', 'filesize']);
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $size = (int)$value;
            return $size >= 0 ? $size : null;
        }
        if (is_string($value) && preg_match('/\d+/', $value, $matches)) {
            return (int)$matches[0];
        }
        return null;
    }

    protected function extractPreferredValue(array $data, array $paths, array $fallbackKeys = [])
    {
        foreach ($paths as $path) {
            $value = $this->getArrayValueByPath($data, $path);
            if ($this->hasMeaningfulValue($value)) {
                return $value;
            }
        }
        if ($fallbackKeys) {
            $value = $this->findNestedValueByKeys($data, $fallbackKeys);
            if ($this->hasMeaningfulValue($value)) {
                return $value;
            }
        }
        return null;
    }

    protected function getArrayValueByPath($data, array $path)
    {
        $current = $data;
        foreach ($path as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }
        return $current;
    }

    protected function findNestedValueByKeys($data, array $keys)
    {
        if (!is_array($data)) {
            return null;
        }
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $this->hasMeaningfulValue($data[$key])) {
                return $data[$key];
            }
        }
        foreach ($data as $value) {
            $found = $this->findNestedValueByKeys($value, $keys);
            if ($this->hasMeaningfulValue($found)) {
                return $found;
            }
        }
        return null;
    }

    protected function hasMeaningfulValue($value)
    {
        return $value !== null && !(is_string($value) && trim($value) === '');
    }

    protected function parseUrlParams($query)
    {
        if (strpos($query, '?') !== false) {
            $query = parse_url($query, PHP_URL_QUERY);
        }
        $params = [];
        parse_str((string)$query, $params);
        return is_array($params) ? $params : [];
    }


    protected function extractFromJsonp($jsonp)
    {
        $jsonStr = preg_replace('/^[^(]*\(|\)[^)]*$/', '', (string)$jsonp);
        $data = json_decode((string)$jsonStr, true);
        return json_last_error() === JSON_ERROR_NONE && is_array($data) ? $data : null;
    }
}
