<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;

/* MODELOS */
use App\Models\FailedUserSessions;

class Authenticate
{
    /**
     * The authentication guard factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if ($this->auth->guard($guard)->guest()) {


            $headersData = app('App\Http\Controllers\loginController')->getRequestData($request);
            //REGISTRAMOS API TOKEN NO EXISTE
            $newSession = new FailedUserSessions;
            $newSession->email = '';
            $newSession->started_at = date('Y-m-d');
            $newSession->request_ip = ''.app('App\Http\Controllers\loginController')->getIp();
            $newSession->user_agent = $headersData["userAgent"];
            $newSession->device =  $headersData["device"];
            $newSession->platform = $headersData["platform"];
            $newSession->browser =  $headersData["browser"];
            $newSession->type =  4;
            $newSession->save();

            return response()->json(['status'=>'error', 'error_code' => "000", 'msg'=>'No autorizado'],401);
        }

        return $next($request);
    }
}
