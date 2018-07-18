<?php

namespace App\Http\Controllers\Wechat;

use App\lib\Alijssdk;
use App\lib\Util;
use App\lib\WeChat\Transfer\JSSDK;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    /*
     * 生成订单
     */
    public function toindex(Request $request)
    {
        $paytype = fun_aliorwechat();
        if(!in_array($paytype,[1,2])){
            $this->respon(0,'请在微信或支付宝客户端打开');
        }
        if(!$_SESSION['openid']){
            $userInfo = get_userInfo_wechat_Ali($paytype);
            if($userInfo['errorCode'] == 0 ) return $userInfo['data'];
            $user = $userInfo['data'];
        }
        if($_POST){
            try
            {
                //env('WX_APPID');  支付appid
                //env('WX_MCHID');  支付商户号
                //env('WX_KEY');  支付key
                $money = $request->input('money',0);
                if($money <= 0 ) respon(0,'缺少支付金额');
                $order_number = date('YmdHis',time() ).rand(1111,9999);//订单号，开发者可自行生成(保证商户平台内唯一)
                $openid = isset($_SESSION['openid'])?$_SESSION['openid']:(isset($user['openid'])?$user['openid']:respon(0,'网络异常') );
                //微信
                if($paytype == 1) {
                    $body_cont = 'JSAPI支付测试';
                    $key = env('WX_KEY');
                    $rand = md5(time() . mt_rand(0,1000));
                    $param["appid"] = env('WX_APPID');
                    $param["openid"] = $openid;
                    $param["mch_id"] = env('WX_MCHID');
                    $param["nonce_str"] = "$rand";
                    $param["body"] = $body_cont;
                    $param["out_trade_no"] = $order_number; //订单单号
                    $param["total_fee"] = $money*100;//支付金额
                    $param["spbill_create_ip"] = $_SERVER["REMOTE_ADDR"];
                    $param["notify_url"] = $_SERVER['HTTP_HOST']."/index/wxnotify";//回调
                    $param["trade_type"] = "JSAPI";
                    $signStr = 'appid='.$param["appid"]."&body=".$param["body"]."&mch_id=".$param["mch_id"]."&nonce_str=".$param["nonce_str"]."&notify_url=".$param["notify_url"]."&openid=".$param["openid"]."&out_trade_no=".$param["out_trade_no"]."&spbill_create_ip=".$param["spbill_create_ip"]."&total_fee=".$param["total_fee"]."&trade_type=".$param["trade_type"];
                    $signStr = $signStr."&key=$key";
                    $param["sign"] = strtoupper(MD5($signStr));
                    $data = Util::arrayToXml($param);
                    $postResult = Util::postCurl("https://api.mch.weixin.qq.com/pay/unifiedorder",$data);
                    $postObj = Util::xmlToArray( $postResult );
                    $msg = $postObj['return_code'];
                    if($msg == "SUCCESS"){
                        $result["timestamp"] = time();
                        $result["nonceStr"] = $postObj['nonce_str'];  //不加""拿到的是一个json对象
                        $result["package"] = "prepay_id=".$postObj['prepay_id'];
                        $result["signType"] = "MD5";
                        $paySignStr = 'appId='.$param["appid"].'&nonceStr='.$result["nonceStr"].'&package='.$result["package"].'&signType='.$result["signType"].'&timeStamp='.$result["timestamp"];
                        $paySignStr = $paySignStr."&key=$key";
                        $result["paySign"] = strtoupper(MD5($paySignStr));
                        $result['appId'] = env('WX_APPID');
                        respon( 1, $result );
                    }else{
                        throw new \Exception( $postObj );
                    }
                }elseif($paytype==2){//支付宝
                    $AliSSDK = new Alijssdk();
                    $pay = array(
                        'subject'=>'QUICK_WAP_PAY支付测试',
                        'out_trade_no'=>$order_number,
                        'total_amount'=>$money,
                        'product_code'=>'QUICK_WAP_PAY',
                        'return_url'=>'http://'.$_SERVER['HTTP_HOST'].'/index/share',//支付完成跳转页面
                        'notify_url'=>'http://'.$_SERVER['HTTP_HOST'].'/index/alinotify'//异步回调地址
                    );
                    $formdata = $AliSSDK->alipay($pay);
                    respon(1688,$formdata);
                }else{
                    throw new \Exception( '支付失败!!' );
                }
            }catch (\Exception $e)
            {
                respon(0,$e->getMessage());
                return false;
            }
        }
        $arr['type'] = $paytype;
        if($paytype == 1){
            $jssdk= new JSSDK();
            $signPackage = $jssdk->getSignPackage(1);
            $arr['signPackage'] = $signPackage;
        }
        return view('h5.order.pay',$arr);
    }

    /*
    * 微信回调
    * */
    public function wxnotify()
    {
        $xml = file_get_contents("php://input");
        //回调数据为空
        if(!$xml){
            echo '123'; exit;
        }
        $data = Util::xmlToArray($xml);
        if($data && $data['result_code']=='SUCCESS')
        {
            //此处为回调信息开发者需自行写业务回调
            Storage::disk('paylog')->append('wechatnotify.log','回调数据:'."\n".json_encode($data)."\n".' 时间：'.date('Y-m-d H:i:s',time()));
            $res = '业务处理结果';
            if($res){
                return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
                exit;
            }else{
                return "<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[error]]></return_msg></xml>";
                exit;
            }
        }else{
            return "<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[error]]></return_msg></xml>";
            exit;
        }
    }
    /*
    * 支付宝回调
    * */
    public function alinotify()
    {
        date_default_timezone_set('Asia/Shanghai');
        $data = $_POST;
        if( empty($data) || count($data)<=0 ){
            Storage::disk('paylog')->append('alinotify_null.log','回调数据:null 时间:'.date('Y-m-d H:i:s',time()));
        }
        if( $data && ( $data['trade_status'] == 'TRADE_SUCCESS' || $data['trade_status'] == 'TRADE_FINISHED' ) ){
            Storage::disk('paylog')->append('alinotify.log','回调数据:'."\n".json_encode($data)."\n".' 时间：'.date('Y-m-d H:i:s',time()));
            $aliInfo = new Alijssdk();
            //此处为回调信息验证签名是否正确签名通过开发者需自行写业务回调
            $sign = $aliInfo->verifysign($data);
            if( !$sign ){
                Storage::disk('paylog')->append('notify_order_fait.log', '回调记录3——签名错误时间:'.date('Y-m-d H:i:s')."\n".'data:'.json_encode($data));
                return 'FAIT';
            }
            return 'success';
            exit;
        }else{
            Storage::disk('paylog')->append('alinotify_fait.log', '回调记录2——回掉数据时间:'.date('Y-m-d H:i:s')."\n".'data:'.json_encode($data));
            echo 'FAIT';
            exit;
        }
    }
}
