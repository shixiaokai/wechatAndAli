<?php
use App\lib\WeChat\H5pay\JsApiPay;
use App\lib\Alijssdk;

if (!function_exists('respon')) {
    /**
     * Flatten a multi-dimensional array into a single level.
     *
     * @param  array $array
     * @param  int $depth
     * @return array
     */
    function respon($success = 0, $res)
    {
        $result = array();
        $result['success'] = $success;

        if ($success == 1) {
            $result['data'] = $res;
        } else {
            $result['error'] = $res;
        }

        header("Content-Type: application/json; charset=utf-8");
        exit(json_encode($result));
    }
}


if (!function_exists('post_curl')) {
    /**
     * 远程获取数据，POST模式
     * @param $url  指定URL完整路径地址
     * @param $para 请求的数据
     * @param $json 是否json格式，默认为否
     * @return mixed
     */
    function post_curl($url, $para, $json = false)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        // curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);    //SSL证书认证
        // curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);       //严格认证
        // curl_setopt($curl, CURLOPT_CAINFO, $cacert_url);     //证书地址
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);          //显示输出结果
        if (!empty($para)) {
            if ($json && is_array($para)) {
                $para = json_encode($para);
            }

            curl_setopt($curl, CURLOPT_POST, true);             //post传输数据
            curl_setopt($curl, CURLOPT_POSTFIELDS, $para);      //post传输数据
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $responseText = curl_exec($curl);
        curl_close($curl);
        return $responseText;
    }
}

if (!function_exists('create_key_str')) {
    /** 生成Key
     *  作用：产生随机字符串，不长于32位
     */
    function create_key_str($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
}

if(!function_exists('get_userInfo_wechat_Ali')) {
    /**
     * 作用：获取微信或支付宝用户授权信息
     * @param $type  1:微信 2:支付宝
     * @return mixed
     */
    function get_userInfo_wechat_Ali ($type=0)
    {
        if(!$type){
            $type = fun_aliorwechat();
            if(in_array($type,[1,2])) return ['errorCode'=>0,'data'=>'请求类型错误'];
        }
        $arr = [];
        //微信
        if ($type == 1){
            $tools = new JsApiPay();
            $userInfo = $tools->__GetUserInfo();
            if (!isset($userInfo['openid']) || empty($userInfo['openid'])) {
                return ['errorCode'=>0,'data'=>'网络错误'];
            }
            $openid = $userInfo['openid'];
            $unionid = (isset($userInfo['unionid']) && !empty($userInfo['unionid'])) ? $userInfo['unionid'] : $userInfo['openid'];
            $nickname = (isset($userInfo['nickname']) && !empty($userInfo['nickname'])) ? $userInfo['nickname'] : '';
            $language = (isset($userInfo['language']) && !empty($userInfo['language'])) ? $userInfo['language'] : '';
            $city = (isset($userInfo['city']) && !empty($userInfo['city'])) ? $userInfo['city'] : '';
            $province = (isset($userInfo['province']) && !empty($userInfo['province'])) ? $userInfo['province'] : '';
            $country = (isset($userInfo['country']) && !empty($userInfo['country'])) ? $userInfo['country'] : '';
            $arr['openid'] = $openid;
            $arr['unionid'] = $unionid;
            $arr['nickname'] = $nickname;
            $arr['sex'] = isset($userInfo['sex']) ? $userInfo['sex'] : 0;
            $arr['type'] = 1;
            $arr['headimgurl'] = isset($userInfo['headimgurl']) ? $userInfo['headimgurl'] : '';
            $arr['language'] = $language;
            $arr['city'] = $city;
            $arr['province'] = $province;
            $arr['country'] = $country;
        } else {
            $AliSSDK = new Alijssdk();
            $userInfo = $AliSSDK->getCode('2');
            if (!isset($userInfo['user_id']) || empty($userInfo['user_id'])) return '网络错误';
            $user_id = $userInfo['user_id'];
            $user['open_id'] = $user_id;
            $arr['openid'] = $user_id;
            $arr['nickname'] = isset($userInfo['nick_name']) ? $userInfo['nick_name'] : '';
            $arr['sex'] = isset($userInfo['gender']) && $userInfo['gender'] == 'M' ? 1 : (isset($userInfo['gender']) && $userInfo['gender'] == 'F' ?: '0');
            $arr['type'] = 2;
            $arr['headimgurl'] = isset($userInfo['avatar']) ? $userInfo['avatar'] : '';
            $arr['language'] = '';
            $arr['city'] = (isset($userInfo['city']) && !empty($userInfo['city'])) ? $userInfo['city'] : '';
            $arr['province'] = (isset($userInfo['province']) && !empty($userInfo['province'])) ? $userInfo['province'] : '';
        }
        return ['errorCode'=>1,'data'=>$arr];
    }
}

/**
 * $date
 * $type=1:  2018/06/04
 * $type=2:  2018年06月04
 * $type=3:  2018 06 04
 * 默认      2018-06-04
 */
if (!function_exists('checkDateFormat')) {
    function checkDateFormat($date='',$type=0)
    {
        if (!$date) return false;
        if ($type == 1)
            $preg = "/^([0-9]{4})\/([0-9]{2})\/([0-9]{2})$/";
        elseif ($type == 2)
            $preg = "/^([0-9]{4})年([0-9]{2})月([0-9]{2})$/";
        elseif($type == 3)
            $preg = "/^([0-9]{4}) ([0-9]{2}) ([0-9]{2})$/";
        else
            $preg = "/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/";
        //匹配日期格式
        if (preg_match($preg, $date, $parts))
            //检测是否为日期
            if (checkdate($parts[2], $parts[3], $parts[1]))
                return true;
            else
                return false;
        else
            return false;
    }
}

if (!function_exists('fun_aliorwechat')) {
    /**
     * 统一判断是微信还是者支付宝
     * 1:微信
     * 2:支付宝
     * 3:其他
     */
    function fun_aliorwechat()
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        if($is_mobile==0){
            if(  strpos($user_agent, 'MicroMessenger') !== false  )//微信
            {
                return 1;
            }elseif(  strpos($user_agent, 'AlipayClient') !== false  )//支付宝
            {
                return 2;
            }else
            {
                return 3;
            }
        }else{
            return $user_agent;
        }
    }
}

