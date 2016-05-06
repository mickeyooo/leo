<?php
namespace Leo\Pay;

/**
 *  微信JS支付
 */
class JsApiWeChatPay extends WeChatPay implements PayInterface
{
    /**
     * @param array $config
     *
     * <code>
     *      $config = [
     *          "app_id" => "wxd930ea5d5a258f4f",
     *          "partner_id" => "1900000109",
     *          "app_key" => "192006250b4c09247ec02edce69f6a2d",
     *          "cert_file_path" => "/tmp/apiclient_cert.pem",
     *          "key_file_path" => "/tmp/apiclient_key.pem"
     *      ]
     * </code>
     *
     * @throws \Exception
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
    }

    /**
     * 创建订单
     * @link https://pay.weixin.qq.com/wiki/doc/api/jsapi_sl.php?chapter=9_1
     *
     * @param string $payOrderId 订单号
     * @param string $body 商品或支付单简要描述
     * @param int $fee 订单总金额，单位为分
     * @param string $notifyAbsoluteUrl 接收微信支付异步通知回调地址
     * @param string $ip 用户端ip
     * @param int $timeExpire 支付超时时间
     * @param string $openId 微信openid
     *
     * @return array
     * @throws \Exception
     */
    public function buildOrder($payOrderId, $body, $fee, $notifyAbsoluteUrl, $ip, $timeExpire, $openId = '')
    {
        $timeExpire = $timeExpire && $timeExpire > 5 ? time() + $timeExpire * 60 : time() + 900;
        $data = [
            'appid'            => $this->app_id,
            'mch_id'           => $this->partner_id,
            'sub_mch_id'=> '',
            'nonce_str'        => '',
            'body'             => $body,
            'out_trade_no'     => $payOrderId,
            'total_fee'        => (int)$fee,
            'spbill_create_ip' => $ip,
            'notify_url'       => $notifyAbsoluteUrl,
            'trade_type'       => 'JSAPI',
            'openid'           => $openId ?: '',
            'time_expire'      => formatDate('YmdHis', $timeExpire)
        ];
        $data['sign'] = $this->getSign($data);
        $response = $this->post($this->toXml($data), 'https://api.mch.weixin.qq.com/pay/unifiedorder');
        $responseData = $this->parseResponseResult($response);

        return [
            $responseData,
            $this->_buildPrepayQueryParameters($responseData['prepay_id'])
        ];
    }

    /**
     *  生成预支付参数
     *
     * @param  string $prepayId
     *
     * @return array
     * @throws \Exception
     */
    private function _buildPrepayQueryParameters($prepayId)
    {
        if (!$prepayId) {
            throw new \Exception("Invalid prepayId.");
        }

        $data = [
            "appId"     => $this->app_id,
            "timeStamp" => time(),
            "nonceStr"  => getRandString(32),
            "package"   => "prepay_id=$prepayId",
            "signType"  => "MD5"
        ];
        $data["paySign"] = $this->getSign($data);
        $data['timestamp'] = $data['timeStamp'];
        unset($data['timeStamp']);
        unset($data['appId']);

        return $data;
    }

}