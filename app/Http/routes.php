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
        $api->post('/wxneworder', 'App\Http\Controllers\Wechat\OrderController@wxaddorder');//生成订单支付
        $api->post('/wxnotify', 'App\Http\Controllers\Wechat\OrderController@wxnotify');//微信支付回调
        $api->post('/alinotify', 'App\Http\Controllers\Wechat\OrderController@alinotify');//支付宝支付回调
        $api->any('/wifi', 'App\Http\Controllers\Wechat\ConfigWifiController@index');//微信联网配置
    });
});
