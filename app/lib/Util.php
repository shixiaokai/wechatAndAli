<?php
/**
 * Created by PhpStorm.
 * User: shixiaokai
 * Date: 2017/6/19
 * Time: 10:31
 * PHP通用类
 */

namespace App\lib;
use Ixudra\Curl\Facades\Curl;
class Util
{

    /**
     * 是否是有效的邮箱
     * @param $address
     * @return mixed
     */
    public static function isValidEmail($address)
    {
        return filter_var($address, FILTER_VALIDATE_EMAIL);
    }

    /**
     * @param $url
     * @param $vars
     * @param int $type 默认为普通商户证书  1为服务商证书   注：服务商和普通商户为两套证书，如有疑问请看微信支付文档或联系本人QQ：545807679
     * @return mixed
     */
    public static function weachatPostPemCurl($url, $vars, $type=0)
    {
        if ($type) {
            $rs = Curl::to( $url )
                ->withOption('SSL_VERIFYPEER', false)
                ->withOption('SSL_VERIFYHOST', false)
                ->withOption('SSLCERT',app_path(''))//证书存放位置绝对路径
                ->withOption('SSLKEY',app_path(''))//密钥存放位置绝对路径
                ->withData( $vars )
                ->post();
            return $rs;
        } else {
            $rs = Curl::to( $url )
                ->withOption('SSL_VERIFYPEER', false)
                ->withOption('SSL_VERIFYHOST', false)
                ->withOption('SSLCERT',app_path(''))//证书存放位置绝对路径
                ->withOption('SSLKEY',app_path(''))//密钥存放位置绝对路径
                ->withData( $vars )
                ->post();
            return $rs;
        }
    }

    public static function getFilePath($hash)
    {
        $path = env('FIRM_UPLOAD_PATH','data/files/');
        return $path.'/'. substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;
    }

    public function filterUserName($str)
    {
        $str = iconv('utf-8', 'gbk//IGNORE', $str);
        $str = preg_replace('%([\xA1-\xA9][\xA1-\xFE]|[\xA8-\xA9][\x40-\xA0]|[\x00-\x2f]|[\x3a-\x40]|[\x5B-\x60]|[\x7B-\x7F])%xs', '', $str);
        $str = preg_replace('%([^0-9a-zA-Z]])%s', '', $str);
        $str = iconv('gbk', 'utf-8//IGNORE', $str);

        return $str;
    }
    /*APPID*/
    public static  function genAppid()
    {
        $key = rand(11111111, 99999999);
        return $key;
    }
    /*
     * 生成随机数
     * */
    public static function randomkeys( $length = 16 )
    {
        $pattern='1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLOMNOPQRSTUVWXYZ';
        $key = '';
        for($i=0;$i<$length;$i++)
        {
            $key .= $pattern{mt_rand(0,35)};    //生成php随机数
        }
        return $key;
    }
    /*是否在线*/
    public static function isOnline($appid, $username)
    {
        /*$key = sprintf('users/%s/%s', $appid, $username);
        $clientIds = Redis::smembers($key);
        if ($clientIds) {
            return true;
        }
        return false;*/
        return get_isOnline($username);


    }
    /**
     * 过滤SQL注入非法字符 
     * 
     * @desc
     *
     * @param string | array $string 需要处理的字符串 
     * @access public static
     * @return string | array
     * @exception none
     */
    public static function filterSqlInjection($string)
    {
        if(is_array($string)) {
            foreach($string as $key => $row) {
                $row            = self::_addslashes($row);
                $string[$key]   = self::encodeHtml($row);
            }
        } else {
            $string     = self::_addslashes($string);
            $string     = self::encodeHtml($string);
        }

        return $string;
    }

