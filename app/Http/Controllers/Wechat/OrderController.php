<?php

namespace App\Http\Controllers\Wechat;

use App\lib\Alijssdk;
use App\lib\common\CommonAction;
use App\lib\Util;
use App\lib\WeChat\Transfer\JSSDK;
use App\Models\Merchant\branch;
use App\Models\Merchant\channel_attributes;
use App\Models\Merchant\coupon_use_log;
use App\Models\Merchant\customer_coupon;
use App\Models\CommChannel\channel_customer;
use App\Models\Merchant\channel_partner;
use App\Models\Merchant\client;
use App\Models\Merchant\container_goods_stock;
use App\Models\MerchantApi\customer;
use App\Models\MerchantAdmin\money_record;
use App\Models\Operate\operates;
use App\Models\MerchantApi\order_info;
use App\Models\MerchantApi\order_sku;
use App\Models\MerchantApi\receive_record;
use App\Models\Supply\supply_partner_relation;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    /**
     * H5首页 针对不同的设备类型，输出不同的界面
     * @param Request $request
     */
    public function toindex(Request $request)
    {
        $ali_or_wechat = fun_aliorwechat(); //访问类型是否是支付宝或微信
        if (!in_array($ali_or_wechat, [1,2])) {
            return fun_error_page('请在微信或支付宝客户端扫描打开');
        }
        $clientHash = $request->input('clientHash');
        if($clientHash)
        {
            session(['clientHash'=>$clientHash]);
        }else
        {
            $clientHash = session('clientHash');
        }
        //获取设备信息
        $clientInfo = get_clientInfo($clientHash);
        if( $clientInfo['errorCode'] != 0 )
        {
            //savelog('client_err_code', json_encode($clientInfo));
            if(!isset($clientInfo['client']))
            {
                return fun_error_page($clientInfo['erroeMessage']);
            }else
            {
                if($ali_or_wechat == 1){
                    $jssdk= new JSSDK();
                    $signPackage = $jssdk->getSignPackage(1);
                    return fun_client_errro(1, $clientInfo['client'], $clientInfo['erroeMessage'], $signPackage);
                }elseif($ali_or_wechat == 2){
                    return fun_client_errro(2, $clientInfo['client'], $clientInfo['erroeMessage']);
                }
            }
        }
        $types = $clientInfo['client']['types'];//设备类型
        //奥佳华按摩椅中转至奥佳华
        if($types == 5){
            $url = env('OGA_URL').'?clientHash='.$clientHash;
            echo header('Location:'.$url);
            exit;}
        $hasRefashWechat = 0;
        if( !session('open_id') )
        {
            $hasRefashWechat = 1;
            new_customer($ali_or_wechat);
        }
        $product = isset($clientInfo['product']) && !empty($clientInfo['product'])?$clientInfo['product']:[];
        $attribute = isset($clientInfo['attribute']) && !empty($clientInfo['attribute'])?$clientInfo['attribute']:[];
        if(isset($clientInfo['client']['kefu']) && $clientInfo['client']['kefu'])
        {
            session(['kefu'=>$clientInfo['client']['kefu']]);
        }
        //问候语
            $greeting = (isset($product['copywriter']) && $product['copywriter'])?$product['copywriter']:'欢迎使用osv服务，祝您生活愉快^_^';
            session(['copywriter'=>$greeting]);
        //问候语

        // 获取链接中是否有关注公众号领取优惠券跳转回回来的值
        $coupon_id = (int) $request->input('coupon_id', 0);
        // 获取优惠券的相关信息
        $open_id = session('open_id')?session('open_id'): 0;
//        $unionid = session('unionid')?session('unionid'):$openid;//订单统一性
        $user_coupon_res = [];
        if($ali_or_wechat == 1 || $ali_or_wechat == 2)
        {
            $user_coupon_res = get_wechat($clientInfo['client'],$open_id,$coupon_id,$ali_or_wechat);
        }
        //是否全屏展示
        $is_force = isset($user_coupon_res['is_force'])?$user_coupon_res['is_force']:3;
        //吸粉模式
        $mpType = isset($user_coupon_res['mpType'])?$user_coupon_res['mpType']:3;
        $wechat = isset($user_coupon_res['wechat']) ? $user_coupon_res['wechat'] : ''; // 是否设置吸粉公众号
        //支持同时吸粉和购买
        $hasWechat = 0;
        if($wechat)
        {
            //小程序吸粉组好跳转至小程序链接
            $service_type_info = isset($wechat['0']['service_type_info'])?$wechat['0']['service_type_info']:'';
            if($service_type_info)
            {
                $wechatId = $wechat['0']['id'];
                if($service_type_info ==8){
                    //个人号跳转第三方域名
                    $redirect_status = $request->input('status',0);
                    $is_jump = env('EDIRECT_JUMP');
                    if($is_jump == 1 && $redirect_status != 1){
                        return redirect(env('EDIRECT_URL') . '/coupon/storageinfo?clientHash=' . $clientHash . '&open_id=' . $open_id . '&wechat_id=' . $wechatId);
                    }
                }
                session(['wechat_id'=>$wechatId]);
                $hasWechat = 1;
                $str = $open_id.$clientHash.'wechat'.$wechatId;
                $token = substr(md5($str),0,16) ;
                $data = array(
                    'open_id'=>$open_id,
                    'clientHash'=>$clientHash,
                    'wechat_id'=>$wechatId,
                );
                $res = Crypt::encrypt($data);
                Redis::setex($token,600,$res);
                if($service_type_info==9){
                    $qrstr = $wechat['0']['qrcode_url'];
                    $wechat['0']['qrcode_url'] = $qrstr.$token;
                }
            }
        }
        if($hasWechat==0)
        {
            session_unset(session('wechat_id'));
        }else
        {
            if($hasRefashWechat != 1 && isset($user_coupon_res['shouldRefreshWechat']) && $user_coupon_res['shouldRefreshWechat'] == 1)
            {
                new_customer($ali_or_wechat);
            }
        }
        if($is_force == 1 && $wechat)//强吸全屏展示
        {
            if($mpType==1)
            {
                return view('h5.index.freecargo', ['wechat'=>$wechat]);
            }
            elseif($mpType==2)//优惠券强吸模式
            {
                return view('h5.index.couponfree', ['wechat'=>$wechat]);
            }
        }
        //是否可以购买
        $can_buy = isset($user_coupon_res['can_buy'])?$user_coupon_res['can_buy']:2;
        if($can_buy==0)//
        {
            return view('h5.index.nofree', []);
        }
        $user_coupon = isset($user_coupon_res['coupon']) ? $user_coupon_res['coupon'] : []; // 获取对应渠道商的个人优惠券
        $couponnum = count($user_coupon);//用户优惠券数量
        $coupon_info = isset($user_coupon_res['coupon_info']) ? $user_coupon_res['coupon_info']:''; // 领取优惠券时的优惠券详情
        $receive = isset($user_coupon_res['can_receive']) ? $user_coupon_res['can_receive']:'' ; //今日是否可以免费领取
        $coupon_type = (int) $request->input('coupon_type', 0);
        $info = array(
            'client'=>$clientInfo['client'],
            'type'=>$ali_or_wechat,
            'clientHash'=>$clientHash,
            'name'=>$clientInfo['client']['clientName'],
            'coupon'=>$user_coupon,
            'coupon_type'=>$coupon_type,
            'coupon_info'=>$coupon_info,
            'wechat'=>$wechat,
            'only'=>substr(md5($open_id),14),
        );
        if($ali_or_wechat == 1)
        {
            $jssdk= new JSSDK();
            $info['signPackage'] = $jssdk->getSignPackage(1);
        }
        $info['product'] = $product;
        $info['attribute'] = $attribute;
        $subtype = $clientInfo['client']['subtype'];//分类
        if ( $types == 2 ) { //货柜产品多口多商品多库存
            /*优惠券券后价格*/
            if($couponnum > 0 && !empty($clientInfo['goods']))
            {
                foreach($clientInfo['goods'] as $k=>$v)
                {
                    $price = $v['price'];
                    if($price>0)
                    {
                        for($i=0;$i<$couponnum;$i++)
                        {
                            $money = $user_coupon[$i]['money'];
                            if($money == 0 || $money >= $price){//免费券
                                $clientInfo['goods'][$k]['coupon_money'] = 0;
                                $clientInfo['goods'][$k]['coupon_code'] = $user_coupon[$i]['coupon_code'];
                                break;
                            }
                        }
                        if(!isset($clientInfo['goods'][$k]['coupon_code']))
                        {
                            $num = $couponnum>0?$couponnum-1:$couponnum;
                            $clientInfo['goods'][$k]['coupon_money'] = $price- $user_coupon[$num]['money'];
                            $clientInfo['goods'][$k]['coupon_code'] = $user_coupon[$num]['coupon_code'];
                        }
                    }
                }

            }
            $info['goods'] = $clientInfo['goods'];
            //多口
            if($subtype == 3 || $subtype == 2)
            {
                $info['client_types'] = 1;
            }else
            {
                $info['client_types'] = 2;
                if(  $subtype == 5 )//小王子
                {
                    $info['client_types'] = 3;
                }
                if($receive)
                {
                    $info['isfree'] = 1;
                    $info['is_receive'] = 1;
                }else
                {
                    $info['isfree'] = $mpType;
                }
            }
            return view('h5.index.cargo', $info);
        }
        elseif ( $types == 3 ) { //充电
            if($subtype == 1 )//4口
            {
                if($product['export'] > 4 )
                {
                    $product['export'] = 4;
                }
            }
            if($couponnum>0)
            {
                $price = $product['price'];
                if($price>0)
                {
                    for($i=0;$i<$couponnum;$i++)
                    {
                        $money = $user_coupon[$i]['money'];
                        if($money == 0 || $money >= $price){//免费券
                            $product['coupon_money'] = 0;
                            $product['coupon_code'] = $user_coupon[$i]['coupon_code'];
                            break;
                        }
                    }
                    if(!isset($product['coupon_code']))
                    {
                        $num = $couponnum>0?$couponnum-1:$couponnum;
                        $product['coupon_money'] = $price- $user_coupon[$num]['money'];
                        $product['coupon_code'] = $user_coupon[$num]['coupon_code'];
                    }
                }
            }
            $info['product'] = $product;
            return view('h5.index.charge',$info);
        }
        elseif ($types > 0 && $types <= 7) { //模板类型
            if($couponnum > 0 && $attribute)//是否有优惠券，有优惠券显示优惠后价格
            {
                foreach($attribute as $k=>$v)
                {
                    $price = $v['price'];
                    if($price>0)
                    {
                        for($i=0;$i<$couponnum;$i++)
                        {
                            $money = $user_coupon[$i]['money'];
                            if($money == 0 || $money >= $price){//免费券
                                $attribute[$k]['coupon_money'] = 0;
                                $attribute[$k]['coupon_code'] = $user_coupon[$i]['coupon_code'];
                                break;
                            }
                        }
                        if(!isset($attribute[$k]['coupon_code']))
                        {
                            $num = $couponnum>0?$couponnum-1:$couponnum;
                            $attribute[$k]['coupon_money'] = $price- $user_coupon[$num]['money'];
                            $attribute[$k]['coupon_code'] = $user_coupon[$num]['coupon_code'];
                        }
                    }
                }
                $info['attribute'] = $attribute;
            }
            if($types == 4)
            {
                return view('h5.index.warte',$info);
            }else
            {
                return view('h5.index.coin', $info);
            }
        }  else { //未知类型
            return fun_error_page($clientInfo['不支持的设备']);
        }
    }

    /*
     * 生成订单
     */

    public function wxaddorder(Request $request)
    {
        $paytype = fun_aliorwechat();
        if(!in_array($paytype,[1,2]))
        {
            $this->respon(0,'请在微信或支付宝客户端打开');
        }
        $clientHash = session('clientHash') ?session('clientHash'):'';
        if(empty($clientHash))
        {
            $this->respon(0,'设备信息异常!');
        }
        $openid = session('open_id')?session('open_id'):'';
        if(empty($openid))
        {
            $this->respon(0,'用户信息异常!');
        }
        $where = [];
        $where['client.clientHash'] = $clientHash;
        $clientInfo = get_clientInfo($clientHash);
        if( $clientInfo['errorCode'] != 0 )
        {
            $this->respon(0,$clientInfo['erroeMessage']);
        }

        $sku_id = $request->input('sku_id',0);
        if($sku_id <= 0)
        {
            $this->respon(0,'请选择服务!');
        }

        $unionid = session('unionid')?session('unionid'):$openid;//订单统一性
        $clientState = $this->clientState();
        $client = $clientInfo['client'];
        if($clientState['code'] == 200)
        {
            $this->respon(0,'设备使用中!');
        }
        elseif($clientState['code'] == 1)
        {
            $this->respon(0,'时间未到,请勿重复支付!');
        }
        elseif($client['types'] == 3)//充电的
        {
            if($clientState['code'] == 2)//充电
            {
                $this->respon(0,'有正在运行的订单');
            }
        }
        $coupon_code = $request->input('coupon','');//优惠券
        $coupon_valid = false;
        if ($coupon_code) {
            $CommonAction = new CommonAction();
            $coupon_valid = $CommonAction->couponChk(['coupon_code'=>$coupon_code], $client['uid'], $openid);
            if (!is_object($coupon_valid) && ($coupon_valid == 400 || $coupon_valid == 300 || $coupon_valid == false)) {
                if($coupon_valid == 300 ){
                    $this->respon( 0, '优惠券不在使用范围内' );
                }
                $this->respon( 0, '代金券无效' );
            }
        }
        $mouth = $request->input('mouth',0);//充电口
        $price = $request->input('price',0);//价格模板传价格
        $types = $client['types'];//设备类型
        $subtype = $client['subtype'];//分类
        ////
        if($types == 3)
        {
            if($mouth <= 0 )
            {
                $this->respon(0,'请选择充电口');
            }
        }
        ////
        $receive = '';
        if($types == 2)
        {
            $goods = get_client_product($client['clientHash'],2,$sku_id);
            $goodsInfo = '';
            if($goods['code']==1){
                $goodsInfo = $goods['data'][0];
            }
            if(!$goodsInfo) $this->respon(0, '商品不存在!');
            if( $goodsInfo['stock'] <= 0 )
            {
                $this->respon(0,'库存不足');
            }
            if($goodsInfo['conid']==$sku_id)
            {
                if($price!=$goodsInfo['price'])
                {
                    respons( 0, '价格有变动！' );
                }
            }
            if($subtype == 5)
            {
                $message = gateway_msg_new($client['clientHash']);
                if($message)
                {
                    $messageInfo = !empty($message['data'])?json_decode($message['data'],true):'';
                    if(!empty($messageInfo) )
                    {
                        if(isset($messageInfo['isStock']) && $messageInfo['isStock'] != 1)
                        {
                            $this->respon(0,'库存不足');
                        }
                    }
                }
            }
            $maximum_stock = (isset($goodsInfo['maximum_stock']) && $goodsInfo['maximum_stock'] > 0 )?$goodsInfo['maximum_stock']:60;
            if( $goodsInfo['stock'] == ceil(($maximum_stock*0.2)) || $goodsInfo['stock'] == 1 )
            {
                //库存不足推送消息
                $supplys = supply_partner_relation::getSupplyListByClient($client['clientid']);
                if($supplys)
                {
                    $branch_addres = '默认网点';
                    $branchInfo = branch::getInfo($client['branchId']);
                    if($branchInfo) $branch_addres = $branchInfo['branch_addres'];

                    $needNum = $maximum_stock - $goodsInfo['stock'] - 1;
                    $jssdk = new JSSDK();
                    $template_id = env('PUSH_TEMPLATE','kAWntfWaLCfpLMYWQrHggVsXVtMqVMhguQlcs86Q1Bw');
                    $remark = "请尽快补货";
                    $url = env('H5_URL','http://m.seller.osv.cn')."/supply/clientinfo?clientid=".$client['clientid'];
                    $clientName = $client['clientid'];
                    if($clientName != $client['clientName'])
                    {
                        $clientName = $clientName.'('.$client['clientName'].')';
                    }
                    $newdata=array(
                        'first'=>array('value'=>'设备缺货警告提醒','color'=>"#7167ce"),
                        'keyword1'=>array('value'=>$clientName,'color'=>'#7167ce'),
                        'keyword2'=>array('value'=>$branch_addres,'color'=>'#7167ce'),
                        'keyword3'=>array('value'=>$needNum,'color'=>'#7167ce'),
                        'keyword4'=>array('value'=>0,'color'=>'#7167ce'),
                        'remark'=>array('value'=>"{$remark}",'color'=>'#7167ce'),
                    );
                    foreach($supplys as $item)
                    {
                        if($item['openid'])
                        {
                            $jssdk->doSend($item['openid'],$template_id,$url,$newdata);
                        }
                    }
                }
            }
            if($subtype == 1 ||$subtype== 4)
            {
                $frecc = get_wechat($client,$openid);//是否可以免费领取
                $receive = isset($frecc['can_receive']) ? $frecc['can_receive']:'' ; //今日是否可以免费领取
            }
        }
        else
        {
            $checkPrice = 0;
            //充电类型取模板价格 其他取属性价格
            $pay_money = get_price($sku_id,$types);
            if($pay_money!==false)
            {
                if($price != $pay_money)
                {
                    $this->respon( 0, '价格有变动,请重新支付！' );
                }
                $checkPrice = 1;
            }
            $currentPrice = $price;
            if( $types == 3 )//充电
            {
                $proInfo = get_client_product($client['clientHash'],$types);
                if($proInfo['code']==1){
                    $proInfo = $proInfo['data'];
                    if (!$checkPrice)
                    {
                        $currentPrice = $proInfo["price"];
                    }
                }else{
                    $this->respon( 0, '该服务暂不可用!' );
                }
            }else
            {
                $attributes = channel_attributes::getProductAttr($sku_id);
                if (empty($attributes)){
                    $this->respon( 0, '该服务暂不可用' );
                }
                if (!$checkPrice)
                {
                    $currentPrice = $attributes["price"];
                }
            }
            if($currentPrice!=$price){
                $this->respon( 0, '价格有变动,请重新支付' );
            }
        }
        $opdata['op'] = 'state';
        $opdata['data'] = array();
        $message_id = gateway_send_message($opdata['op'],$opdata['data'],trim($client['clientHash']));//给设备发送消息验证是否可用
        if (empty($message_id)) respon(0,'当前设备还没准备好');
        for ($x=0; $x<=9; $x++)
        {
            sleep(1); //usleep(500);
            $messnum = geteway_message_num($message_id);//获取消息条数
            if( $messnum >= 2) break;
        }
        if( $x >= 9 )
        {
            Storage::disk('h5')->append('overtime.log', '超时时间time:'.date('Y-m-d H:i:s').'-----------'.$client['clientid']);
            respon(0,'网络拥堵，请稍后再试');
        }else{
            if( $types == 2 && $subtype == 1)
            {
                $newgoods = get_client_product($client['clientHash'],2,$sku_id);
                $newgoodsInfo = '';
                if($newgoods['code']==1){
                    $newgoodsInfo = $newgoods['data'][0];
                }
                if(!$newgoodsInfo) $this->respon(0, '商品不存在!');
                if( $newgoodsInfo['stock'] <= 0 )
                {
                    $this->respon(0,'库存不足');
                }
            }
//            if($types == 6)
//            {
//                $message = gateway_msg_new($client['clientHash']);
//                if($message)
//                {
//                    $messageInfo = !empty($message['data'])?json_decode($message['data'],true):'';
//                }
//                if( !isset($messageInfo['enabled']) || $messageInfo['enabled'] != 1 ) respon(0,'设备运行中');
//            }
        }
        try
        {
            DB::beginTransaction();
            $order = array(
                'order_number'=>date( 'YmdHis',time() ).rand(1111,9999),
                'merchant_id'=>( isset($client['merchant_id']) && $client['merchant_id'] > 0 )? $client['merchant_id'] : 1,
                'pay_money'=>$price,
                'clientid'=>$client['clientid'],
                'sku_id'=>$sku_id,
                'is_checked'=>0,
                'dev_channel_id'=>$client['uid'],
                'pay_id'=>$paytype,//支付方式
                'branchId'=>$client['branchId'],
                'add_time'=>time()
            );
            $sku_coupon_code = 0;
            $sku_coupon_money = 0;
            $is_coupon = 0;
            if($receive)//免费领取
            {
                $order['order_status'] = 6;
                $order['pay_money'] = 0;
            }
            else //不能免费领取使用优惠券
            {
                $coupon_price = $this->__handleMoney($coupon_valid, $price);
                $order['pay_money'] = $coupon_price;
                $is_coupon = 1;
            }
            $orderId = order_info::insertGetId($order);
            if( !$orderId )
            {
                throw new \Exception( '网络错误,稍后重试!' );
            }
            if($is_coupon == 1)
            {
                // xiaobin 0803 生成订单之前判断代金券是否存在，并处理价格
                if (isset($coupon_valid) && $coupon_valid) {
                    $sku_coupon_code = $coupon_valid->coupon_code;
                    $sku_coupon_money = $coupon_valid->money;
                    // xiaobin 0803 订单生成之后判断是否使用代金券，如果使用了代金券 修改代金券的状态，并记录使用代金券的日志
                    $sel_coupon = [
                        'openid' => $coupon_valid->openid,
                        'coupon_code' => $coupon_code,
                        'channel_id' => $client['uid'],
                        'is_valid' => 1,
                    ];
                    $coupon_edit_type = customer_coupon::where($sel_coupon)->update(['type'=>2,'use_at'=>date('Y-m-d H:i:s')]);
                    if (!$coupon_edit_type) {
                        throw new \Exception( '修改代金券状态错误' );
                    }
                    // 修改了代金券的状态之后记录代金券使用日志
                    $use_coupon = [
                        'openid' => $coupon_valid->openid,
                        'coupon_code' => $coupon_valid->coupon_code,
                        'order_id' => $orderId,
                        'use_desc' => '下单使用代金券',
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    $add_use_coupon = coupon_use_log::insert($use_coupon);
                    if (!$add_use_coupon) {
                        throw new \Exception( '添加代金券使用日志错误' );
                    }
                }
            }


            $skuOrder = array(
                'order_id'=>$orderId,
                'coupon_code'=>$sku_coupon_code,
                'coupon_money'=>$sku_coupon_money,
                'sku_img'=>'',
                'sku_thump'=>'',
                'norms_name'=>'暂无',
                'openid'=>$unionid,
                'platform_source'=>($paytype==1)?1:0,
                'goods_id'=>0,
                'add_time'=>time(),
            );
            $attribute_name = '充电';
            $worketd = 0;
            $price = 0;
            $number = 0;
            if( $types== 2 )//货柜产品
            {
                $attribute_name = (isset($goodsInfo['goods_name']) && !empty($goodsInfo['goods_name']) ) ? $goodsInfo['goods_name'] :'未知';
                $price = ( isset($goodsInfo['price']) && $goodsInfo['price'] > 0) ? $goodsInfo['price'] :'0';
                $number = isset($goodsInfo['op_export']) && $goodsInfo['op_export'] >0?$goodsInfo['op_export']:(isset($goodsInfo['export']) && !empty($goodsInfo['export']) ? $goodsInfo['export'] :'');//商品对应货柜的口
                $skuOrder['sku_img'] = ( isset($goodsInfo['goods_img']) && !empty($goodsInfo['goods_img']) ) ? $goodsInfo['goods_img'] :'';//商品图
                $skuOrder['goods_id'] = ( isset($goodsInfo['goods_id']) && !empty($goodsInfo['goods_id']) ) ? $goodsInfo['goods_id'] :0;//商品图
            }elseif($types == 3 )//充电产品
            {
                //$skuOrder['worketd'] = env('CHARGE_TIME',1800) ;//30分钟
                $worketd = ( isset($proInfo['duration']) && $proInfo['duration'] > 0 ) ? $proInfo['duration']*60 :1800;//30分钟
                $price = ( isset($proInfo['price']) && $proInfo['price'] > 0) ? $proInfo['price'] :'0';
                $number = $mouth;//第几个口
            }else
            {
                $attribute_name = (isset($attributes['attributes_name']) && !empty($attributes['attributes_name']) ) ? $attributes['attributes_name'] :'暂无';
                $price = (isset($attributes['price']) && $attributes['price'] > 0) ? $attributes['price'] :'0';
                if($types == 4)
                {
                    $worketd = isset($attributes['duration']) && $attributes['duration'] > 0 ? $attributes['duration'] : 30;//出水时长
                    $number = (isset($attributes['required']) && ($attributes['required'] > 0) ) ? ($attributes['required'] - 1) :0;
                }else
                {

                    $worketd = isset($attributes['duration']) && $attributes['duration'] > 0 && ( isset( $attributes['mode'] ) &&  $attributes['mode'] == 2 ) ? $attributes['duration']*60 : 0;
                    if($types == 7)
                    {
                        $worketd = (isset($attributes['duration']) && $attributes['duration'] > 0)  ? $attributes['duration']*60 : 60;
                    }
                    $number = (isset($attributes['required']) && !empty($attributes['required']) ) ? $attributes['required'] :'';
                }
            }
            $skuOrder['attribute_name'] = $attribute_name;
            $skuOrder['worketd'] = $worketd;
            $skuOrder['price'] = $price;
            $skuOrder['number'] = $number;

            if(trim($client['sub_mch_id']))
            {
                $skuOrder['sub_mch_id'] = trim($client['sub_mch_id']);
            }
            /*合作者伙伴是否分成-----------start*/
            $channel_partner = channel_partner::getInfoById($client['channel_partner_id']);
            if($channel_partner)
            {
                $skuOrder['partner_id'] = $channel_partner['partner_id'];
                $rate = $channel_partner['rate'];
                if($rate>100)
                {
                    $rate = 100;
                }elseif($rate < 0 )
                {
                    $rate = 0;
                }
                $skuOrder['rate'] = $rate;
            }
            //平台和渠道商分成比例
            $channel_rate = isset($client['income']) ? $client['income'] : 100;
            if($channel_rate <0 || $channel_rate > 100) $channel_rate = 100;
            $skuOrder['channel_rate']= $channel_rate;
            /*合作者伙伴是否分成-----------end*/
            $orderSkuId = order_sku::insertGetId($skuOrder);
            if( !$orderSkuId )
            {
                throw new \Exception( '网络错误,稍后重试!!' );
            }
            /*货柜产品修改商品库存*/
            if( $types == 2 && $subtype>0 && $subtype != 5 )
            {
                $stock = container_goods_stock::where(['client_id'=>$client['clientid'],'id'=>$sku_id])->decrement('stock');
                if( !$stock )
                {
                    throw new \Exception( '修改库存失败!' );
                }
            }

            if($order['pay_money'] <= 0)//价格为0或免费领取直接发送指令启动设备
            {
                $orderData = [];
                $orderData['trade_no'] = time().mt_rand(10000,99999);
                $orderData['order_number'] = $order['order_number'];
                if($receive)
                {
                    $code = 300;
                    $otype = 4;//免费领取
                    $record = array(
                        'channel_id' => $client['uid'],
                        'order_id' => $orderId,
                        'clientHash' => $client['clientHash'],
                        'openid' => $openid,
                        'created_at' => date('Y-m-d H:i:s', time())
                    );
                    $record_id = receive_record::insertGetId($record);
                    if (!$record_id) {
                        throw new \Exception( '领取失败!' );
                    }
                }else
                {
                    $otype = 3;
                    $code = 200;
                }
                $res = $this->handleMessage($orderData,$otype);//免费的
                DB::commit();
                $this->respon($code,"支付成功");
            }
            else//微信支付
            {
                if($paytype == 1)//微信
                {
                    $body_cont = isset($attributes['attributes_name']) ? 'OSV-'.$attributes['attributes_name'] : 'OSV-零米微生活';
                    $attach = array(
                        'order'=>$order['order_number'],
                        'clientId'=>$client['clientid'],
                        'skuId'=>$sku_id,
                    );
                    $key = env('WX_KEY');
                    $rand = md5(time() . mt_rand(0,1000));
                    $param["appid"] = env('WX_APPID');
                    $param["openid"] = $openid;
                    $param["mch_id"] = env('WX_MCHID');
                    $param["nonce_str"] = "$rand";
                    $param["attach"] = json_encode($attach);
                    $param["body"] = $body_cont;
                    $param["out_trade_no"] = $order['order_number']; //订单单号
                    $param["total_fee"] = $order['pay_money']*100;//支付金额
                    $param["spbill_create_ip"] = $_SERVER["REMOTE_ADDR"];
                    $param["notify_url"] = $_SERVER['HTTP_HOST']."/index/wxnotify";//回调
                    $param["trade_type"] = "JSAPI";
                    $signStr = 'appid='.$param["appid"]."&attach=".$param['attach']."&body=".$param["body"]."&mch_id=".$param["mch_id"]."&nonce_str=".$param["nonce_str"]."&notify_url=".$param["notify_url"]."&openid=".$param["openid"]."&out_trade_no=".$param["out_trade_no"]."&spbill_create_ip=".$param["spbill_create_ip"]."&total_fee=".$param["total_fee"]."&trade_type=".$param["trade_type"];
                    if(trim($client['sub_mch_id']))//子商户支付模式
                    {
                        $param["mch_id"] = env('WX_MCHID_KING');
                        $param["sub_mch_id"] = trim($client['sub_mch_id']);
                        $signStr = 'appid='.$param["appid"]."&attach=".$param['attach']."&body=".$param["body"]."&mch_id=".$param["mch_id"]."&nonce_str=".$param["nonce_str"]."&notify_url=".$param["notify_url"]."&openid=".$param["openid"]."&out_trade_no=".$param["out_trade_no"]."&spbill_create_ip=".$param["spbill_create_ip"]."&sub_mch_id=".$param["sub_mch_id"]."&total_fee=".$param["total_fee"]."&trade_type=".$param["trade_type"];
                    }
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
                        DB::commit();
                        $this->respon( 1, $result );
                    }else{
                        throw new \Exception( '支付失败!' );
                    }
                }
                elseif($paytype==2)//支付宝
                {
                    $AliSSDK = new Alijssdk();
                    $pay = array(
                        'subject'=>'OSV快付',
                        'out_trade_no'=>$order['order_number'],
                        'total_amount'=>$order['pay_money'],
                        'product_code'=>'QUICK_WAP_PAY',
                        'return_url'=>'https://'.env('API_DOMAIN').'/index/share',
                        'notify_url'=>'https://'.env('API_DOMAIN').'/index/alinotify'
                    );
                    $formdata = $AliSSDK->alipay($pay);
                    DB::commit();
                    $this->respon(1688,$formdata);
                }else
                {
                    throw new \Exception( '支付失败!!' );
                }
            }
        }catch (\Exception $e)
        {
            DB::rollback();
            $this->respon(0,$e->getMessage());
            return false;
        }
    }

    public function __handleMoney($coupon_valid, $pay_money)
    {
        if (isset($coupon_valid) && $coupon_valid) {
            $sku_coupon_money = $coupon_valid->money;
            if ($sku_coupon_money > $pay_money || $sku_coupon_money == 0) {//优惠金额大于支付或者优惠券为免费券
                $pay_money = 0;
            } else {
                $pay_money = (float) $pay_money - (float)$sku_coupon_money;
            }
        }
        return $pay_money;
    }

    /**
     * @param $message string()  json格式
     * @return bool|int
     * 接收消息回调处理物理投币
     */
    public function notify_machine_coin($message)
    {
        if(empty($message))
        {
            return false;
        }
        $message = json_decode($message,true);
        $clientHash = isset($message['clientHash'])?$message['clientHash']:'';
        if(empty($clientHash))
        {
            return 1;
        }
//        if(!in_array($clientHash,['040281817f538cbf4162e3a8b903a6ba'])) {
//            return 4;
//        }
        $response = isset($message['response'])?$message['response']:(isset($message['data'])?$message['data']:'');
        if(empty($response))
        {
            return 2;
        }
        return $this->machine_coin($clientHash,$response);
    }
    public function machine_coin($clientHash,$response)
    {
        if(empty($clientHash))
        {
            return 1;
        }
        if(empty($response))
        {
            return 2;
        }
        $coin_number = isset($response['coin_number_machine'])?$response['coin_number_machine']:(isset($response['coin_number'])?$response['coin_number']:-1);
        if($coin_number < 0)
        {
            return 3;
        }
        $key = 'machine'.$clientHash;
        $clientData = Redis::get($key);
        if(!$clientData)
        {
            $where = [];
            $where['clientHash'] = $clientHash;
            $where['ds'] = NULL;
            $clientData = client::select('coin_num','clientid','uid','branchId','types','subtype')->where($where)->first();
            if($clientData)
            {
                $clientData = json_decode($clientData,true);
                Redis::setex($key,7200,json_encode($clientData));
            }
        }
        else{
            $clientData = json_decode($clientData,true);
        }
        $shouldUpdateCoin = ($clientData['types'] ==2 && $clientData['subtype'] == 1);
        if(!$shouldUpdateCoin)
        {
            return 4;
        }

        $clientCoinNum = isset($clientData['coin_num']) ? (int)$clientData['coin_num'] : 0;
        if($clientCoinNum == $coin_number)
        {
            return 0;
        }
        $addCoinNum = $coin_number - $clientCoinNum;
        $is_log = false;
        if($clientCoinNum == 0 || $addCoinNum >=5)//本身就是0
        {
            $is_log = true;
        }
        $stock = 0;
        try {
            DB::beginTransaction();
            //更新设备币数
            client::where(['clientHash'=>$clientHash])->update(['coin_num'=>$coin_number]);
            //更新缓存中投币数
            $clientData['coin_num'] = $coin_number;
            $time = Redis::ttl($key);
            Redis::setex($key,$time,json_encode($clientData));
            if($addCoinNum > 0 && $clientData['uid'] >0 )
            {
                //查询设备绑定商品信息
                $list = get_client_product($clientHash,2);
                if(isset($list['code']) && $list['code']==1)
                {
                    $goods=$list['data'];
                    $firstGoods = $goods['0'];
                    $stock = $firstGoods['stock'];
                    $conid = $firstGoods['conid'];
                    //剩余库存大于投币数 更新库存值,
                    if($addCoinNum >= (int)$stock)
                    {
                        container_goods_stock::where(['id'=>$conid])->update(['stock'=>0]);
                        $is_log = true;
                    }
                    else
                    {
                        container_goods_stock::where(['id'=>$conid])->decrement('stock',$addCoinNum);
                    }
                    //开始插入订单
                    $order = array(
                        'order_number'=>date( 'YmdHis',time() ).rand(1000,9999),
                        'clientid'=>$clientData['clientid'],
                        'developer_id'=>0,
                        'branchId'=>$clientData['branchId'],
                        'dev_channel_id'=>$clientData['uid'],
                        'sku_id'=>$conid,
                        'order_status'=>2,
                        'operation_is_settle'=>1,
                        'is_settle'=>1,
                        'pay_id'=>1,
                        'pay_money'=>0,
                        'is_valid'=>1,//支付方式
                        'pay_time'=>time(),
                        'add_time'=>time(),
                        'is_checked'=>1,
                        'is_machine'=>1,
                    );
                    $orderId = order_info::insertGetId($order);
                    if(!$orderId)
                    {
                        throw new \Exception('插入订单失败!');
                    }
                    //开始插入订单扩展
                    $price = $firstGoods['price'];
                    $sku_img = isset($firstGoods['goods_img']) ? $firstGoods['goods_img'] :'';//商品图
                    $attribute_name = $firstGoods['goods_name'];
                    $goods_id = $firstGoods['goods_id'];

                    $skuorder = [];
                    $skuorder['order_id'] = $orderId;
                    $skuorder['price'] = $price;
                    $skuorder['coupon_code'] = 0;
                    $skuorder['coupon_money'] = 0;
                    $skuorder['worketd'] = 0;
                    $skuorder['number'] = $addCoinNum;
                    $skuorder['sku_img'] = $sku_img;
                    $skuorder['attribute_name'] = $attribute_name;
                    $skuorder['norms_name'] = '暂无';
                    $skuorder['add_time'] = time();
                    $skuorder['goods_id'] = $goods_id;
                    $res = order_sku::insert($skuorder);
                    if(!$res)
                    {
                        throw new \Exception('插入订单扩展失败!');
                    }
                }
            }
            DB::commit();
            if($is_log)
            {
                Storage::disk('h5')->append('client_coin_number.log','时间：'.date('Y-m-d H:i:s').'clientid:'.$clientData['clientid'].'old_num:'.$clientCoinNum.'new_num:'.$coin_number.'stock:'.$stock."\n");
            }
        } catch (\Exception $ex) {
            DB::rollback();
            return $ex->getMessage().'=='.$ex->getLine();
        }
    }

    /*
    * 微信回调
    * */
    public function wxnotify()
    {
        $xml = file_get_contents("php://input");
        if(!$xml)//回调数据为空
        {
            echo '123';
            exit;
        }
        $data = Util::xmlToArray($xml);
        if($data && $data['result_code']=='SUCCESS')
        {
//            Storage::disk('h5')->append('wechatnotify.log','调用记录:'.json_encode($data).' 时间：'.date('Y-m-d H:i:s',time()));
            $attach = $data['attach'];
            $attach = json_decode($attach,true);
            //微信支付回执号
            $attach['transaction_id'] = $data['transaction_id'];
            $res = $this->handleMessage($attach,1);
            if($res)
            {
                return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
                exit;
            }else
            {
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
        if( empty($data) || count($data)<=0 )
        {
            Storage::disk('h5')->append('alinotify_null.log','回掉数据:null 时间:'.date('Y-m-d H:i:s',time()));
        }
        if( $data && ( $data['trade_status'] == 'TRADE_SUCCESS' || $data['trade_status'] == 'TRADE_FINISHED' ) )
        {
            $aliInfo = new Alijssdk();
            $sign = $aliInfo->verifysign($data);
            if( !$sign )
            {
                Storage::disk('h5')->append('notify_order_fait.log', '回调记录3——签名错误:'.date('Y-m-d H:i:s').'data:'.json_encode($data));
                return 'FAIT';
            }
            $res = $this->handleMessage($data,2);
            return 'success';
            exit;
        }else{
            Storage::disk('h5')->append('alinotify_fait.log', '回调记录2——回掉数据:'.date('Y-m-d H:i:s').'data:'.json_encode($data));
            echo 'FAIT';
            exit;
        }
    }

    /*支付完成根据类型发送不同消息*/
    private function handleMessage($data,$type=1)
    {
        if(count($data)<0)
        {
            return '';
        }
        if($type <0 || $type>4)
        {
            return '';
        }
        try{
            DB::beginTransaction();
            if($type == 1)
            {
                $order_number = $data['order'];
                $trade_no = $data['transaction_id'];
            }elseif($type == 2)
            {
                $order_number = $data['out_trade_no'];
                $trade_no = $data['trade_no'];
            }else
            {
                $order_number = $data['order_number'];
                $trade_no = $data['trade_no'];
            }
            $orderInfo = order_info::getInfoByOrderNum($order_number);
            if( empty($orderInfo) )
            {
                throw new \Exception( date('Y-m-d H:i:s').'订单不存在!'.'订单号:'.$order_number);
            }
            if($orderInfo['is_valid'] != 0)
            {
                throw new \Exception( date('Y-m-d H:i:s').'订单已处理过!'.'订单号:'.$order_number);
            }
            $client = client::getClientForAPI(array('clientid'=>$orderInfo['clientid']));
            if(!$client)
            {
                throw new \Exception( date('Y-m-d H:i:s').'设备不存在'.'订单号:'.$order_number);
            }
            $client = json_decode($client,true);
            $order_status = $orderInfo['order_status'];
            $is_return = $orderInfo['is_return'];
            // 添加订单是否退款判断
            if ( $order_status == 4 || $is_return == 1)
            {
                throw new \Exception( date('Y-m-d H:i:s').'订单已退款！'.'订单号:'.$order_number.'order_status:'.$order_status.'is_return:'.$is_return );
            }
            if( $order_status== 2 || $order_status == 5)
            {
                throw new \Exception( date('Y-m-d H:i:s').'订单已完成!'.'订单号:'.$order_number);
            }else{
                $datas = array(
                    'order_status'=>2,
                    'trade_no'=>$trade_no,
                    'pay_time'=>time(),
                    'is_valid'=>1
                );
                if( $client['types'] == 2 )//货柜类型查询商品状态
                {
                    $gwhere = [];
                    $gwhere['goods_id'] = $orderInfo['goods_id'];
                    $gwhere['client_id'] = $orderInfo['clientid'];
                    $goodsInfo = container_goods_stock::getInfo($gwhere);
                    $anomaly_code = 100;
                    if( empty( $goodsInfo ) )
                    {
                        $anomaly_code = 102;//订单支付成功商品删除
                    }elseif( !isset( $goodsInfo['stock'] ) || ( $goodsInfo['stock'] < 0 ) )
                    {
                        $anomaly_code = 103;//订单支付成功商品无库存
                    }elseif($orderInfo['order_status']== 3)
                    {
                        $anomaly_code = 104;//订单超时取消支付成功
                    }
                    if($anomaly_code != 100)
                    {
                        $datas['order_status'] = 5;
                        $datas['isRefunds'] = 1;
                        $datas['anomaly_code'] = $anomaly_code;
                        $datas['is_checked'] = 1;
                        order_info::editByOrderNumber($order_number,$datas);
                        DB::commit();
                        return;
                    }
                }
                if( $orderInfo['pay_money'] > 0 ){
                    $redis_data = [
                        'pay_money' => $orderInfo['pay_money'],
                        'order_number' => $orderInfo['order_number'],
                        'pay_id' => $orderInfo['pay_id']
                    ];
                    Redis::lpush('tom_order_log_list', json_encode($redis_data));
                    $red_list = Redis::llen('tom_order_log_list');
                    if ($red_list == 1) {
                        //
                        $this->addmoneylog();
                    }
                }
                if($type == 4)//吸粉免费领取的
                {
                    $datas['order_status'] = 6;
                }
                $id = order_info::editByOrderNumber($order_number,$datas);
                if(!$id)
                {
                    throw new \Exception( date('Y-m-d H:i:s').'编辑订单失败'.'订单号:'.$order_number);
                }
            }
            //发送消息  参数不确定
            $types = $client['types'];
            $subtype = $client['subtype'];
            $opdata = [];
            $opdata['op'] = 'state';
            $opdata['data'] = array('defaultData'=>'1');
            //发送消息
            if( $types==1 )//投币产品
            {
                $opdata['op'] = 'coin';
                $opdata['data'] = array('num'=>$orderInfo['number']);
            }
            elseif($types == 3)//充电设备
            {
                $opdata['op'] = 'run_time';
                $opdata['data'] = array(
                    'seconds'=>$orderInfo['worketd']
                );
                if( $orderInfo['number'] > 1 )
                {
                    $opdata['port']=$orderInfo['number'];
                }
                if( $subtype== 2 )
                {
                    $aaa = $orderInfo['number']-1;
                    if($client['clientid'] == 103055 )
                    {
                        if($orderInfo['number'] == 12)
                        {
                            $aaa = 16;
                        }
                    }
                    $bbb = $orderInfo['worketd'];
                    $opdata['op'] = 'transmi';
                    $opdata['data'] = array(
                        'data'=>"[0,".$aaa.",".$bbb."]"
                    );
                }
            }
            elseif($types == 4 )//售水机
            {
                $opdata['op'] = 'run_time';
                $opdata['data'] = array(
                    'port'=>$orderInfo['number'],
                    'seconds'=>$orderInfo['worketd']
                );
            }
            elseif($types ==2)//货柜
            {
                /*默认OP多口*/
                $opdata['op'] = 'unlock';
                $opdata['data'] = array(
                    'port'=>$orderInfo['number'],
                    'time'=>500
                );
                if($subtype == '1' || $subtype == '5')//单口货柜OP
                {
                    $opdata['op'] = 'coin';
                    $opdata['data'] = array(
                        'num'=>'1'
                    );
                }elseif($subtype == 2)
                {
                    $opdata['op'] = 'unlock';
                    $opdata['data'] = array(
                        'port'=>$orderInfo['number'],
                        'time'=>500
                    );
                    $liqiaomo_list = [ '31a3f1b0945029d2080130085c650a9c',
                        '2145fb18e444acabac5a5eb086107156',
                        'd73c40b0440bf2b18258c131b969972c',
                        '0f9d4fe4d04cd7735ec75dd91d455032',
                        '8972998871c1efb10e1890b1d6ec656f',
                        '829dc7199a3330947a5c9f0581ffec9b',
                        'dff23e20cedb45cc330b96c81ad4021e',
                        '155d7ae2585045e8859eeb4397907c2d',
                        '2e8bd1f7ef06dd73118d9b7aac03bf11',
                        '6ed257d757782b6a64f39df5c4da5d1d',
                        '26460eadce3d7e13713e74b6e25afcc1'
                    ];
                    if(in_array($client['clientHash'],$liqiaomo_list))
                    {
                        $aaa = $orderInfo['number']-1;
                        $bbb = 1;
                        $opdata['op'] = 'transmi';
                        $opdata['data'] = array(
                            'data'=>"[0,".$aaa.",".$bbb."]"
                        );
                    }
                }elseif($subtype == '4')
                {
                    $opdata['op'] = 'turnon';
                    $opdata['data'] = array(
                        'time'=>10000
                    );
                }
            }
            elseif($types == 6)
            {
                $pluseType = $orderInfo['number']==1?1:0;
                $keep_time = 50;
                $attributes = channel_attributes::leftjoin('channel_product','channel_attributes.channel_product_id','=','channel_product.id')
                    ->where(['channel_attributes.id'=>$orderInfo['sku_id'],'channel_attributes.is_valid'=>1])
                    ->first(['channel_attributes.keep_time']);
                if($attributes)
                {
                    $keep_time = $attributes['keep_time'];
                }
                $opdata['op'] = 'pluse';
                $opdata['data'] = array(
                    'type'=>$pluseType,
                    'time'=>$keep_time
                );
            }
            elseif($types == 7)
            {
                $opdata['op'] = 'run_time';
                $opdata['data'] = array(
                    'seconds'=>$orderInfo['worketd']
                );
            }
            $messageId = gateway_send_message($opdata['op'],$opdata['data'],trim($client['clientHash']));//给设备发送消息
            if(empty($messageId))
            {
                throw new \Exception( date('Y-m-d H:i:s').'发送消息失败!'.'订单号:'.$order_number );
            }
            order_info::editByOrderNumber($orderInfo['order_number'],['message_id'=>$messageId]);//消息发送成功
            $userinfo = customer::where(['openid'=>$orderInfo['openid']])->orWhere(['unionid'=>$orderInfo['openid']])->first();
            if($userinfo)
            {
                channel_customer::add($client['uid'],$userinfo->id);
            }
            $list = [];
            $list['order_number'] = $order_number;
            $list['goods_id'] = $orderInfo['goods_id'];
            $list['clientHash'] = $client['clientHash'];
            $list['pay_id'] = $orderInfo['pay_id'];
            $list['pay_money'] = $orderInfo['pay_money'];
            $list['openid'] = $orderInfo['openid'];
            $list['coupon_code'] = $orderInfo['coupon_code'];
            $list['pay_time'] = time();
            if(isset($orderInfo['sub_mch_id']) && $orderInfo['sub_mch_id'] && $type = 1)
            {
                $list['sub_mch_id'] = $orderInfo['sub_mch_id'];
            }
            $list['pay_time'] = time();
            $a1 = ['message_id'=>$messageId,"orderInfo"=>$list];
            Redis::lPush('shi_order-message-id',json_encode($a1));
            DB::commit();
            return true;
        }catch (\Exception $e){
            DB::rollback();
            if($type != 2)
            {
                Storage::disk('h5')->append('wechatnotify_fait.log', $e->getMessage());
            }else
            {
                Storage::disk('h5')->append('notify_order_ali.log', $e->getMessage());
            }
            return false;
            //return "<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[error]]></return_msg></xml>";
            exit;
        }
    }
    /**
     * 处理并发回调时 插入流水异常情况
     * @param $clientInfo
     * @param int $type
     * @return array
     */
    public function addmoneylog()
    {
        $list = Redis::llen('tom_order_log_list');
        if (!$list) return '';
        if ($list) {
            $datas = Redis::rpop('tom_order_log_list');
            Redis::rpush('tom_order_log_list', $datas);
            /*OSV账户添加资金流水*/
            $operate = getOperate();//运营财务账号
            if( !empty($operate) )
            {
                $orderInfo = json_decode($datas, 1);
                $after_money = isset($operate['money'])?$operate['money']:0;
                $after_money = $after_money + $orderInfo['pay_money'];
                $capital = array(
                    'user_id'=>0,
                    'user_type'=>0,
                    'type'=>1,
                    'pay_type'=>$orderInfo['pay_id'],
                    'before_money'=>isset($operate['money'])?$operate['money']:0,//变更之前金额(账户余额)
                    'change_money'=>$orderInfo['pay_money'],//此次变更金额
                    'after_money'=>$after_money,//变更后金额
                    'status'=>2,//用户消费(平台收入)
                    'order_number'=>$orderInfo['order_number'],
                    'created_at'=>date('Y-m-d H:i:s',time()),
                );
                money_record::insertGetId($capital);
                operates::where( ['email'=>'osv@e7124.com'] )->update( ['money'=>$after_money] );
            }
            Redis::rpop('tom_order_log_list');
            $this->addmoneylog();
        }

    }
    /**
     * 返回倒计时信息
     */
    public function getClientStatus(Request $request)
    {
        $clientState = $this->clientState();
        if($clientState['code'] == 200)
        {
            $this->respon(0,'设备使用中!');
        }elseif($clientState['code'] == 2)//充电
        {
            $this->respon(1,$clientState['data']);
        }elseif($clientState['code'] == 3)
        {
            $this->respon(0,$clientState['data']);
        }elseif($clientState['code'] == 1)
        {
            $this->respon(1,$clientState['restTime']);
        }else
        {
            $this->respon(300,'');
        }
    }
    /**
     * 设备状态(是否运行)
     */
    private function clientState()
    {
        $clientHash = session('clientHash') ?session('clientHash'):'';
        if(empty($clientHash))
        {
            return array('code'=>0,'restTime'=>0);
        }
        $openid = session('open_id')?session('open_id'):'';
        if(empty($openid))
        {
            return array('code'=>0,'restTime'=>0);
        }
        $client = client::getClientForAPI(['clientHash'=>$clientHash]);
        $where = [];
        $where['order_info.clientid'] = $client['clientid'];
        $where['order_info.order_status'] = 2;
        $orderInfo = order_sku::getOrderInfo($where);
        if( $orderInfo )
        {
            $Time = (int)$orderInfo['pay_time'] + (int)$orderInfo['worketd'];
            $workTime = $Time - time();
            if( $workTime > 0 )
            {
                $unionid = session('unionid')?session('unionid'):$openid;//订单统一性
                switch ($client['types'])
                {
                    case 1://投币
                        $map = array(
                            'channel_attributes.id'=>$orderInfo['sku_id']
                        );
                        break;
                    case 3://充电
                        $endTime = time()-7200;
                        $orderInfo = order_sku::select('order_sku.worketd','order_sku.number','order_sku.attribute_name','order_sku.norms_name','order_sku.openid','client.clientName','order_info.pay_money','order_info.pay_time','order_info.sku_id','order_info.order_number','order_info.clientid')
                            ->leftjoin('order_info', function($join)
                            {
                                $join->on('order_info.id', '=', 'order_sku.order_id');
                            })
                            ->leftjoin('client', function($join)
                            {
                                $join->on('order_info.clientid', '=', 'client.clientid');
                            })
                            ->where($where)
                            ->where('order_info.pay_time','>=',$endTime)
                            ->orderby('order_info.id','desc')
                            ->get()
                            ->toArray();
                            $arr1 = [];
                            for ($i=1;$i<=4;$i++)
                            {
                                foreach( $orderInfo as $k=>$v )
                                {
                                    $status = 0;
                                    if( ( $v['pay_time'] + $v['worketd'] ) > time() )
                                    {
                                        if( $v['openid'] ==  $unionid )//本用户使用中
                                        {
                                            $time = ( $v['pay_time'] + $v['worketd'] ) - time();
                                            return array('code'=>2,'data'=>['time'=>$time,'port'=>$v['number']]);
                                        }else//非本用户使用
                                        {
                                            $arr1[] = $v['number'];
                                            continue;
                                        }
                                    }
                                }
                            }
                            return array('code'=>3,'data'=>$arr1);
                        break;
                }
                if($orderInfo['openid'] == $unionid )
                {
                    return array('code'=>1,'restTime'=>$workTime);
                }else
                {
                    return array('code'=>200,'msg'=>'设备使用中!');
                }
            }
        }
        return array('code'=>0,'restTime'=>NULL);
    }
    public function cargosuccess()
    {
        $copywriter = session('copywriter')?session('copywriter'):'欢迎使用osv服务，祝您生活愉快^_^';
        $tel = session('kefu')?session('kefu'):'';
        $clientHash = session('clientHash')?session('clientHash'):'';
        $data = [];
        $data['copywriter'] = $copywriter;
        $data['tel'] = $tel;
        $data['clientHash'] = $clientHash;
        return view('h5.index.cargosuccess',$data);
    }
    /*其他品类支付成功中转*/
    public function csuccess()
    {
        $copywriter = session('copywriter')?session('copywriter'):'欢迎使用osv服务，祝您生活愉快^_^';
        $tel = session('kefu')?session('kefu'):'';
        $clientHash = session('clientHash')?session('clientHash'):'';
        $data = [];
        $data['copywriter'] = $copywriter;
        $data['tel'] = $tel;
        $data['clientHash'] = $clientHash;
        return view('h5.index.csuccess',$data);
    }
}
