<?php
/**
 * 微信小程序接口类
 */

namespace App\lib\WeChat;

use Ixudra\Curl\Facades\Curl;
use Illuminate\Support\Facades\Redis;
class WxSmallClient {
    private  $WX_URL    = 'https://api.weixin.qq.com';

    private  $WX_APP_ID = '';
    private  $WX_SECRET = '';
    private $accessToken;
    public  function __construct($appid,$secret)
    {
        $this->WX_APP_ID = $appid;
        $this->WX_SECRET = $secret;
        $this->accessToken = $this->getToken();
    }

    public  function getSessionKey($code)
    {
        if (empty($code))
        {
            return false;
        }
        return Curl::to( $this->WX_URL . '/sns/jscode2session')
            ->withData([
                'appid'=> $this->WX_APP_ID,
                'secret'=> $this->WX_SECRET,
                'js_code'=> $code,
                'grant_type' => 'authorization_code'
            ])
            ->get();
    }

    /**
     * 根据session_key解密用户数据
     */
    public  function decryptData($session_key, $iv, $datas)
    {
        include_once "BizCrypt/wxBizDataCrypt.php";
        $pc = new \WXBizDataCrypt( $this->WX_APP_ID , $session_key);

        $rs = $pc->decryptData( $datas, $iv, $data );
        if ($rs == 0) {
            return $data;
        } else {
            return $rs;
        }
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
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->WX_APP_ID&secret=$this->WX_SECRET";
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
}