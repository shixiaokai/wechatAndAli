<?php
namespace App\lib;
/**
 * 
 * 微信支付--只生成预定义订单+订单结果查询
 */
class Weixinpay
{
  
   const Appid = '';
   const Mch_id = '';
   const CURL_TIMEOUT = 30;
   const Key = '';
   public $out_trade_no ;
   public $total_fee ;

    /**
    * 获取prepay_id
    */
   public static function getPrepayId($oid,$money,$notifyUrl='') {

       $xml = self::createPrepayIdXml($oid, $money,$notifyUrl);
       $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
       $response = self::postXml($xml,$url);
       $result = self::xmlToArray($response);
       //$prepay_id = $result["prepay_id"] = 'wx201411101639507cbf6ffd8b0779950874';\
       return $result["prepay_id"];
   }
   
   /**
    * 获取查询结果
    */
   public static function getOrderQuery($out_trade_no ) {
       $xml = self::createOrderQueryXml($out_trade_no );
       $url = 'https://api.mch.weixin.qq.com/pay/orderquery';
       $response = self::postXml($xml,$url);
       $result = self::xmlToArray($response);

       return $result;
   }
   
   /**
    * 生成预定义订单参数的xml
    */
   public static function createPrepayIdXml($oid,$money,$notifyUrl) {
       $parameters["out_trade_no"] = $oid;
       $parameters["body"] = '订单编号-'.$oid;
       $parameters["total_fee"] = ($money*100);
       $parameters["notify_url"] = empty($notifyUrl)?'http://' . $_SERVER['HTTP_HOST'] . '/agent/wxpaynotify':$notifyUrl;
       $parameters["trade_type"] = 'APP';
       try {
           // 检测必填参数S
           if($parameters["out_trade_no"] == null) {
               throw new \Exception("缺少统一支付接口必填参数out_trade_no！"."<br>");
           }elseif($parameters["body"] == null){
               throw new \Exception("缺少统一支付接口必填参数body！"."<br>");
           }elseif ($parameters["total_fee"] == null ) {
               throw new \Exception("缺少统一支付接口必填参数total_fee！"."<br>");
           }elseif ($parameters["notify_url"] == null) {
               throw new \Exception("缺少统一支付接口必填参数notify_url！"."<br>");
           }elseif ($parameters["trade_type"] == null) {
               throw new \Exception("缺少统一支付接口必填参数trade_type！"."<br>");
           }

           $parameters["appid"] = self::Appid;
           $parameters["mch_id"] = self::Mch_id;        // 商户号

           $parameters["spbill_create_ip"] = $_SERVER['REMOTE_ADDR'];// 终端ip
           $parameters["nonce_str"] = self::createNoncestr();     // 随机字符串
           $parameters["sign"] = self::getSign($parameters); // 签名
           return  self::arrayToXml($parameters);
       } catch (\Exception $e) {
           die($e->errorMessage());
       }
   }
   /**
    * 生成接口参数xml
    */
   public static function createXml($oid,$money) {
       $parameters["out_trade_no"] = $oid;
       $parameters["body"] = 'AppAPI支付测试';
       $parameters["total_fee"] = $money;
       //$parameters["total_fee"] = 1;
       $parameters["notify_url"] = 'http://' . $_SERVER['HTTP_HOST'] .  '/api/weixinnotify/paynotify';
       $parameters["trade_type"] = 'App';
       try {
           // 检测必填参数
           if($parameters["out_trade_no"] == null) {
               throw new \Exception("缺少统一支付接口必填参数out_trade_no！"."<br>");
           }elseif($parameters["body"] == null){
               throw new \Exception("缺少统一支付接口必填参数body！"."<br>");
           }elseif ($parameters["total_fee"] == null ) {
               throw new \Exception("缺少统一支付接口必填参数total_fee！"."<br>");
           }elseif ($parameters["notify_url"] == null) {
               throw new \Exception("缺少统一支付接口必填参数notify_url！"."<br>");
           }elseif ($parameters["trade_type"] == null) {
               throw new \Exception("缺少统一支付接口必填参数trade_type！"."<br>");
           }
           $parameters["appid"] = self::Appid;     // 公众账号ID
           $parameters["mch_id"] = self::Mch_id;        // 商户号
           $parameters["spbill_create_ip"] = $_SERVER['REMOTE_ADDR'];// 终端ip
           $parameters["nonce_str"] = self::createNoncestr();     // 随机字符串
           $parameters["sign"] = self::getSign($parameters); // 签名
           return  self::arrayToXml($parameters);
       } catch (\Exception $e) {
           die($e->errorMessage());
       }
   }
   
