<?php
/**
 * Created by PhpStorm.
 * User: waixiu
 * Date: 2017/6/15
 * Time: 15:03
 */

namespace App\lib;
use App\lib\SampleCode\Thirdparty;
use App\lib\SampleCode\wxBizMsgCrypt;
use App\Models\CommChannel\wechats;
use App\Models\Merchant\coupon;
use App\Models\Merchant\dev_channel;
use App\Models\MerchantApi\channel_powder_set;
use App\Models\MerchantApi\wechat_subscribe;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Ixudra\Curl\Facades\Curl;

//define("token", "weixin");
//define("AppID", "wx1985bd0909bbc1f9");
//define("EncodingAESKey", "NQRc2EskoLyejQsPgfhOUj2cPyRDmDhzv2G4IjxRylA");
//if(strtolower($_SERVER['REQUEST_METHOD']) == 'get') {
//    file_put_contents('weixin_log.txt', "IP=".$_SERVER['REMOTE_ADDR'].PHP_EOL,FILE_APPEND); //记录访问IP到log日志
//    file_put_contents('weixin_log.txt', "QUERY_STRING=".$_SERVER['QUERY_STRING'].PHP_EOL,FILE_APPEND);//记录请求字符串到log日志
//    file_put_contents('weixin_log.txt', '$_GET[echostr])='.htmlspecialchars($_GET['echostr']).PHP_EOL,FILE_APPEND); //记录是否获取到echostr参数
//    exit(htmlspecialchars($_GET['echostr']));      //把echostr参数返回给微信开发者后台
//}
class wechatCallbackapiTest
{
    private $token;
    private $encodingAESKey;
    private $appid;
    public function __construct()
    {
        $this->token = env('WX_SF_TOKEN');
        $this->encodingAESKey = env('WX_SF_AESKEY');
        $this->appid = env('WX_SF_APPID');
    }

     //验证签名
     public function valid()
     {
         $echoStr = $_GET["echostr"];
         $signature = $_GET["signature"];
         $timestamp = $_GET["timestamp"];
         $nonce = $_GET["nonce"];
         $tmpArr = array($this->token, $timestamp, $nonce);
         sort($tmpArr);
         $tmpStr = implode($tmpArr);
         $tmpStr = sha1($tmpStr);
         if($tmpStr == $signature)
         {
            return $echoStr;
            exit;
         }
    }

     //响应消息
    public function responseMsg()
    {
        $timestamp  = $_GET['timestamp'];
        $nonce = $_GET["nonce"];
        $msg_signature = $_GET['msg_signature'];
        $encrypt_type = (isset($_GET['encrypt_type']) && ($_GET['encrypt_type'] == 'aes')) ? "aes" : "raw";
        $postStr = file_get_contents('php://input');
        if (!empty($postStr))
        {
             //解密
            if ($encrypt_type == 'aes')
            {
                $pc = new wxBizMsgCrypt($this->token, $this->encodingAESKey,  $this->appid);
                $decryptMsg = "";  //解密后的明文
                $errCode = $pc->decryptMsg($msg_signature, $timestamp, $nonce, $postStr, $decryptMsg);
                $postStr = $decryptMsg;
            }
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            if(!$postObj) {
                Storage::disk('test')->append('success.log',date('Y-m-d H:i:s').'返回明文:'.$postStr);
                return '';
            }
            $RX_TYPE = $postObj ? trim($postObj->MsgType) : '';
            $result = '';
            switch ($RX_TYPE)
            {
                case "event":
                     $result = $this->receiveEvent($postObj);
                     break;
                case "text":
                    $result = $this->receiveText($postObj);
                    break;
            }
            if (!$result) {
                return '';
            }
            //Storage::disk('test')->append('success.log',date('Y-m-d H:i:s').'返回明文:'.$result);
             //加密
             if ($encrypt_type == 'aes')
             {
                 $encryptMsg = ''; //加密后的密文
                 $errCode = $pc->encryptMsg($result, $timestamp, $nonce, $encryptMsg);
                 $result = $encryptMsg;
             }
             return $result;
        }else
        {
             return "";
             exit;
        }
   }
    private function receiveEvent($object)
    {
        $content = "ok";
        switch ($object->Event)
        {
            case "subscribe":
                $content = "我关注了";
                break;
            case "unsubscribe"://取消关注事件
                $content = "success";
                break;
        }
        $result = $this->transmitText($object, $content);
        return $result;
     }

