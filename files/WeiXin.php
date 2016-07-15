<?php

/**
 * 微信第三方平台接口类
 *
 * @link https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1453779503&token=&lang=zh_CN
 */
abstract class WeiXin
{
    const VERIFY_TICKET_KEY = "wx:verify:ticket:%s";
    const COMPONENT_ACCESS_TOKEN_KEY = "wx:component:access:token:%s";

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

    public function setVerifyTicket($verifyTicket)
    {
        return $this->setCache(sprintf(self::VERIFY_TICKET_KEY, $this->appId), $verifyTicket, 900);
    }

    protected function getVerifyTicket()
    {
        return $this->getCache(sprintf(self::VERIFY_TICKET_KEY, $this->appId));
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
            "component_verify_ticket" => $this->getVerifyTicket()
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
        $data = json_decode($this->post(
            sprintf(self::PRE_AUTH_CODE, $this->getComponentAccessToken()),
            ["component_appid" => $this->appId]), true);

        return isset($data['pre_auth_code']) ? $data['pre_auth_code'] : "";
    }

    /**
     * 使用授权码换取公众号的接口调用凭据和授权信息
     *
     * @param $authorizationCode 授权code,会在授权成功时返回给第三方平台，详见第三方平台授权流程说明
     *
     * @return array
     */
    public function getAuthorizationInfo($authorizationCode)
    {
        $data = [
            "component_appid"    => $this->appId,
            "authorization_code" => $authorizationCode
        ];

        return json_decode($this->post(sprintf(self::AUTHORIZATION_INFO, $this->getComponentAccessToken()), $data), true);
    }

    /**
     * 获取（刷新）授权公众号的接口调用凭据（令牌）
     *
     * @param $authorizerAppId
     * @param $refreshToken
     *
     * @return array
     */
    public function refreshAuthorizerAccessToken($authorizerAppId, $refreshToken)
    {
        $data = [
            "component_appid"          => $this->appId,
            "authorizer_appid"         => $authorizerAppId,
            "authorizer_refresh_token" => $refreshToken
        ];

        return json_decode($this->post(
            sprintf(self::AUTHORIZER_ACCESS_TOKEN, $this->getComponentAccessToken()),
            $data), true);
    }

    /**
     * 获取授权方的公众号帐号基本信息
     *
     * @param $authorizerAppId
     *
     * @return array
     */
    public function getAuthorizerInfo($authorizerAppId)
    {
        $data = [
            "component_appid"  => $this->appId,
            "authorizer_appid" => $authorizerAppId
        ];

        return json_decode($this->post(
            sprintf(self::AUTHORIZER_INFO, $this->getComponentAccessToken()),
            $data), true);
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

        $data = json_decode($this->post(
            sprintf(self::SET_AUTHORIZER_OPTION, $this->getComponentAccessToken()),
            $data), true);

        return isset($data['errcode']) && $data['errcode'] == 0 ? true : false;
    }

    protected abstract function setCache($key, $value, $expired);

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