    /**
     * 生成订单结果查询 参数的xml
     */
    public static function createOrderQueryXml($oid) {
        $parameters["out_trade_no"] = $oid;
        try {
            // 检测必填参数
            if($parameters["out_trade_no"] == null) {
                throw new \Exception("缺少统一支付接口必填参数out_trade_no！"."<br>");
            }
            $parameters["appid"] = self::Appid;     // 公众账号ID
            $parameters["mch_id"] = self::Mch_id;        // 商户号
            $parameters["nonce_str"] = self::createNoncestr();     // 随机字符串
            $parameters["sign"] = self::getSign($parameters); // 签名
            return  self::arrayToXml($parameters);
        } catch (\Exception $e) {
            die($e->errorMessage());
        }
    }
    /**
     *  作用：产生随机字符串，不长于32位
     */
    public static function createNoncestr( $length = 32 ) {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ( $i = 0; $i < $length; $i++ )  {
            $str.= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
    }
    
    /**
     *  作用：生成签名
     */
    public static function getSign($Obj,$key='') {
        foreach ($Obj as $k => $v) {
            $Parameters[$k] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = self::formatBizQueryParaMap($Parameters, false);
        //echo '【string1】'.$String.'</br>';
        //签名步骤二：在string后加入KEY
        $keys = !empty($key)?:self::Key;
        $String = $String."&key=".$keys;
        //echo "【string2】".$String."</br>";
        //签名步骤三：MD5加密
        $String = md5($String);
        //echo "【string3】 ".$String."</br>";
        //签名步骤四：所有字符转为大写
        $result_ = strtoupper($String);
        //echo "【result】 ".$result_."</br>";
        return $result_;
    }
   
    /**
     *  作用：post请求xml
     */
    public  static  function postXml($xml,$url) {
        $response = self::postXmlCurl($xml,$url,self::CURL_TIMEOUT);
        return $response;
    }
    
   /**
    *  作用：以post方式提交xml到对应的接口url
    */
   public static function postXmlCurl($xml,$url,$second = 30) {
       //初始化curl
       $ch = curl_init();
       //设置超时
       curl_setopt($ch, CURLOPT_TIMEOUT, $second);
       //这里设置代理，如果有的话
       //curl_setopt($ch,CURLOPT_PROXY, '8.8.8.8');
       //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
       curl_setopt($ch,CURLOPT_URL, $url);
       curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
       curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
       //设置header
       curl_setopt($ch, CURLOPT_HEADER, FALSE);
       //要求结果为字符串且输出到屏幕上
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
       //post提交方式
       curl_setopt($ch, CURLOPT_POST, TRUE);
       curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
       //运行curl
       $data = curl_exec($ch);
       curl_close($ch);
       //返回结果
       if($data)
       {
           //curl_close($ch);
           return $data;
       }
       else
       {
           $error = curl_errno($ch);
           echo "curl出错，错误码:$error"."<br>";
           echo "<a href='http://curl.haxx.se/libcurl/c/libcurl-errors.html'>错误原因查询</a></br>";
           curl_close($ch);
           return false;
       }
   }
   
   /**
    *  作用：将xml转为array
    */
   public static function xmlToArray($xml) {
       //将XML转为array
       $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
       return $array_data;
   }
    
   /**
    *  作用：array转xml
    */
   public static function arrayToXml($arr) {
       $xml = "<xml>";
       foreach ($arr as $key=>$val) {
           if (is_numeric($val)) {
               $xml.="<".$key.">".$val."</".$key.">";
           } else {
               $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
           }
       }
       $xml.="</xml>";
       return $xml;
   }
    
   
   /**
    *  作用：格式化参数，签名过程需要使用
    */
   public static function formatBizQueryParaMap($paraMap, $urlencode) {
       $buff = "";
       ksort($paraMap);
       foreach ($paraMap as $k => $v)
       {
           if($urlencode)
           {
               $v = urlencode($v);
           }
           //$buff .= strtolower($k) . "=" . $v . "&";
          
           $buff .= $k . "=" . $v . "&";
           
       }
       $reqPar = '';
       if (strlen($buff) > 0) {
           $reqPar = substr($buff, 0, strlen($buff)-1);
       }
       return $reqPar;
   }
   
   /**
    * 生成接口参数xml
    */
   public static function createRefundXml($oid,$rid)
   {
       $parameters['transaction_id'] = $oid;
       $parameters['out_refund_no'] = $rid;
       $parameters['total_fee'] = 1;
       $parameters['refund_fee'] = 1;
       $parameters['op_user_id'] = self::Mch_id;
       try
       {
           //检测必填参数
           if($parameters["transaction_id"] == null) {
               throw new \Exception("退款申请接口中，out_trade_no、transaction_id至少填一个！"."<br>");
           }elseif($parameters["out_refund_no"] == null){
               throw new \Exception("退款申请接口中，缺少必填参数out_refund_no！"."<br>");
           }elseif($parameters["total_fee"] == null){
               throw new \Exception("退款申请接口中，缺少必填参数total_fee！"."<br>");
           }elseif($parameters["refund_fee"] == null){
               throw new \Exception("退款申请接口中，缺少必填参数refund_fee！"."<br>");
           }elseif($parameters["op_user_id"] == null){
               throw new \Exception("退款申请接口中，缺少必填参数op_user_id！"."<br>");
           }
           $parameters["appid"] = self::Appid;//公众账号ID
           $parameters["mch_id"] = self::Mch_id;//商户号
           $parameters["nonce_str"] = self::createNoncestr();;//随机字符串
           $parameters["sign"] =self::getSign($parameters);//签名
           return  self::arrayToXml($parameters);
       } catch (\Exception $e) {
           die($e->errorMessage());
       }
   }
    
   /**
    * 	作用：使用证书，以post方式提交xml到对应的接口url
    */
   function postXmlSSLCurl($xml,$url,$second=30)
   {
       $ch = curl_init();
       //超时时间
       curl_setopt($ch,CURLOPT_TIMEOUT,$second);
       //这里设置代理，如果有的话
       //curl_setopt($ch,CURLOPT_PROXY, '8.8.8.8');
       //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
       curl_setopt($ch,CURLOPT_URL, $url);
       curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
       curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
       //设置header
       curl_setopt($ch,CURLOPT_HEADER,FALSE);
       //要求结果为字符串且输出到屏幕上
       curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
       //设置证书
       //使用证书：cert 与 key 分别属于两个.pem文件
       //默认格式为PEM，可以注释
       curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
       curl_setopt($ch,CURLOPT_SSLCERT, SSLCERT_PATH);
       //默认格式为PEM，可以注释
       curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
       curl_setopt($ch,CURLOPT_SSLKEY, SSLKEY_PATH);
       //post提交方式
       curl_setopt($ch,CURLOPT_POST, true);
       curl_setopt($ch,CURLOPT_POSTFIELDS,$xml);
       $data = curl_exec($ch);
       //返回结果
       if($data){
           curl_close($ch);
           return $data;
       }
       else {
           $error = curl_errno($ch);
           echo "curl出错，错误码:$error"."<br>";
           echo "<a href='http://curl.haxx.se/libcurl/c/libcurl-errors.html'>错误原因查询</a></br>";
           curl_close($ch);
           return false;
       }
   }
   
   /**
    * 	作用：获取结果，使用证书通信
    */
   public static function getResult($oid,$fid)
   {
       $reponse = self::postXmlSSL($oid,$fid);
       $result = self::xmlToArray($reponse);
       return $result;
   }
   
   /**
    * 	作用：使用证书post请求xml
    */
   public static function postXmlSSL($oid,$fid)
   {
       $url = 'https://api.mch.weixin.qq.com/secapi/pay/refund';
       $xml = self::createRefundXml($oid,$fid);
       $response = self::postXmlSSLCurl($xml,$url,self::CURL_TIMEOUT);
       return $response;
   }
}