    /**
     * 给字符串加上反斜杆 
     * 
     * 当开启了自动加反斜杆的配置时，就直接返回 
     * 
     * @access protected static
     * @param string $string 需要处理的字符串
     * @return string 
     * @exception none
     */
    protected static function _addslashes($string)
    {
        if(!get_magic_quotes_gpc()) {
            return addslashes(self::cleanSlash($string));
        } 
        
        return $string;
    }
    /**
     * 计算两个点之间的距离
     * @param $lat1  纬度
     * @param $lng1  经度
     * @param $lat2  纬度
     * @param $lng2  经度
     * @param int $len_type
     * @param int $decimal
     * @return float
     */
    public static function getDistance($lat1, $lng1, $lat2, $lng2, $len_type = 1, $decimal = 2)
    {
        $EARTH_RADIUS = 6378.137;//地球半径
        $PI = 3.1415926;
        $radLat1 = $lat1 * $PI / 180.0;
        $radLat2 = $lat2 * $PI / 180.0;
        $a = $radLat1 - $radLat2;
        $b = ($lng1 * $PI / 180.0) - ($lng2 * $PI / 180.0);
        $s = 2 * asin(sqrt(pow(sin($a/2),2) + cos($radLat1) * cos($radLat2) * pow(sin($b/2),2)));
        $s = $s * $EARTH_RADIUS;
        $s = round($s * 1000);
        if ($len_type > 1)
        {
            $s /= 1000;
        }

        return round($s, $decimal);
    }

    /**
     * 根据经纬度和半径计算出范围
     * @param string $lat 经度
     * @param String $lng 纬度
     * @param float $radius 半径 米
     * @return Array 范围数组
     */
    public static function calcScope($lat, $lng, $radius = '1000000000')
    {
        $PI = 3.14159265;
        $degree = (24901*1609)/360.0;
        $dpmLat = 1/$degree;

        $radiusLat = $dpmLat*$radius;
        $minLat = $lat - $radiusLat;       // 最小经度
        $maxLat = $lat + $radiusLat;       // 最大经度

        $mpdLng = $degree*cos($lat * ($PI/180));
        $dpmLng = 1 / $mpdLng;
        $radiusLng = $dpmLng*$radius;
        $minLng = $lng - $radiusLng;      // 最小纬度
        $maxLng = $lng + $radiusLng;      // 最大纬度

        /** 返回范围数组 */
        $scope = array(
            'minLat'    =>  $minLat,
            'maxLat'    =>  $maxLat,
            'minLng'    =>  $minLng,
            'maxLng'    =>  $maxLng
        );
        return $scope;
    }
    /**
     * 清除反斜杆 
     * 
     * 用法：
     * <code>
     *  HString::cleanSlash('test\''); //test'
     * </code>
     * @access public static
     * @param  String $content  需要处理的内容
     * @return String 
     * @throws none
     */
    public static function cleanSlash($content)
    {
        return stripslashes($content);
    }

    /**
     * 格式化HTML字符
     * 
     * 对：', ", <, >, & 等符号进行转换
     * 
     * @access public static
     * @param string $string 需要处理的字符串
     * @return string 
     * @exception none
     */
    public static function encodeHtml($htmlCode)
    {
        return htmlspecialchars($htmlCode, ENT_QUOTES);
    }

    /**
     * 还原HTML标签 
     * 
     * 把由encodeHtml转化后的html代码反转回来 
     * 
     * @access public
     * @param string $htmlCode 需要处理的HTML代码
     * @return string 
     * @exception none
     */
    public static function decodeHtml($htmlCode)
    {
        return htmlspecialchars_decode($htmlCode, ENT_QUOTES);
    }

    /**
     * 安字符数来计算字符串的长度 
     * 
     * 支持按给写的编码来得到对应的长度 
     * 
     * @access public static
     * @param string $string 需要处理的字符串
     * @param string $encode 字符编码
     * @return int 
     * @exception none
     */
    public static function getLenByChar($string, $encode = 'utf8')
    {
        return mb_strlen($string, $encode);
    }

    /**
     * 通过字节数来得到字符串的长度 
     * 
     * 直接按每个字符所占的内存字节和 
     * 
     * @access public static
     * @param $string
     * @return int 
     */
    public static function getLenByByte($string)
    {
        return strlen($string);
    }

