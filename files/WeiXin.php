<?php

/**
 * 微信第三方平台接口类
 *
 * @link https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1453779503&token=&lang=zh_CN
 */
abstract class WeiXin
{
    const COMPONENT_VERIFY_TICKET_KEY = "wx:component:verify:ticket:%s";
    const COMPONENT_ACCESS_TOKEN_KEY = "wx:component:access:token:%s";
    const AUTHORIZER_REFRESH_TOKEN_KEY = 'wx:authorizer:refresh:token:%s';
    const AUTHORIZER_ACCESS_TOKEN_KEY = 'wx:authorizer:access:token:%s';

    const COMPONENT_ACCESS_TOKEN = "/api_component_token";
    const PRE_AUTH_CODE = "/api_create_preauthcode?component_access_token=%s";
    const AUTHORIZATION_INFO = "/api_query_auth?component_access_token=%s";
    const AUTHORIZER_ACCESS_TOKEN = "/api_authorizer_token?component_access_token=%s";
    const AUTHORIZER_INFO = "/api_get_authorizer_info?component_access_token=%s";
    const GET_AUTHORIZER_OPTION = "/api_get_authorizer_option?component_access_token=%s";
    const SET_AUTHORIZER_OPTION = "/api_set_authorizer_option?component_access_token=%s";

    protected $appId;
    protected $appSecret;

    public function __construct($appId, $appSecret)
    {
        $this->appId     = $appId;
        $this->appSecret = $appSecret;
    }

    public function setComponentVerifyTicket($componentVerifyTicket)
    {
        return $this->setCache(
            sprintf(self::COMPONENT_VERIFY_TICKET_KEY, $this->appId),
            $componentVerifyTicket
        );
    }

    protected function getComponentVerifyTicket()
    {
        return $this->getCache(sprintf(self::COMPONENT_VERIFY_TICKET_KEY, $this->appId));
    }

    protected function setAuthorizerRefreshToken($authorizerAppId, $authorizerRefreshToken)
    {
        return $this->setCache(
            sprintf(self::AUTHORIZER_REFRESH_TOKEN_KEY, $authorizerAppId),
            $authorizerRefreshToken
        );
    }

    public function getAuthorizerRefreshToken($authorizerAppId)
    {
        $token = $this->getCache(sprintf(self::AUTHORIZER_REFRESH_TOKEN_KEY, $authorizerAppId));

        return $token;
    }

    protected function setAuthorizerAccessToken($authorizerAppId, $authorizerAccessToken, $expired = 3600)
    {
        return $this->setCache(
            sprintf(self::AUTHORIZER_ACCESS_TOKEN_KEY, $authorizerAppId),
            $authorizerAccessToken,
            $expired
        );
    }

    protected function refreshAuthorizerAccessToken($authorizerAppId)
    {
        $data = [
            "component_appid"          => $this->appId,
            "authorizer_appid"         => $authorizerAppId,
            "authorizer_refresh_token" => $this->getAuthorizerRefreshToken($authorizerAppId)
        ];

        return json_decode(
            $this->post(
                sprintf(self::AUTHORIZER_ACCESS_TOKEN, $this->getComponentAccessToken()),
                $data),
            true
        );
    }

    /**
     * 获取授权方访问 token
     *
     * @param string $authorizerAppId
     *
     * @return string
     * @throws \Exception
     */
    public function getAuthorizerAccessToken($authorizerAppId)
    {
        $token = $this->getCache(sprintf(self::AUTHORIZER_ACCESS_TOKEN_KEY, $authorizerAppId));

        if ($token) {
            return $token;
        }

        $data = $this->refreshAuthorizerAccessToken($authorizerAppId);

        if (isset($data['errcode'])) {
            throw new \Exception($data['errmsg'], $data['errcode']);
        }

        $this->setAuthorizerAccessToken(
            $authorizerAppId,
            $data['authorizer_access_token'],
            isset($data['expires_in']) ? intval($data['expires_in']) - 100 : 3600
        );

        return $data['authorizer_access_token'];
    }

