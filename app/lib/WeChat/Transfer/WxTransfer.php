<?php
/**
 * Created by PhpStorm.
 * User: shixiaokai
 * Date: 2017/6/19
 * Time: 10:31
 * 微信转账到个人用户
 */

namespace App\lib\WeChat\Transfer;

use App\lib\Util;
use Illuminate\Support\Facades\Storage;
use Ixudra\Curl\Facades\Curl;

class WxTransfer {
    private  $WX_URL = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
    private  $WX_REFUND_URL = 'https://api.mch.weixin.qq.com/secapi/pay/refund';
    private  $WX_ORDER_QUERY = 'https://api.mch.weixin.qq.com/pay/orderquery';
    private  $TURN_MCH_APPID = '';
    private  $TURN_MCHID = '';
    private  $TURN_MCHID_KING = '';
    private  $TURN_KEY = '';

    public  function __construct()
    {
        $this->TURN_MCH_APPID = env('TURN_MCH_APPID');
        $this->TURN_MCHID =  env('TURN_MCHID');
        $this->TURN_KEY = env('TURN_KEY');
        $this->TURN_MCHID_KING =  env('WX_MCHID_KING');
    }
    public function WxTransfer($data)
    {
        $data['mch_appid'] = $this->TURN_MCH_APPID;
        $data['mchid'] = $this->TURN_MCHID;
        $data['spbill_create_ip'] = Util::get_client_ip();
        ksort($data);
        $signStr = $this->getWxSign( $data );
        $data["sign"] = strtoupper(MD5($signStr));
        $xml = Util::arrayToXml($data);
        $res = Util::weachatPostPemCurl($this->WX_URL,$xml);
        $res = Util::xmlToArray($res);
        Storage::disk('test')->append('wxTransfer.log','时间:'.date('Y-m-d H:i:s').print_r($res , 1));
        return $res;
    }

    /**
     * @param $arr array() 商户系统内部的退款单号--订单金额--退款金额
     * 微信退款
     * 带子商户退款
     */
    public function refund( $arr = array(), $type=0 )
    {
        if( !isset($arr['orderNumber']) || empty( trim( $arr['orderNumber'] ) ) )
        {
            return false;
        }
        if( !isset($arr['total_fee']) || empty( trim( $arr['total_fee'] ) ) )
        {
            return false;
        }
        if( $type==1 )
        {
            if( !isset($arr['sub_mch_id']) || empty( trim( $arr['sub_mch_id'] ) ) )
            {
                return false;
            }
        }
        $data = array();
        $rand = md5(time() . mt_rand(0,1000));
        $data['appid'] = $this->TURN_MCH_APPID;
        $data['mch_id'] =  $this->TURN_MCHID;
        $data['nonce_str'] = "$rand";
        if($type==1)
        {
            $data['mch_id'] =  $this->TURN_MCHID_KING;
            $data['sub_mch_id'] =  $arr['sub_mch_id'];
        }
        $data['out_trade_no'] = $arr['orderNumber'] ;
        $data['out_refund_no'] = $arr['orderNumber'] ;
        $data['total_fee'] = $arr['total_fee']*100;
        $data['refund_fee'] = isset( $arr['refund_fee'] ) && $arr['refund_fee'] > 0 ? $arr['refund_fee']*100 : $arr['total_fee']*100;
        ksort($data);
        $signStr = $this->getWxSign( $data );
        $data["sign"] = strtoupper(MD5($signStr));
        $xml = Util::arrayToXml($data);
        $res = Util::weachatPostPemCurl($this->WX_REFUND_URL,$xml, $type);
        $res = Util::xmlToArray($res);
//        Storage::disk('test')->append('wechatrefunds.log',json_encode($res , 1));
        if( isset($res['result_code']) && $res['result_code'] == 'SUCCESS' )
        {
            return 1;
        }
        return false;
    }

    /**
     * @param array $arr
     * 微信订单查询
     * 参数商户自行生成的唯一订单或微信回掉订单
     * 带子商户查询
     */
    public function orderQuery( $arr = array(), $type=0 )
    {
        if( !isset($arr['orderNumber']) || empty( trim( $arr['orderNumber'] ) ) )
        {
            return false;
        }
        if( $type==1 )
        {
            if( !isset($arr['sub_mch_id']) || empty( trim( $arr['sub_mch_id'] ) ) )
            {
                return false;
            }
        }
        $data = array();
        $rand = md5(time() . mt_rand(0,1000));
        $data['appid'] = $this->TURN_MCH_APPID;
        $data['mch_id'] =  $this->TURN_MCHID;
        if($type==1)
        {
            $data['mch_id'] =  $this->TURN_MCHID_KING;
            $data['sub_mch_id'] =  $arr['sub_mch_id'];
        }

        $data['nonce_str'] = "$rand";
        //$data['out_trade_no'] = $arr['orderNumber'] ;
        $data['transaction_id'] = $arr['orderNumber'] ;
        ksort($data);
        $signStr = $this->getWxSign( $data );
        $data["sign"] = strtoupper(MD5($signStr));
        $xml = Util::arrayToXml($data);
        $res = Util::weachatPostPemCurl($this->WX_ORDER_QUERY,$xml, $type);
        $res = Util::xmlToArray($res);
        return $res;
    }

    private function getWxSign($arr)
    {
        $buff = "";
        foreach ($arr as $k => $v)
        {
            if($k != "sign"){
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        $str = $buff."&key=$this->TURN_KEY";
        return $str;
    }

}