    /**
     * 剪切字符串 
     * 
     * @desc
     * 
     * @access public static
     * @param string $string 需要处理的字符串
     * @param int $max 最大的显示字串长
     * @param string $overMask 超过的标记, 默认为：......
     * @return string 
     */
    public static function cutString($string, $max, $overMask = '.....')
    {
        $enMax      = 2 * $max;
        $strLen     = strlen($string);
        for($i = 0; $i < $strLen && $i < $enMax; $i ++) {
            if(128 <= ord($string[$i])) {
                $enMax --;
            }
        }
        preg_match_all('/./us', $string, $match);
        if($enMax < count($match[0])) { 
            return mb_substr($string , 0, $enMax, 'utf8') . $overMask;
        }

        return $string;
    }

    /**
     * 清除字符串里的HTML标签 
     * 
     * @desc
     * 
     * @access public static
     * @param string $string 需要处理的字符
     * @return string 
     */
    public static function cleanHtmlTag($string)
    {
        return $string;
        $mode   = '%</?[:\w]+(\s?[:\w]+(:\w+)?=\"([/\w.:;\-()\s#=?%]*|[\x{4e00}-\x{9fa5}])*\"\s?)*/?>%i';

        return preg_replace($mode, '', $string);
    }

    /**
     * 把DS换成url的/形式 
     * 
     * 如果当前的DS不是/，则把所有的DS换成/
     * 
     * @access public static
     * @param string $uri 需要处理链接地址
     * @return string  处理后的url串
     */
    public static function DSToSlash($uri)
    {
        if(DS == '/') {
            return $uri;
        }

        return strtr($uri, array(DS => '/'));
    }

    /**
     * 把正斜杆换成DS 
     * 
     * 当DS 不是正斜杆时就换 
     * 
     * @access public static
     * @param string $uri 需要处理的资源路径
     * @return string 处理后的路径值 
     * @exception none
     */
    public static function slashToDS($uri)
    {
        if(DS == '/') {
            return $uri;
        }

        return strtr($uri, array('/' => DS));
    }

    /**
     * @return string
     */
    public static function get_client_ip()
    {
        if ($_SERVER['REMOTE_ADDR']) {
            $cip = $_SERVER['REMOTE_ADDR'];
        } elseif (getenv("REMOTE_ADDR")) {
            $cip = getenv("REMOTE_ADDR");
        } elseif (getenv("HTTP_CLIENT_IP")) {
            $cip = getenv("HTTP_CLIENT_IP");
        } else {
            $cip = "unknown";
        }
        return $cip;
    }

    /**
     * 过滤多余的反斜杆 
     * 
     * @desc
     * 
     * @access public static
     * @param string $string 需要处理的字符串
     * @return string 
     */
    public static function filterMoreBackSlash($string)
    {
        return preg_replace('%\\\+%', '', $string);
    }



    /**
     * 文本转换成Unicode字符串
     *
     * @desc
     *
     * @access public static
     * @param  String $str  需要转换的字符串
     * @return String 转换后的字符串 
     */
    public static function text2Unicode( $str )
    {
        $unicode    = array();      
        $values     = array();
        $lookingFor = 1;
        for ($i = 0; $i < strlen( $str ); $i++ ) {
            $thisValue = ord( $str[ $i ] );
            if ( $thisValue < ord('A') ) {
                if ($thisValue >= ord('0') && $thisValue <= ord('9')) {
                    $unicode[] = '00'.dechex($thisValue);
                } else {
                    $unicode[] = '00'.dechex($thisValue);
                }
            } else {
                if ( $thisValue < 128) 
                    $unicode[] = '00'.dechex($thisValue);
                else {
                    if ( count( $values ) == 0 ) $lookingFor = ( $thisValue < 224 ) ? 2 : 3;                
                    $values[] = $thisValue;                
                    if ( count( $values ) == $lookingFor ) {
                        $number = ( $lookingFor == 3 ) ?
                            ( ( $values[0] % 16 ) * 4096 ) + ( ( $values[1] % 64 ) * 64 ) + ( $values[2] % 64 ):
                            ( ( $values[0] % 32 ) * 64 ) + ( $values[1] % 64 );
                        $number = dechex($number);
                        $unicode[] = (strlen($number)==3)?"0".$number:"".$number;
                        $values = array();
                        $lookingFor = 1;
                    } 
                } 
            }
        } 
        for ($i = 0 ; $i < count($unicode) ; $i++) {
            $unicode[$i] = str_pad($unicode[$i] , 4 , "0" , STR_PAD_LEFT);
        }

        return implode("" , $unicode);
    } 

