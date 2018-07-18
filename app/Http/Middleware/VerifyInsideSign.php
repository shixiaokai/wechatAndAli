<?php

namespace App\Http\Middleware;

use Closure;

class VerifyInsideSign
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
        $clientIP = $request->ip();
        $inside_ips = config('allowips.inside');
        if ( !in_array($clientIP, $inside_ips) ) {
            return response()->json([
                'code' => 5006,
                'message' => 'IP未被授权',
            ]);
        }

        return $next($request);
    }
}
