<?php

namespace App\Http\Controllers;

use Dingo\Api\Routing\Helpers;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesResources;

class Controller extends BaseController
{
    use AuthorizesRequests, AuthorizesResources, DispatchesJobs, ValidatesRequests, Helpers;

    /**
     * @param int $success
     * @param $res
     * @time 2017/3/2 14:59
     * @con 接口返回格式
     * $success 1 成功 0失败
     * $res 错误提示
     */
    public function respon($success = 0, $res)
    {

        $result = array();
        $result['success'] = $success;

        if ($success) {
            $result['data'] = $res;
        } else {
            $result['error'] = $res;
        }

        header("Content-Type: application/json; charset=utf-8");
        exit(json_encode($result));
    }
}