    /**
     * 得到UUID
     * 
     * @desc
     * 
     * @author wuchuanchang <wuchuanchang@e7124.com>
     * @access public static
     * @param  char $char 连接字符, 默认为：''
     * @return String 得到当前的UUID 
     */
    public static function getUUID($char = '')
    {
        return sprintf( '%04x%04x' . $char . '%04x' . $char . '%04x' . $char . '%04x' . $char . '%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

            // 16 bits for "time_mid"
            mt_rand( 0, 0xffff ),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand( 0, 0x0fff ) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand( 0, 0x3fff ) | 0x8000,

            // 48 bits for "node"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }

    /**
     * IP转换成整数
     * 
     * @desc
     * 
     * @author wuchuanchang <wuchuanchang@e7124.com>
     * @access public static
     * @param  String $ip 需要转换的IP地址
     * @return int 整形数据
     */
    public static function ip2int($ip)
    {
        list($ip1,$ip2,$ip3,$ip4)   =   explode(".", $ip);

        return ($ip1<<24)|($ip2<<16)|($ip3<<8)|($ip4);
    }

    //获取网址域名
    public static function getDomain( $url )  
    {  
        if ( ! $url ) return "";
        $pattern = "/[/w-]+/.(com|net|org|fm|gov|biz|com.tw|com.hk|com.ru|net.tw|net.hk|net.ru|info|cn|com.cn|net.cn|org.cn|gov.cn|mobi|name|sh|ac|la|travel|tm|us|cc|tv|jobs|asia|hn|lc|hk|bz|com.hk|ws|tel|io|tw|ac.cn|bj.cn|sh.cn|tj.cn|cq.cn|he.cn|sx.cn|nm.cn|ln.cn|jl.cn|hl.cn|js.cn|zj.cn|ah.cn|fj.cn|jx.cn|sd.cn|ha.cn|hb.cn|hn.cn|gd.cn|gx.cn|hi.cn|sc.cn|gz.cn|yn.cn|xz.cn|sn.cn|gs.cn|qh.cn|nx.cn|xj.cn|tw.cn|hk.cn|mo.cn|org.hk|is|edu|mil|au|jp|int|kr|de|vc|ag|in|me|edu.cn|co.kr|gd|vg|co.uk|be|sg|it|ro|com.mo)(/.(cn|hk))*/";  
        @preg_match($pattern, $url, $matches);  
        if(count($matches) > 0)  
        {  
            return $matches[0];  
        }
        else
        {  
            $rs = parse_url($url);  
            $main_url = $rs["host"];  
            if(!strcmp(long2ip(sprintf("%u",ip2long($main_url))),$main_url))  
            {  
                return $main_url;  
            }
            else
            {  
                $arr = explode(".",$main_url);  
                $count=count($arr);  
                $endArr = array("com","net","org");//com.cn net.cn 等情况  
                if (in_array($arr[$count-2],$endArr))  
                {  
                    $domain = $arr[$count-3].".".$arr[$count-2].".".$arr[$count-1];  
                }else
                {  
                    $domain = $arr[$count-2].".".$arr[$count-1];  
                }

                return $domain;  
            }  
        }  
    }  


    public static function isValidPrice($price)
    {
        return preg_match('/^([1-9]\d*.\d*|0.\d*[1-9]\d*)|([1-9]\d*)$/', $price);
    }

    /**
     * @param $telphone
     * @return bool
     * 验证手机号格式是否正确
     */

    public static function isValidMobile($mobile)
    {
        return preg_match("/^1[34578]{1}\d{9}$/",$mobile);
    }

    /**
     * @param $telphone
     * @return bool
     * 验证手机号电话格式是否正确
     */
    public static function isValidTelPhone($telphone)
    {
        if(!preg_match("/^1[34578]{1}\d{9}$/",$telphone))
        {
            $rule  = "/^(0[0-9]{2,3}-)?([2-9][0-9]{6,7})+(-[0-9]{1,4})?$/";
            $result = preg_match($rule,$telphone);
            if(!$result)
            {
                $rule = "/^400[0-9]{7}/";
                $result = preg_match($rule,$telphone);
                if(!$result)
                {
                    return false;
                }
            }
        }
        return true;
    }
    public static function secondsToStr($seconds=0)
    {
        if(!$seconds) return '';
        if ($seconds < 60) {
            return $seconds.'秒';
        }
        $minutes = floor($seconds/60);
        if($minutes < 60)
        {
            $seconds = $seconds%60;
            if($seconds) return $minutes.'分'.$seconds.'秒';
            return $minutes.'分钟';
        }
        $hour = floor($minutes/60);
        $minutes = $minutes%60;
        if($minutes) return $hour.'小时'.$minutes.'分';
        return $hour.'小时';
    }

    public static function formatTime($timestamp)
    {
        $now = time();
        $today = strtotime('today');
        $yesterday = strtotime('yesterday');

        $intval = $now - $timestamp;
        switch (1) {
            case $intval < 60:
                return "刚刚";
                break;
            case $intval < 3600:
                return ceil($intval / 60) . "分钟前";
                break;
            case $timestamp >= $today:
                return "今天 " . date("H:i");
                break;
            case $timestamp >= $yesterday:
                return "昨天 " . date("H:i");
                break;
            default:
                return date("Y-m-d H:i:s", $timestamp);
                break;
        }
    }

    public static function strcode($string, $key = '', $action='encode') 
    {
        $action = strtolower($action);

        $string = $action == 'encode' ? $string : base64_decode($string);
        $len = strlen($key);
        $code = '';

        for ($i = 0; $i < strlen($string); $i ++){
            $k = $i % $len;
            $code .= $string[$i] ^ $key[$k];
        }

        $code = $action == 'decode' ? $code : base64_encode($code);

        return $code;
    }
    /**
     * @param $_String
     * @param string $_Code
     * @return mixed
     * 字符转义
     */
    public  static function getFirstWord($_String, $_Code='UTF8'){ //GBK页面可改为gb2312，其他随意填写为UTF8
        $_String = preg_replace("/[0-9]/", '', $_String);
        $_DataKey = "a|ai|an|ang|ao|ba|bai|ban|bang|bao|bei|ben|beng|bi|bian|biao|bie|bin|bing|bo|bu|ca|cai|can|cang|cao|ce|ceng|cha". 
                        "|chai|chan|chang|chao|che|chen|cheng|chi|chong|chou|chu|chuai|chuan|chuang|chui|chun|chuo|ci|cong|cou|cu|". 
                        "cuan|cui|cun|cuo|da|dai|dan|dang|dao|de|deng|di|dian|diao|die|ding|diu|dong|dou|du|duan|dui|dun|duo|e|en|er". 
                        "|fa|fan|fang|fei|fen|feng|fo|fou|fu|ga|gai|gan|gang|gao|ge|gei|gen|geng|gong|gou|gu|gua|guai|guan|guang|gui". 
                        "|gun|guo|ha|hai|han|hang|hao|he|hei|hen|heng|hong|hou|hu|hua|huai|huan|huang|hui|hun|huo|ji|jia|jian|jiang". 
                        "|jiao|jie|jin|jing|jiong|jiu|ju|juan|jue|jun|ka|kai|kan|kang|kao|ke|ken|keng|kong|kou|ku|kua|kuai|kuan|kuang". 
                        "|kui|kun|kuo|la|lai|lan|lang|lao|le|lei|leng|li|lia|lian|liang|liao|lie|lin|ling|liu|long|lou|lu|lv|luan|lue". 
                        "|lun|luo|ma|mai|man|mang|mao|me|mei|men|meng|mi|mian|miao|mie|min|ming|miu|mo|mou|mu|na|nai|nan|nang|nao|ne". 
                        "|nei|nen|neng|ni|nian|niang|niao|nie|nin|ning|niu|nong|nu|nv|nuan|nue|nuo|o|ou|pa|pai|pan|pang|pao|pei|pen". 
                        "|peng|pi|pian|piao|pie|pin|ping|po|pu|qi|qia|qian|qiang|qiao|qie|qin|qing|qiong|qiu|qu|quan|que|qun|ran|rang". 
                        "|rao|re|ren|reng|ri|rong|rou|ru|ruan|rui|run|ruo|sa|sai|san|sang|sao|se|sen|seng|sha|shai|shan|shang|shao|". 
                        "she|shen|sheng|shi|shou|shu|shua|shuai|shuan|shuang|shui|shun|shuo|si|song|sou|su|suan|sui|sun|suo|ta|tai|". 
                        "tan|tang|tao|te|teng|ti|tian|tiao|tie|ting|tong|tou|tu|tuan|tui|tun|tuo|wa|wai|wan|wang|wei|wen|weng|wo|wu". 
                        "|xi|xia|xian|xiang|xiao|xie|xin|xing|xiong|xiu|xu|xuan|xue|xun|ya|yan|yang|yao|ye|yi|yin|ying|yo|yong|you". 
                        "|yu|yuan|yue|yun|za|zai|zan|zang|zao|ze|zei|zen|zeng|zha|zhai|zhan|zhang|zhao|zhe|zhen|zheng|zhi|zhong|". 
                        "zhou|zhu|zhua|zhuai|zhuan|zhuang|zhui|zhun|zhuo|zi|zong|zou|zu|zuan|zui|zun|zuo";
        $_DataValue = "-20319|-20317|-20304|-20295|-20292|-20283|-20265|-20257|-20242|-20230|-20051|-20036|-20032|-20026|-20002|-19990". 
                        "|-19986|-19982|-19976|-19805|-19784|-19775|-19774|-19763|-19756|-19751|-19746|-19741|-19739|-19728|-19725". 
                        "|-19715|-19540|-19531|-19525|-19515|-19500|-19484|-19479|-19467|-19289|-19288|-19281|-19275|-19270|-19263". 
                        "|-19261|-19249|-19243|-19242|-19238|-19235|-19227|-19224|-19218|-19212|-19038|-19023|-19018|-19006|-19003". 
                        "|-18996|-18977|-18961|-18952|-18783|-18774|-18773|-18763|-18756|-18741|-18735|-18731|-18722|-18710|-18697". 
                        "|-18696|-18526|-18518|-18501|-18490|-18478|-18463|-18448|-18447|-18446|-18239|-18237|-18231|-18220|-18211". 
                        "|-18201|-18184|-18183|-18181|-18012|-17997|-17988|-17970|-17964|-17961|-17950|-17947|-17931|-17928|-17922". 
                        "|-17759|-17752|-17733|-17730|-17721|-17703|-17701|-17697|-17692|-17683|-17676|-17496|-17487|-17482|-17468". 
                        "|-17454|-17433|-17427|-17417|-17202|-17185|-16983|-16970|-16942|-16915|-16733|-16708|-16706|-16689|-16664". 
                        "|-16657|-16647|-16474|-16470|-16465|-16459|-16452|-16448|-16433|-16429|-16427|-16423|-16419|-16412|-16407". 
                        "|-16403|-16401|-16393|-16220|-16216|-16212|-16205|-16202|-16187|-16180|-16171|-16169|-16158|-16155|-15959". 
                        "|-15958|-15944|-15933|-15920|-15915|-15903|-15889|-15878|-15707|-15701|-15681|-15667|-15661|-15659|-15652". 
                        "|-15640|-15631|-15625|-15454|-15448|-15436|-15435|-15419|-15416|-15408|-15394|-15385|-15377|-15375|-15369". 
                        "|-15363|-15362|-15183|-15180|-15165|-15158|-15153|-15150|-15149|-15144|-15143|-15141|-15140|-15139|-15128". 
                        "|-15121|-15119|-15117|-15110|-15109|-14941|-14937|-14933|-14930|-14929|-14928|-14926|-14922|-14921|-14914". 
                        "|-14908|-14902|-14894|-14889|-14882|-14873|-14871|-14857|-14678|-14674|-14670|-14668|-14663|-14654|-14645". 
                        "|-14630|-14594|-14429|-14407|-14399|-14384|-14379|-14368|-14355|-14353|-14345|-14170|-14159|-14151|-14149". 
                        "|-14145|-14140|-14137|-14135|-14125|-14123|-14122|-14112|-14109|-14099|-14097|-14094|-14092|-14090|-14087". 
                        "|-14083|-13917|-13914|-13910|-13907|-13906|-13905|-13896|-13894|-13878|-13870|-13859|-13847|-13831|-13658". 
                        "|-13611|-13601|-13406|-13404|-13400|-13398|-13395|-13391|-13387|-13383|-13367|-13359|-13356|-13343|-13340". 
                        "|-13329|-13326|-13318|-13147|-13138|-13120|-13107|-13096|-13095|-13091|-13076|-13068|-13063|-13060|-12888". 
                        "|-12875|-12871|-12860|-12858|-12852|-12849|-12838|-12831|-12829|-12812|-12802|-12607|-12597|-12594|-12585". 
                        "|-12556|-12359|-12346|-12320|-12300|-12120|-12099|-12089|-12074|-12067|-12058|-12039|-11867|-11861|-11847". 
                        "|-11831|-11798|-11781|-11604|-11589|-11536|-11358|-11340|-11339|-11324|-11303|-11097|-11077|-11067|-11055". 
                        "|-11052|-11045|-11041|-11038|-11024|-11020|-11019|-11018|-11014|-10838|-10832|-10815|-10800|-10790|-10780". 
                        "|-10764|-10587|-10544|-10533|-10519|-10331|-10329|-10328|-10322|-10315|-10309|-10307|-10296|-10281|-10274". 
                        "|-10270|-10262|-10260|-10256|-10254";
        $_TDataKey   = explode('|', $_DataKey);
        $_TDataValue = explode('|', $_DataValue);
        $_Data = array_combine($_TDataKey, $_TDataValue);
        arsort($_Data);
        reset($_Data);
        if($_Code!= 'gb2312') $_String = self::_U2_Utf8_Gb($_String);
        $_Res = '';
        for($i=0; $i<strlen($_String); $i++) {
                $_P = ord(substr($_String, $i, 1));
                if($_P>160) {
                        $_Q = ord(substr($_String, ++$i, 1)); $_P = $_P*256 + $_Q - 65536;
                }
                $str = self::_Pinyin($_P, $_Data);
                $_Res .= substr($str,0,1);
        }
        return preg_replace("/[^a-z0-9]*/", '', $_Res);
    }
    protected static function _Pinyin($_Num, $_Data){
        if($_Num>0 && $_Num<160 ){
                return chr($_Num);
        }elseif($_Num<-20319 || $_Num>-10247){
                return '';
        }else{
                foreach($_Data as $k=>$v){ if($v<=$_Num) break; }
                return $k;
        }
    }
    public static function _U2_Utf8_Gb($_C) {
        $_String = '';
        if($_C < 0x80){
                $_String .= $_C;
        }elseif($_C < 0x800) {
                $_String .= chr(0xC0 | $_C>>6);
                $_String .= chr(0x80 | $_C & 0x3F);
        }elseif($_C < 0x10000){
                $_String .= chr(0xE0 | $_C>>12);
                $_String .= chr(0x80 | $_C>>6 & 0x3F);
                $_String .= chr(0x80 | $_C & 0x3F);
        }elseif($_C < 0x200000) {
                $_String .= chr(0xF0 | $_C>>18);
                $_String .= chr(0x80 | $_C>>12 & 0x3F);
                $_String .= chr(0x80 | $_C>>6 & 0x3F);
                $_String .= chr(0x80 | $_C & 0x3F);
        }
        return iconv('UTF-8', 'GB2312', $_String);
    }

    /*
     * xml转换数组
     */
    public static function xmlToArray($xml) {
        //2018-06-30 修复
        //禁用加载外部实体的能力
        libxml_disable_entity_loader(true);
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
     * @param $object
     * @return array
     * object 转 array
     */
    public static function objectToArray($object)
    {
        $array = is_object($object) ? get_object_vars($object) : $object;
        $arr = array();
        foreach ($array as $key => $value)
        {
            $value = (is_array($value) || is_object($value)) ? Util::objectToArray($value) : $value;
            $arr[$key] = $value;
        }
        return $array;
    }
    
    public static function postCurl($url,$xml,$second = 30) {
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
        if($data){
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
}

