<?php
/**
 * OAuth2.0 Tencent QQ
 * @author Cigery
 */
class wechat extends abstract_oauth
{
    private $_api = 'https://api.weixin.qq.com';

    private $openid = null;

    public function create_login_url($state)
    {
        $params = array
        (
            'response_type' => 'code',
            'appid' => $this->config['appid'],
            'redirect_uri' => baseurl().'/api/oauth/callback/wechat',
            'state' => $this->set_session('STATE', $state),
            'scope' => 'snsapi_userinfo',
        );
        if($this->device == 'mobile') $params['display'] = 'mobile';
        return 'https://open.weixin.qq.com/connect/oauth2/authorize?'.http_build_query($params);
    }

    public function check_callback($args)
    {
        if(empty($args['state']) || $args['state'] != $this->get_session('STATE') || empty($args['code'])) return FALSE;

        $params = array
        (
            'grant_type' => 'authorization_code',
            'appid' => $this->config['appid'],
            'secret' => $this->config['secret'],
            'code' => $args['code'],
        );

        $uri = $this->_api.'/sns/oauth2/access_token?'.http_build_query($params);
        $res = file_get_contents($uri);
        {
            $res = json_decode($res, TRUE);
            if(isset($res['access_token']) && isset($res['openid'])) {
                $this->openid = $res['openid'];

                return $res['access_token'];
            }
        }
        return FALSE;
    }

    public function get_oauth_key($access_token)
    {
        if($this->openid != null){
            return $this->openid;
        }
        return '';
    }

    public function get_user_info($access_token, $oauth_key)
    {
        $params = array
        (
            'access_token' => $access_token,
            'openid' => $oauth_key,
            'lang' => 'zh_CN'
        );

        $uri = $this->_api.'/sns/userinfo?'.http_build_query($params);
        if($res = file_get_contents($uri))
        {
            $res = json_decode($res, TRUE);
            return array
            (
                'openid' => $oauth_key,
                'nickname' => $res['nickname'],
                'gender' => $res['sex'],
                'avatar' => $res['headimgurl'],
                'unionid' => isset($res['unionid']) ? $res['unionid'] : ''
            );
        }
        return FALSE;
    }
}
