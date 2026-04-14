<?php

namespace app\common\library;

use Exception;

/**
 * 天翼云相关接口
 */
class Tianyiyun
{
    protected $ACCOUNT_TYPE = "02";
	protected $APP_ID       = "8025431004";
	protected $CLIENT_TYPE  = "10020";
	protected $VERSION      = "6.2" ;

    protected $WEB_URL    = "https://cloud.189.cn";
	protected $AUTH_URL   = "https://open.e.189.cn";
	protected $API_URL    = "https://api.cloud.189.cn";
	protected $UPLOAD_URL = "https://upload.cloud.189.cn";

	protected $RETURN_URL = "https://m.cloud.189.cn/zhuanti/2020/loginErrorPc/index.html";

	protected $PC  = "TELEPC";
	protected $MAC = "TELEMAC";

	protected $CHANNEL_ID = "web_cloud.189.cn";

    protected $client = null;

    protected $cookies = null;

    public $config = [];


    protected $handler = null;

    function __construct(){
        $this->handler = \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\CurlHandler());
        $this->handler->remove('http_errors');
    }

    /**
     * 模拟H5登录
     * 返回accessToken  3EAFC89B1F371E3E9DE479B3274A538D
     */
    function loginH5($username, $password) {
        $this->cookies = new \GuzzleHttp\Cookie\CookieJar();
        $this->client = new \GuzzleHttp\Client([
            'cookies' => $this->cookies,
            'allow_redirects' => [
                'max' => 0,
                'protocols' => ['http', 'https'],
                'strict' => false,
                'referer' => false,
                'track_redirects' => false,
            ]
        ]);

        // 获取登录页面
        $response = $this->client->get('https://api.cloud.189.cn/open/oauth2/ssoH5.action', [
            'headers' => [
                "Referer" => 'https://h5.cloud.189.cn/',
            ],
            'cookies' => $this->cookies,
        ]);

        while($response->getStatusCode() == 302){
            $headers = $response->getHeaders();
            $response = $this->client->get($headers['Location'][0], [
                'headers' => [
                    "Referer" => 'https://h5.cloud.189.cn/',
                ],
                'cookies' => $this->cookies,
            ]);
        }

        if($response->getStatusCode() != 200){
            throw new Exception('login failed, status code: '.$response->getStatusCode());
        }

        $url = $headers['Location'][0];
        $params = $this->parseUrlParams($url);
        $params['protocol'] = 'https';
        $params['showTip'] = 'true';

        $this->client = new \GuzzleHttp\Client([
            'cookies' => $this->cookies
        ]);
        $response = $this->client->get('https://open.e.189.cn/api/logbox/separate/wap/login.html', [
            'headers' => [
                "Referer" => $url,
            ],
            'query' => $params,
            'cookies' => $this->cookies,
        ]);
        $html = (string)$response->getBody();

        $response = $this->client->get('https://open.e.189.cn/api/logbox/oauth2/wap/appConf.do', [
            'headers' => [
                "content-type" => 'application/x-www-form-urlencoded',
                "Referer" => 'https://open.e.189.cn/api/logbox/separate/wap/login.html?'.http_build_query($params),
            ],
            'query' => $params,
            'cookies' => $this->cookies,
        ]);
        $appConf = json_decode($response->getBody(), true);


        $response = $this->client->post('https://open.e.189.cn/api/logbox/config/encryptConf.do', [
            'headers' => [
                "content-type" => 'application/x-www-form-urlencoded',
                "Referer" => 'https://open.e.189.cn/api/logbox/separate/wap/login.html?'.http_build_query($params),
            ],
            'form_params' => ['appId' => $params['appId']],
            'cookies' => $this->cookies,
        ]);
        $encryptConf = json_decode($response->getBody(), true);
        // echo var_export($encryptConf).PHP_EOL;

        $jRsaKey = "-----BEGIN PUBLIC KEY-----\n" . $encryptConf['data']['pubKey'] . "\n-----END PUBLIC KEY-----";
        $rsaUsername = $encryptConf['data']['pre'] . self::RsaEncrypt($jRsaKey, $username);
        $rsaPassword = $encryptConf['data']['pre'] . self::RsaEncrypt($jRsaKey, $password);

        //检查是否需要验证码
        $captchaResponse = $this->client->post('https://open.e.189.cn/api/logbox/oauth2/needcaptcha.do', [
            'headers' => [
                "Accept" =>  "application/x-www-form-urlencoded",
                "Referer" => 'https://open.e.189.cn/api/logbox/separate/wap/login.html?'.http_build_query($params),
                'Reqid' => $appConf['data']['reqId'],
            ],
            'form_params' => [
                'appKey' => $params['appId'],
                'userName' => $rsaUsername
            ]
        ]);
        $needCaptcha = trim($captchaResponse->getBody());
        if($needCaptcha != '0'){
            throw new Exception('need captcha');
        }

        //模拟登录
        $loginResponse = $this->client->get('https://open.e.189.cn/api/logbox/oauth2/loginSubmit.do', [
            'headers' => [
                "Referer" => 'https://open.e.189.cn/api/logbox/separate/wap/login.html?'.http_build_query($params),
            ],
            'query' => [
                'apptype' => $appConf['data']['infix'],
                'appKey'  => $appConf['data']['appKey'],
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

        $logininfo = $this->extractFromJsonp($loginResponse->getBody());
        if(empty($logininfo)){
            throw new Exception('login failed, response: '.$loginResponse->getBody());
        }
        if($logininfo['result']!=0){
            throw new Exception('login failed, result: '.$logininfo['msg']);
        }

        $this->client = new \GuzzleHttp\Client([
            'cookies' => $this->cookies,
            'allow_redirects' => [
                'max' => 0,
                'protocols' => ['http', 'https'],
                'strict' => false,
                'referer' => false,
                'track_redirects' => false,
            ]
        ]);
        $response = $this->client->get($logininfo['toUrl'], [
            'headers' => [
                "Referer" => 'https://open.e.189.cn/',
            ],
            'cookies' => $this->cookies,
        ]);

        while($response->getStatusCode() == 302){
            $headers = $response->getHeaders();
            $response = $this->client->get($headers['Location'][0], [
                'headers' => [
                    "Referer" => 'https://open.e.189.cn/',
                ],
                'cookies' => $this->cookies,
            ]);
        }
        if($response->getStatusCode() != 200){
            throw new Exception('login failed, status code: '.$response->getStatusCode());
        }
        $userinfo = $this->parseUrlParams($headers['Location'][0]);
        echo $userinfo['accessToken'].PHP_EOL; 
        echo var_export($this->cookies).PHP_EOL; 


        $response = $this->client->get('https://cloud.189.cn/api/portal/v2/getUserBriefInfo.action', [
            'query' => [
                "noCache" => time(),
            ],
            'cookies' => $this->cookies,
        ]);
        
        echo $response->getBody().PHP_EOL;

        return $userinfo['accessToken'];
    }

    /**
     * 检测用户token是否有效
     */
    function checkUserInfo($accessToken){

        $this->cookies = new \GuzzleHttp\Cookie\CookieJar();
        $this->client = new \GuzzleHttp\Client([
            'handler' => $this->handler,
            'cookies' => $this->cookies,
        ]);

        $timestamp = time() * 1000 + 999;

        $response = $this->client->get('https://api.cloud.189.cn/open/user/getUserInfo.action', [
            'headers' => [
                "Accept" =>  "application/json;charset=UTF-8",
                "Referer" => "https://h5.cloud.189.cn/",
                "Accesstoken" => $accessToken,
                "Timestamp" => $timestamp,
                "Sign-Type" => 1,
                "Signature" => md5("AccessToken=".$accessToken."&Timestamp=".$timestamp)
            ],
            'cookies' => $this->cookies,
        ]);
        
        if($response->getStatusCode()!=200){
            return false;
        }

        return true;
    }

    /**
     * 获取文件下载地址
     */
    function getFileDownloadUrl($shareCode, $accessCode, $accessToken){
        $this->cookies = new \GuzzleHttp\Cookie\CookieJar();
        $this->client = new \GuzzleHttp\Client([
            'cookies' => $this->cookies,
        ]);

        $timestamp = time() * 1000 + 999;
        $response = $this->client->post('https://api.cloud.189.cn/open/share/getShareInfoByCodeV2.action', [
            'headers' => [
                "Accept" =>  "application/json;charset=UTF-8",
                "Referer" => "https://h5.cloud.189.cn/",
                "Accesstoken" => $accessToken,
                "Timestamp" => $timestamp,
                "Sign-Type" => 1,
                "Signature" => md5("AccessToken=".$accessToken."&Timestamp=".$timestamp."&shareCode=".$shareCode)
            ],
            'form_params' => [
                'shareCode' => $shareCode,
            ],
            'cookies' => $this->cookies,
        ]);
        $fileinfo = json_decode($response->getBody(), true);

        $fileId = $fileinfo['fileId'];


        $timestamp = time() * 1000 + 999;
        $response = $this->client->post('https://api.cloud.189.cn/open/share/checkAccessCode.action', [
            'headers' => [
                "Accept" =>  "application/json;charset=UTF-8",
                "Referer" => "https://h5.cloud.189.cn/",
                "Accesstoken" => $accessToken,
                "Timestamp" => $timestamp,
                "Sign-Type" => 1,
                "Signature" => md5("AccessToken=".$accessToken."&Timestamp=".$timestamp."&shareCode=".$shareCode."&accessCode=".$accessCode)
            ],
            'form_params' => [
                'shareCode' => $shareCode,
                'accessCode' => $accessCode,
            ],
            'cookies' => $this->cookies,
        ]);
        $shareinfo = json_decode($response->getBody(), true);

        $shareId = $shareinfo['shareId'];


        $timestamp = time() * 1000 + 999;
        $response = $this->client->post('https://api.cloud.189.cn/open/file/getFileDownloadUrl.action', [
            'headers' => [
                "Accept" =>  "application/json;charset=UTF-8",
                "Referer" => "https://h5.cloud.189.cn/",
                "Accesstoken" => $accessToken,
                "Timestamp" => $timestamp,
                "Sign-Type" => 1,
                "Signature" => md5("AccessToken=".$accessToken."&Timestamp=".$timestamp."&dt=1&fileId=".$fileId."&shareId=".$shareId)
            ],
            'form_params' => [
                'fileId' => $fileId,
                'dt' => '1',
                'shareId' => $shareId,
            ],
            'cookies' => $this->cookies,
        ]);
        $downinfo = json_decode($response->getBody(), true);
        
        $url = $downinfo['fileDownloadUrl'];

        $this->client = new \GuzzleHttp\Client([
            'cookies' => $this->cookies,
            'allow_redirects' => [
                'max' => 0,
                'protocols' => ['http', 'https'],
                'strict' => false,
                'referer' => false,
                'track_redirects' => false,
            ]
        ]);

        // 获取登录页面
        $response = $this->client->get($url, [
            'headers' => [
                "Referer" => 'https://h5.cloud.189.cn/',
            ],
            'cookies' => $this->cookies,
        ]);

        if($response->getStatusCode() != 302){
            throw new Exception('get download url failed, return: '.$response->getBody());
        }
        $headers = $response->getHeaders();
        return $headers['Location'][0];
    }

    
    
    /**
     *  RSA 加密
     */
    private function RsaEncrypt($publicKey, $origData) {
        // 解析 PEM 格式的公钥
        $publicKey = openssl_pkey_get_public($publicKey);
        if ($publicKey === false) {
            throw new Exception('Failed to parse public key');
        }
        
        // 获取公钥详情
        $keyDetails = openssl_pkey_get_details($publicKey);
        if ($keyDetails === false) {
            throw new Exception('Failed to get key details');
        }
        
        $encrypted = '';
        $chunkSize = $keyDetails['bits'] / 8 - 11; // RSA 加密的最大块大小
        
        // 分块加密
        $offset = 0;
        while ($offset < strlen($origData)) {
            $chunk = substr($origData, $offset, $chunkSize);
            $encryptedChunk = '';
            
            if (openssl_public_encrypt($chunk, $encryptedChunk, $publicKey, OPENSSL_PKCS1_PADDING) === false) {
                throw new Exception('Encryption failed');
            }
            
            $encrypted .= $encryptedChunk;
            $offset += $chunkSize;
        }
        
        // 转换为大写十六进制字符串
        return strtoupper(bin2hex($encrypted));
    }

    /**
     * 将URL查询字符串转换为关联数组
     * @param string $query URL查询字符串（可以包含问号，也可以不包含）
     * @return array 参数键值对数组
     */
    private function parseUrlParams($query) {
        // 如果包含问号，则去除问号及之前的部分
        if (strpos($query, '?') !== false) {
            $query = parse_url($query, PHP_URL_QUERY);
        }
        
        // 解析查询字符串
        parse_str($query, $params);
        
        // 返回参数数组
        return $params;
    }


    /**
     * 从JSONP字符串中提取数组
     * @param string $jsonp JSONP字符串
     * @return array 包含result和toUrl的数组
     */
    function extractFromJsonp($jsonp) {
        // 移除回调函数名和括号
        $jsonStr = preg_replace('/^[^(]*\(|\)[^)]*$/', '', $jsonp);
            
        // 将JSON字符串转换为PHP数组
        $data = json_decode($jsonStr, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        }
        return null;
    }
}
