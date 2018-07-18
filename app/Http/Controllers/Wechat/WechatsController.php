<?php
/**
 * Created by PhpStorm.
 * User: shixiaokai
 * Date: 2017/7/10
 * Time: 14:29
 */
namespace App\Http\Controllers\Wechat;

use App\lib\SampleCode\Thirdparty;
use App\lib\wechatCallbackapi;
use App\lib\wechatCallbackapiTest;
use App\lib\wechatTest;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

header('content-type:text/html;charset=utf-8');
class WechatsController extends Controller
{

    public function index()
    {
        date_default_timezone_set('Asia/Shanghai');
        $wechatObj = new wechatCallbackapiTest();
        if (!isset($_GET['echostr']))
        {
            $res = $wechatObj->responseMsg();
        }else
        {
            $res = $wechatObj->valid();
        }
        echo $res;die;
    }
}