<?php

namespace App\Http\Middleware;

use Closure;
// use JWTAuth;
// use Tymon\JWTAuth\Exceptions\JWTException;
// use Tymon\JWTAuth\Exceptions\TokenExpiredException;
// use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class ValidateToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        var_dump($request->cookie('userinfo'));
        return $next($request);
    //     try {
    //         $token_request = JWTAuth::setRequest($request);
    //         $token = $token_request->getToken();
    //         if (!$token) {
    //             return ['code' => 1004, 'message' => '授权不存在'];
    //         };
    //         $a_token = explode('.', $token->__toString());
    //         $payload = json_decode(base64_decode($a_token[1]));
    //         if ($payload->exp < time()) {
    //             return ['code' => 1005, 'message' => '授权已过期'];
    //         }
    //         $claims = $token_request->parseToken()->getPayload()->toArray();
    //         $user_agent = md5($request->header('User-Agent'));
    //         if ($user_agent != $claims['client']) {
    //             return ['code' => 1003,'message' => '授权无效'];
    //         }
    //     } catch (Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
    //         return ['code' => 1005, 'message' => '授权已过期'];
    //     } catch (Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
    //         return ['code' => 1003, 'message' => '授权无效'];
    //     } catch (Tymon\JWTAuth\Exceptions\JWTException $e) {
    //         return ['code' => 1004, 'message' => '授权不存在'];
    //     }
    //     $request->offsetSet('osv_uid', $claims['sub']);
    //     return $next($request);
    // }
}
