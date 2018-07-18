<?php
namespace App\lib\WeChat\Transfer;
use Ixudra\Curl\Facades\Curl;
use Illuminate\Support\Facades\Redis;
class JSSDK {

    private $appid = '';
    private $h5appid = '';
    private $secrect = '';
    private $h5secrect = '';
    private $accessToken;
    private $H5accessToken;

    public function __construct()
    {
      $this->appid = env('XCX_APPID');
      $this->secrect= env('XCX_SERECT');
      $this->h5appid= env('WX_APPID');
      $this->h5secrect= env('WX_APPSECRET');
      $this->accessToken = $this->getToken();
      $this->H5accessToken = $this->getTokenToH5();
    }

    /**
     * @param $appid
     * @param $appsecret
     * @return mixed
     * 获取token
     */
    public function getToken() {
        $access_token = Redis::get('xcx_access_token');
        if (!$access_token) {
            // 如果是企业号用以下URL获取access_token
            //$url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$this->appId&corpsecret=$this->appSecret";
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appid&secret=$this->secrect";
            $res = json_decode($this->httpGet($url));
            $access_token = $res->access_token;
            if ($access_token) {
                Redis::setex('xcx_access_token', 7000, $access_token);
            }
        } else {
            return $access_token;
        }
        return $access_token;
    }
    public function getTokenToLM()
    {
        $access_token = Redis::get('lm_access_token');
        if (!$access_token) {
            // 如果是企业号用以下URL获取access_token
            //$url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$this->appId&corpsecret=$this->appSecret";
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->lmappid&secret=$this->lmsecrect";
            $res = json_decode($this->httpGet($url));
            $access_token = $res->access_token;
            if ($access_token) {
                Redis::setex('lm_access_token', 7000, $access_token);
            }
        } else {
            return $access_token;
        }
        return $access_token;
    }

    public function getTokenToH5()
    {
        $access_token = Redis::get('h5_access_token');
        if (!$access_token) {
            // 如果是企业号用以下URL获取access_token
            //$url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$this->appId&corpsecret=$this->appSecret";
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->h5appid&secret=$this->h5secrect";
            $res = json_decode($this->httpGet($url));
            $access_token = $res->access_token;
            if ($access_token) {
                Redis::setex('h5_access_token', 7000, $access_token);
            }
        } else {
            return $access_token;
        }
        return $access_token;
    }
    /**
     * 推送消息
     * @param $touser  openid
     * @param $template_id  模板id
     * @param string $page  点击模板跳转地址
     * @param $data         模板数据
     * @param $formId
     * @param string $color 字体颜色
     * @return mixed
     */
    public function sendTemplate($touser,$template_id,$page = 'pages/integrals/integrals',$data,$formId,$color = '#173177')
    {
        $template = array(
            'touser' => $touser,
            'template_id' => $template_id,
            'page' => $page,
            'form_id' => $formId,
            'data' => $data,
        );
        $json_template = json_encode($template);
        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $this->accessToken;
        $dataRes = Curl::to($url)
            ->withData(urldecode($json_template))
            ->post();
        $dataRes = json_decode($dataRes,true);
        return $dataRes;
    }


    public function getSignPackage($type=0) {
        $jsapiTicket = $this->getJsApiTicket($type);
        // 注意 URL 一定要动态获取，不能 hardcode.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $timestamp = time();
        $nonceStr = $this->createNonceStr();
        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        $signature = sha1($string);
        $appid = ($type==1)?$this->h5appid:$this->appid;
        $signPackage = array(
            "appId" =>$appid,
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
            "url" => $url,
            "signature" => $signature,
            "rawString" => $string
        );
        return $signPackage;
    }

    public function doSend($touser, $template_id,$url, $data, $topcolor = '#173177')
    {
        $template = array(
            'touser' => $touser,
            'template_id' => $template_id,
            'url' => $url,
            'topcolor' => $topcolor,
            'data' => $data
        );
        $json_template = json_encode($template);
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" .$this->H5accessToken;
        $dataRes = $this->httpRequest($url, urldecode($json_template));
        $dataRes = json_decode($dataRes,true);
        return $dataRes;
    }

    private function createNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    private function getJsApiTicket($type=0) {
        // jsapi_ticket 应该全局存储与更新，以下代码以写入到文件中做示例
        if($type == 1)
        {
            $key = 'h5_jsapi_ticket';
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=".$this->H5accessToken;
        }else
        {
            $key = 'jsapi_ticket';
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=".$this->accessToken;
        }
        $jsapi_ticket = Redis::get($key);
        if (!$jsapi_ticket) {
            // 如果是企业号用以下URL获取access_token
            $res = json_decode($this->httpGet($url));
            $jsapi_ticket = $res->ticket;
            if ($jsapi_ticket) {
                Redis::setex($key, 7000, $jsapi_ticket);
            }
        }
        return $jsapi_ticket;
    }

    /**
     * 发送get请求
     * @param string $url
     * @return bool|mixed
     */
    private function httpGet($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);
    
        $res = curl_exec($curl);
        curl_close($curl);
        return $res;
    }
    /**
     * 发送post请求
     * @param string $url
     * @param string $param
     * @return bool|mixed
     */
    public function httpRequest($url, $post, $header = array(), $connectTimeout = 15, $readTimeout = 300)
    {
        if (function_exists('curl_init')) {
            $timeout = $connectTimeout + $readTimeout;
            $ch = curl_init();
            if (strpos($url, 'https://') !== false) {	// HTTPS
                //curl_setopt($ch, CURLOPT_SSLVERSION, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            }
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if ($post == 'get') {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true) ; // 获取数据返回
                curl_setopt($ch, CURLOPT_BINARYTRANSFER, true) ; // 在启用 CURLOPT_RETURNTRANSFER 时候将获取数据返回
            } else {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            }
            $result = curl_exec($ch);
            curl_close($ch);
            return $result;
        }
    }
}

