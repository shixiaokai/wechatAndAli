<?php
/**
 * Created by PhpStorm.
 * User:石晓凯
 * Date: 2017/6/7
 * Time: 11:37
 * 注: 本demo采用的加密方式为RSA,如需更换加密方式，请开发者自行修改相关配置
 */
namespace App\lib;
use App\lib\AliApi\aop\AopClient;
use App\lib\AliApi\aop\request\AlipaySystemOauthTokenRequest;
use App\lib\AliApi\aop\request\AlipayTradeAppPayRequest;
use App\lib\AliApi\aop\request\AlipayTradeQueryRequest;
use App\lib\AliApi\aop\request\AlipayTradeRefundRequest;
use App\lib\AliApi\aop\request\AlipayTradeWapPayRequest;
use App\lib\AliApi\aop\request\AlipayUserInfoShareRequest;
use App\lib\AliApi\aop\request\AlipayFundTransToaccountTransferRequest;
use Illuminate\Support\Facades\Storage;

class Alijssdk
{
    private $appid;
    private $aop;

    public function __construct()
    {
        $this->appid = env('ALI_APPID');
        $objaop = new AopClient ();
        $this->aop = $objaop;
        $this->aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';//ali网关地址
        $this->aop->appId = $this->appid;// 支付宝分配的appid
        $this->aop->rsaPrivateKey = '';//私钥
        $this->aop->alipayrsaPublicKey='';//支付宝公钥
        $this->aop->apiVersion = '1.0';
        $this->aop->signType = 'RSA';
        $this->aop->postCharset='UTF-8';
        $this->aop->format='json';
    }

    /**
     * @param $data array()
     * @return String
     * @throws \Exception
     * 支付宝网站支付
     */
    public function alipay( $data = array() )
    {
        if( empty($data) || count($data) <= 0 )
        {
            return '';
        }
        $request = new AlipayTradeWapPayRequest ();
        $request->setReturnUrl($data['return_url']);
        $request->setNotifyUrl($data['notify_url']);
        unset($data['return_url']);
        unset($data['notify_url']);
        $request->setBizContent( json_encode($data) );
        $result = $this->aop->pageExecute ( $request );
        return $result;
    }



    public function getSign($data){
        $sign = $this->aop->getSignContent($data);
        $signs = $this->aop->alonersaSign($sign,$this->aop->rsaPrivateKey,'RSA');
        return $signs;
    }
    /**
     * @param $data array()
     * @return String
     * @throws \Exception
     * 支付宝app支付
     */
    public function aliapppay( $data = array() )
    {
        if( empty($data) || count($data) <= 0 )
        {
            return '';
        }
//            "{\"body\":\"我是测试数据\","
//            . "\"subject\": \"App支付测试\","
//            . "\"out_trade_no\": \"20170125test01\","
//            . "\"timeout_express\": \"30m\","
//            . "\"total_amount\": \"0.01\","
//            . "\"product_code\":\"QUICK_MSECURITY_PAY\""
//                'notify_url'=>'http://'.env('API_DOMAIN').'/index/caralinotify'
//            . "}
        $request = new AlipayTradeAppPayRequest();
        $request->setNotifyUrl($data['notify_url']);
        unset($data['notify_url']);
        $request->setBizContent( json_encode($data) );
        $result = $this->aop->sdkExecute ( $request );
        return $result;
    }

    /**
     * @param $data array() 异步通知结果
     * @return bool
     */
    public function verifysign($data)
    {
        return $this->aop->rsaCheckV1($data,$this->aop->alipayrsaPublicKey);
    }
    /**
     *获取授权code
     *静默授权
     * 1,静默授权2,授权确认
     */
    public function getCode( $scope = 1 )
    {
        if( $scope == 1)
        {
            $scope = 'auth_base';
        }else{
            $scope = 'auth_user';
        }
        //通过code获得userid
        if (!isset($_GET['auth_code']))
        {
            //触发支付宝返回code码
            $baseUrl = urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
            $url = $this->__CreateOauthUrlForUserCode($baseUrl,$scope);
            Header("Location: $url");
            exit();
        } else {
            //获取code码，以获取userid
            $code = $_GET['auth_code'];
            $user = $this->GetOpenidFromCode($code,$scope);
            return $user;
        }

    }