    /**
     * 获取第三方平台component_access_token
     *
     * @return string
     * @throws Exception
     */
    public function getComponentAccessToken()
    {
        $tokenKey = sprintf(self::COMPONENT_ACCESS_TOKEN_KEY, $this->appId);
        $token    = $this->getCache($tokenKey);

        if ($token) {
            return $token;
        }

        $params = [
            "component_appid"         => $this->appId,
            "component_appsecret"     => $this->appSecret,
            "component_verify_ticket" => $this->getComponentVerifyTicket()
        ];
        $data   = json_decode($this->post(self::COMPONENT_ACCESS_TOKEN, $params), true);

        if (isset($data['component_access_token']) && $data['component_access_token']) {
            $expired = isset($data['expires_in']) ? intval($data['expires_in']) - 100 : 3600;
            $this->setCache($tokenKey, $data['component_access_token'], $expired);

            return $data['component_access_token'];
        }

        throw new \Exception(sprintf("获取第三方（appid:%s）平台 component_access_token 失败", $this->appId));
    }

    /**
     * 获取预授权码pre_auth_code
     *
     * @return string
     */
    public function getPreAuthCode()
    {
        $data = json_decode(
            $this->post(
                sprintf(self::PRE_AUTH_CODE, $this->getComponentAccessToken()),
                ["component_appid" => $this->appId]),
            true
        );

        return isset($data['pre_auth_code']) ? $data['pre_auth_code'] : "";
    }

    /**
     * 使用授权码换取公众号的接口调用凭据和授权信息
     *
     * @param $authorizationCode 授权code,会在授权成功时返回给第三方平台，详见第三方平台授权流程说明
     *
     * @return array
     * @throws \Exception
     */
    public function getAuthorizationInfo($authorizationCode)
    {
        $data = [
            "component_appid"    => $this->appId,
            "authorization_code" => $authorizationCode
        ];
        $data = json_decode(
            $this->post(sprintf(self::AUTHORIZATION_INFO, $this->getComponentAccessToken()), $data),
            true
        );

        if (!is_array($data) || !isset($data['authorization_info'])) {
            throw new \Exception("使用授权码换取公众号的接口调用凭据和授权信息失败");
        }

        $this->setAuthorizerRefreshToken(
            $data['authorization_info']['authorizer_appid'],
            $data['authorization_info']['authorizer_refresh_token']
        );
        $this->setAuthorizerAccessToken(
            $data['authorization_info']['authorizer_appid'],
            $data['authorization_info']['authorizer_access_token'],
            $data['authorization_info']['expires_in'] ? intval($data['authorization_info']['expires_in']) - 100 : 3600
        );

        return $data['authorization_info'];
    }

    /**
     * 获取授权方的公众号帐号基本信息
     *
     * @param $authorizerAppId
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getAuthorizerInfo($authorizerAppId)
    {
        $data = json_decode(
            $this->post(
                sprintf(self::AUTHORIZER_INFO, $this->getComponentAccessToken()),
                ["component_appid" => $this->appId, "authorizer_appid" => $authorizerAppId]),
            true
        );

        if (isset($data['errcode'])) {
            throw new \Exception($data['errmsg'], $data['errcode']);
        }

        return $data['authorizer_info'];
    }

    /**
     * 获取授权方的选项设置信息
     *
     * @param $authorizerAppId
     * @param $option
     *
     * @return array
     */
    public function getAuthorizerOption($authorizerAppId, $option)
    {
        $data = [
            "component_appid"  => $this->appId,
            "authorizer_appid" => $authorizerAppId,
            "option_name"      => $option
        ];

        return json_decode($this->post(
            sprintf(self::GET_AUTHORIZER_OPTION, $this->getComponentAccessToken()),
            $data), true);
    }

    /**
     * 设置授权方的选项信息
     *
     * @param $authorizerAppId
     * @param $option
     * @param $value
     *
     * @return bool
     */
    public function setAuthorizerOption($authorizerAppId, $option, $value)
    {
        $data = [
            "component_appid"  => $this->appId,
            "authorizer_appid" => $authorizerAppId,
            "option_name"      => $option,
            "option_value"     => $value
        ];

        $data = json_decode(
            $this->post(
                sprintf(self::SET_AUTHORIZER_OPTION, $this->getComponentAccessToken()),
                $data),
            true
        );

        return isset($data['errcode']) && $data['errcode'] == 0 ? true : false;
    }

    protected abstract function setCache($key, $value, $expired = null);

    protected abstract function getCache($key);

    protected function post($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSLVERSION, 1);
        curl_setopt($ch, CURLOPT_URL, "https://api.weixin.qq.com/cgi-bin/component" . $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch);

        if (intval($status['http_code']) != 200) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new \Exception($error);
        }

        curl_close($ch);

        return $response;
    }
}