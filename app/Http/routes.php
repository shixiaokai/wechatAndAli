<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::group(['prefix'=>''], function() {
    Route::get('/dev', 'ChannelAdmin\LoginController@login');
});

$api = app('Dingo\Api\Routing\Router');
$api->version('v1', function ($api) {
    /*H5路由*/
    $api->group(['prefix' => 'index'], function($api){
        $api->any('share', 'App\Http\Controllers\Wechat\OrderController@toindex');  // 首页
        $api->any('cargosuccess', 'App\Http\Controllers\Wechat\OrderController@cargosuccess');  // 货柜支付完成页面
        $api->any('csuccess', 'App\Http\Controllers\Wechat\OrderController@csuccess');  // 其他品类支付完成中转
        $api->post('/wxneworder', 'App\Http\Controllers\Wechat\OrderController@wxaddorder');//生成订单支付
        $api->post('/wxnotify', 'App\Http\Controllers\Wechat\OrderController@wxnotify');//微信支付回调
        $api->post('/alinotify', 'App\Http\Controllers\Wechat\OrderController@alinotify');//支付宝支付回调
        $api->post('/clientstatus', 'App\Http\Controllers\Wechat\OrderController@getClientStatus');    //微信订单
        $api->any('/testPaybycard', 'App\Http\Controllers\Wechat\OrderController@testPaybycard');    //测试刷卡支付
        $api->any('/wifi', 'App\Http\Controllers\Wechat\ConfigWifiController@index');                   //用户管理后台首页
    });
});
