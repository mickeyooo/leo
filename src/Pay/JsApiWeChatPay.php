<?php
namespace Leo\Pay;

use Leo\Pay\Exception\ArgumentException;

/**
 *  微信JS支付
 */
class JsApiWeChatPay extends WeChatPay implements PayInterface
{
    /**
     * @param array $config
     *
     * <code>
     * $config = [
     *       "app_id"         => "wxd930ea5d5a258f4f",
     *       "app_key"        => "192006250b4c09247ec02edce69f6a2d",
     *       "mch_id"         => "1301449201",
     *       "sub_mch_id"     => "1315302001",
     *       "cert_file_path" => "/tmp/apiclient_cert.pem",
     *       "key_file_path"  => "/tmp/apiclient_key.pem"
     *   ];
     * </code>
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
     * @param string $payOrderId        订单号
     * @param string $body              商品或支付单简要描述
     * @param int    $fee               订单总金额，单位为分
     * @param string $notifyAbsoluteUrl 接收微信支付异步通知回调地址
     * @param string $openId            微信openid
     * @param string $ip                用户端ip
     * @param int    $timeExpire        支付超时(minutes)
     * @param string $openId            微信openid
     *
     * @return array
     * @throws \Exception
     */
    public function buildOrder($payOrderId, $body, $fee, $notifyAbsoluteUrl, $openId, $ip, $timeExpire = 30)
    {
        $data = [
            'appid'            => $this->app_id,
            'mch_id'           => $this->mch_id,
            'nonce_str'        => $this->buildNonce(16),
            'body'             => $body,
            'out_trade_no'     => $payOrderId,
            'total_fee'        => (int)$fee,
            'spbill_create_ip' => $ip,
            'notify_url'       => $notifyAbsoluteUrl,
            'trade_type'       => 'JSAPI',
            'openid'           => $openId ?: '',
            'time_expire'      => date('YmdHis', time() + (int)$timeExpire)
        ];
        $this->sub_mch_id && $data['sub_mch_id'] = $this->sub_mch_id;
        $data['sign'] = $this->getSign($data);
        $response = $this->post(self::toXml($data), 'https://api.mch.weixin.qq.com/pay/unifiedorder');
        $responseData = $this->parseResponseResult(self::fromXml($response));

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
            throw new ArgumentException("prepayId is not null or empty.");
        }

        $data = [
            "appId"     => $this->app_id,
            "timeStamp" => time(),
            "nonceStr"  => $this->buildNonce(16),
            "package"   => "prepay_id=$prepayId",
            "signType"  => "MD5"
        ];
        $data["paySign"] = $this->getSign($data);
        $data['timestamp'] = $data['timeStamp'];
        unset($data['timeStamp']);

        return $data;
    }

}