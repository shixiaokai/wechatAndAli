<?php
/**
 * Created by PhpStorm.
 * User: shixiaokai
 * Date: 2017/8/3
 * Time: 18:04
 */
namespace App\lib\SampleCode;
use App\Models\CommChannel\wechats;
use Illuminate\Support\Facades\Redis;
use Ixudra\Curl\Facades\Curl;
class Thirdparty
{
    private $token;
    private $encodingAESKey;
    private $appid;
    private $secret;
    private $ComponentAccessToken;
    public function __construct()
    {
        $this->token = env('WX_SF_TOKEN');
        $this->encodingAESKey = env('WX_SF_AESKEY');
        $this->appid = env('WX_SF_APPID');
        $this->secret = env('WX_SF_SECRET');

        $this->ComponentAccessToken = $this->getComponentAccessToken();
    }

    /**
     * @param $ticket
     * @return string
     * 获取token
     */
    public function getComponentAccessToken()
    {
        $ComponentAccessToken = Redis::get('component_access_token');
        if( !$ComponentAccessToken )
        {
            $ticket = Redis::get('component_verify_ticket');
            if( $ticket )
            {
                $url = "https://api.weixin.qq.com/cgi-bin/component/api_component_token";
                $data = array(
                    'component_appid'=>$this->appid,
                    'component_appsecret'=>$this->secret,
                    'component_verify_ticket'=>$ticket
                );
                $resp = Curl::to($url)
                    ->withContentType('application/json')
                    ->withData( $data )
                    ->asJsonRequest()
                    ->post();
                //savelog('res', $resp.'==='.$ticket);
                $resp = json_decode($resp,true);
                if( isset( $resp['component_access_token'] ) && !empty( $resp['component_access_token'] ) )
                {
                    Redis::setex('component_access_token',7000,$resp['component_access_token']);
                    return $resp['component_access_token'] ;
                } else {
                    savelog('sf_shou_quan_error.log', json_encode($resp));
                }
            } else {
                savelog('sf_shou_quan_error.log', '未找到获取token的票券component_verify_ticket');
                return '';
            }

        }
        return $ComponentAccessToken;
    }

    /**
     * 获取授权code
     */
    public function getPreAuthCode()
    {
        $url = "https://api.weixin.qq.com/cgi-bin/component/api_create_preauthcode?component_access_token=".$this->ComponentAccessToken;
        $data = array(
            'component_appid'=>$this->appid,
        );
        $resp = Curl::to($url)
            ->withContentType('application/json')
            ->withData( $data )
            ->asJsonRequest()
            ->post();
        $resp = json_decode($resp,true);
        if( isset( $resp['pre_auth_code'] ) && !empty( $resp['pre_auth_code'] ) )
        {
            return $resp['pre_auth_code'] ;
        }
    }

    /**
     * @param $auth_code
     * 获取授权者信息
     */
    public function getPublicSignal( $auth_code )
    {
        if( !empty ( $auth_code ) )
        {
            $url = "https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token=".$this->ComponentAccessToken;
            $data = array(
                'component_appid'=>$this->appid,
                'authorization_code'=>$auth_code,
            );
            $resp = Curl::to($url)
                ->withContentType('application/json')
                ->withData( $data )
                ->asJsonRequest()
                ->post();
            $resp = json_decode($resp,true);
            return $resp;
        }
        return '';
    }
    /**
     * @param $auth_code
     * 获取授权者详情
     */
    public function getpublicInfo( $appid )
    {
        if( !empty ( $appid ) )
        {
            $url = "https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_info?component_access_token=".$this->ComponentAccessToken;
            $data = array(
                'component_appid'=>$this->appid,
                'authorizer_appid'=>$appid,
            );
            $resp = Curl::to($url)
                ->withContentType('application/json')
                ->withData( $data )
                ->asJsonRequest()
                ->post();
            $resp = json_decode($resp,true);
            return $resp;
        }
        return '';
    }

    private function getUserAccess()
    {
        $three_access_token = Redis::get('three_access_token');
        if( !$three_access_token )
        {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appid&secret=$this->secret";
            $resp = Curl::to($url)->get();
            //return $resp;
            $resp = json_decode($resp,true);
            if( isset( $resp['access_token'] ) && !empty($resp['access_token']) )
            {
                Redis::setex('three_access_token',7000,$resp['access_token']);
                return $resp['access_token'];
            }
            return '';
        }
        return $three_access_token;
    }

    /**
     * @param array $content
     * @return bool|mixed
     * 发送客服消息
     */
    public function service( $content = array() )
    {
        if( !$content )
        {
            return false;
        }
        $accessToken = $this->getUserAccess();
        return $accessToken;
        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=$accessToken";
        $resp = Curl::to($url)
             ->withContentType('application/json')
             ->withData( $content )
             ->asJsonRequest()
             ->post();
        $resp = json_decode($resp,true);
        return $resp;
    }

    /**
     *  获取授权方access_token
     */
    public function getauthorizerAccess_token($authAppid)
    {
        if(!$authAppid)
        {
            return '111';
        }
        $auth_access_token = Redis::get(trim($authAppid));
        if( !$auth_access_token )
        {
            $wechat = wechats::where(['user_name'=>$authAppid])->orderBy('id','desc')->first();
            if(!empty($wechat))
            {
                $wechat = json_decode($wechat,true);
                $platformToken = $this->getComponentAccessToken();
                $authorizer_refresh_token = $wechat['authorizer_refresh_token'];
                $url = "https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token?component_access_token=".$platformToken;
                $data = [];
                $data['component_appid'] = $this->appid;
                $data['authorizer_appid'] = trim($wechat['authorizer_appid']);
                $data['authorizer_refresh_token'] = $authorizer_refresh_token;
                $resp = Curl::to($url)
                    ->withContentType('application/json')
                    ->withData( $data )
                    ->asJsonRequest()
                    ->post();
                $resp = json_decode($resp,true);

                if(isset( $resp['authorizer_refresh_token'] ) && !empty($resp['authorizer_refresh_token']))
                {
                     wechats::where(['user_name'=>$authAppid])->update(['authorizer_refresh_token'=>$resp['authorizer_refresh_token']]);
                }
                if( isset( $resp['authorizer_access_token'] ) && !empty($resp['authorizer_access_token']) )
                {
                    Redis::setex($authAppid,7000,$resp['authorizer_access_token']);
                    return $resp['authorizer_access_token'];
                }
            } else {
                return '666';
            }

        }
        return $auth_access_token;
    }

}