    //接收文本消息
    private function receiveText($object)
    {
        $keyword = trim($object->Content);
        if (strstr($keyword, "文本"))
        {
              $content = "这是个文本消息";
        }else if (strstr($keyword, "单图文"))
        {
             $content = array();
             $content[] = array("Title"=>"单图文标题",  "Description"=>"单图文内容", "PicUrl"=>"http://d.hiphotos.bdimg.com/wisegame/pic/item/f3529822720e0cf3ac9f1ada0846f21fbe09aaa3.jpg", "Url" =>"http://www.baidu.com");
        }else if (strstr($keyword, "图文") || strstr($keyword, "多图文"))
        {
            $content = array();
            $content[] = array("Title"=>"多图文1标题", "Description"=>"", "PicUrl"=>"http://d.hiphotos.bdimg.com/wisegame/pic/item/f3529822720e0cf3ac9f1ada0846f21fbe09aaa3.jpg", "Url" =>"http://www.baidu.com");
            $content[] = array("Title"=>"多图文2标题", "Description"=>"", "PicUrl"=>"http://d.hiphotos.bdimg.com/wisegame/pic/item/f3529822720e0cf3ac9f1ada0846f21fbe09aaa3.jpg", "Url" =>"http://www.baidu.com");
            $content[] = array("Title"=>"多图文3标题", "Description"=>"", "PicUrl"=>"http://d.hiphotos.bdimg.com/wisegame/pic/item/f3529822720e0cf3ac9f1ada0846f21fbe09aaa3.jpg", "Url" =>"http://www.baidu.com");
        }else if (strstr($keyword, "音乐"))
        {
            $content = array();
            $content = array("Title"=>"最炫民族风", "Description"=>"歌手：凤凰传奇", "MusicUrl"=>"http://121.199.4.61/music/zxmzf.mp3", "HQMusicUrl"=>"http://121.199.4.61/music/zxmzf.mp3");
        }else{
            $content = "";
        }

        if(is_array($content))
        {
            if (isset($content[0]))
            {
                 $result = $this->transmitNews($object, $content);
            }else if (isset($content['MusicUrl']))
            {
                 $result = $this->transmitMusic($object, $content);
            }
        }else
        {
            $result = $this->transmitText($object, $content);
        }
        return $result;
    }

    //回复文本消息
     private function transmitText($object, $content)
     {
         if (!isset($content) || empty($content)){
             return "";
         }
         $xmlTpl = "<xml>
                    <ToUserName><![CDATA[%s]]></ToUserName>
                    <FromUserName><![CDATA[%s]]></FromUserName>
                    <CreateTime>%s</CreateTime>
                    <MsgType><![CDATA[text]]></MsgType>
                    <Content><![CDATA[%s]]></Content>
                 </xml>";
         $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time(), $content);
         return $result;
    }

   //回复图文消息
     private function transmitNews($object, $newsArray)
     {
        if(!is_array($newsArray))
        {
                   return;
        }
        $itemTpl = "<item>
                        <Title><![CDATA[%s]]></Title>
                         <Description><![CDATA[%s]]></Description>
                         <PicUrl><![CDATA[%s]]></PicUrl>
                         <Url><![CDATA[%s]]></Url>
                    </item>";
         $item_str = "";
         foreach ($newsArray as $item)
         {
             $item_str .= sprintf($itemTpl, $item['Title'], $item['Description'], $item['PicUrl'], $item['Url']);
         }
         $xmlTpl = "<xml>
                         <ToUserName><![CDATA[%s]]></ToUserName>
                         <FromUserName><![CDATA[%s]]></FromUserName>
                         <CreateTime>%s</CreateTime>
                         <MsgType><![CDATA[news]]></MsgType>
                         <ArticleCount>%s</ArticleCount>
                         <Articles>$item_str</Articles>
                     </xml>";
         $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time(), count($newsArray));
         return $result;
     }

     //回复音乐消息
     private function transmitMusic($object, $musicArray)
     {
        $itemTpl = "<Music>
                         <Title><![CDATA[%s]]></Title>
                         <Description><![CDATA[%s]]></Description>
                         <MusicUrl><![CDATA[%s]]></MusicUrl>
                         <HQMusicUrl><![CDATA[%s]]></HQMusicUrl>
                    </Music>";
         $item_str = sprintf($itemTpl, $musicArray['Title'], $musicArray['Description'], $musicArray['MusicUrl'], $musicArray['HQMusicUrl']);
         $xmlTpl = "<xml>
                     <ToUserName><![CDATA[%s]]></ToUserName>
                     <FromUserName><![CDATA[%s]]></FromUserName>
                     <CreateTime>%s</CreateTime>
                     <MsgType><![CDATA[music]]></MsgType>
                     $item_str
                    </xml>";
         $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
         return $result;
     }
 }
?>