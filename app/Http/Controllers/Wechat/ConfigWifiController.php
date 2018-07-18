<?php
namespace App\Http\Controllers\Wechat;
/**
 * Created by PhpStorm.
 * User: shixiaokai
 * Date: 2017/9/4
 * Time: 11:32
 */
use App\lib\WeChat\Transfer\JSSDK;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
class ConfigWifiController extends Controller
{
    public function index()
    {
        $jssdk =  new JSSDK();
        $signPackage = $jssdk->getSignPackage(1);
        return view('h5.wifi.index',['signPackage'=>$signPackage]);
    }
}