    /**
     * @param $data
     * @return array|bool|mixed|\SimpleXMLElement
     * @throws \Exception
     * 单笔转账给个人账户
     */
    public function transfer( $data )
    {
        $request = new AlipayFundTransToaccountTransferRequest ();
        $request->setBizContent(json_encode($data));
        $result = $this->aop->execute ( $request);
        $result = Util::objectToArray($result);
        $res = Util::objectToArray($result['alipay_fund_trans_toaccount_transfer_response']);
        if( !isset($res['code']) || $res['code'] != 10000 || $res['msg'] != 'Success' )
        {
            $array = array('code'=>'400','msg'=>$res['sub_msg']);
            if($res['sub_code'] == 'PAYEE_NOT_EXIST')
            {
                $array = array(//收款账号不存在
                    'code'=>'200',
                    'msg'=>$res['sub_msg'],
                );
            }
            if($res['sub_code'] == 'PAYER_BALANCE_NOT_ENOUGH')
            {
                $array = array(//付款账户余额不足
                    'code'=>'300',
                    'msg'=>$res['sub_msg'],
                );
            }
            return $array;
        }
        $res['sign'] = $result['sign'];
        return $res;
    }
    /**
     * @param $data array()
     * @return array|bool|mixed|\SimpleXMLElement
     * @throws \Exception
     * 订单退款--即时到账
     */
    public function refund( $arr = array() )
    {
        if( !isset($arr['orderNumber']) || empty( trim( $arr['orderNumber'] ) ) )
        {
            return false;
        }
        if( !isset($arr['total_fee']) || empty( trim( $arr['total_fee'] ) ) )
        {
            return false;
        }
        $data = array();
        $data['out_trade_no'] = trim($arr['orderNumber']);
        $data['refund_amount'] = trim($arr['total_fee']);
        $request = new AlipayTradeRefundRequest  ();
        $request->setBizContent(json_encode($data));
        $result = $this->aop->execute ( $request);
        $result = Util::objectToArray($result);
        $res = Util::objectToArray($result['alipay_trade_refund_response']);
        
        if( isset( $res['code'] ) && $res['code'] == '10000' || ( isset($res['msg']) && $res['msg'] == 'Success' ) )
        {
            return true;
        }
        return false;
    }

    /**
     * @param array $arr
     * @return bool
     * 单笔订单详情
     */
    public function queryOrder( $arr = array() )
    {
        if( empty($arr) || !isset( $arr['orderNumber'] ) || empty( $arr['orderNumber'] ) )
        {
            return false;
        }
        $data = array(
            'trade_no'=>$arr['orderNumber'],//支付宝交易号
        );
        $request = new AlipayTradeQueryRequest();
        $request->setBizContent(json_encode($data));
        $result = $this->aop->execute ( $request);
        $result = Util::objectToArray($result);
        $res = Util::objectToArray($result['alipay_trade_query_response']);
        return $res;
    }
    /**
     *
     * 构造获取能获取用户信息的code的url连接
     * @param string $redirectUrl 支付宝服务器回跳的url，需要url编码
     * @return 返回构造好的url
     */
    private function __CreateOauthUrlForUserCode($redirectUrl,$scope)
    {
        $urlObj["app_id"] = $this->appid;//WxPayConfig::APPID;
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["scope"] = "$scope";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?".$bizString;
    }

    /**
     *
     * 通过code从工作平台获取openid机器access_token
     * @param string $code 支付宝跳转回来带上的code
     * @return openid
     */
    public function GetOpenidFromCode($code,$scope)
    {
        $request = new AlipaySystemOauthTokenRequest ();
        $request->setGrantType("authorization_code");
        $request->setCode($code);
        $result = $this->aop->execute ( $request);
        $result = Util::objectToArray($result);
        if( isset($result['error_response']) )
        {
            $return = Util::objectToArray($result['error_response']);
            return $return['code'].$return['sub_msg'];
        }
        $return  = Util::objectToArray($result['alipay_system_oauth_token_response']);
        if( $scope == 'auth_user' || $scope == '2' )//主动授权
        {
            $request = new AlipayUserInfoShareRequest();
            $result = $this->aop->execute ( $request , $return['access_token'] );
            $result = Util::objectToArray($result);
            //alipay_user_info_share_response
            if( isset($result['error_response']) )
            {
                $return = Util::objectToArray($result['error_response']);
                return $return['code'].$return['sub_msg'];
            }
            $return = Util::objectToArray($result['alipay_user_info_share_response']);
        }
        return $return;
    }

    /**
     *
     * 构造获取open和access_toke的url地址
     * @param string $code，支付宝跳转带回的code
     *
     * @return 请求的url
     */
    private function __CreateOauthUrlForOpenid($code,$scope)
    {
        $urlObj["app_id"] = $this->appid;
        $urlObj["source"] = 'alipay_wallet';
        $urlObj["scope"] = $scope;
        $urlObj["auth_code"] = "$code";
        $bizString = $this->ToUrlParams($urlObj);
        return "http://example.com/doc/toAuthPage.html?".$bizString;
    }
    /**
     *
     * 拼接签名字符串
     * @param array $urlObj
     *
     * @return 返回已经拼接好的字符串
     */
    public function ToUrlParams($urlObj)
    {
        $buff = "";
        foreach ($urlObj as $k => $v)
        {
            if($k != "sign"){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }
}