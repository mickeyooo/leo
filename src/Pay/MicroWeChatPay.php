<?php

namespace Leo\Pay;

class MicroWeChatPay extends WeChatPay
{
    /**
     * 刷卡支付
     * @link https://pay.weixin.qq.com/wiki/doc/api/micropay_sl.php?chapter=9_10&index=1
     *
     * @param string $payOrderId 订单号
     * @param string $body       商品或支付单简要描述
     * @param int    $fee        订单总金额，单位为分
     * @param string $authCode   扫码支付授权码
     * @param string $ip         用户端ip
     * @param array  $options    其它可选支付参数
     *
     * @return array
     */
    public function pay($payOrderId, $body, $fee, $authCode, $ip, array $options = [])
    {
        $data = [
            'appid'            => $this->app_id,
            'mch_id'           => $this->mch_id,
            'nonce_str'        => self::buildNonce(16),
            'body'             => $body,
            'out_trade_no'     => $payOrderId,
            'total_fee'        => (int)$fee,
            'spbill_create_ip' => $ip,
            'auth_code'        => $authCode,
        ];
        $this->sub_appid && $data['sub_appid'] = $this->sub_appid;
        $this->sub_mch_id && $data['sub_mch_id'] = $this->sub_mch_id;
        $data['sign'] = $this->getSign($data);
        $response = $this->post(self::toXml($data), 'https://api.mch.weixin.qq.com/pay/micropay');

        return $this->parseResponseResult(self::fromXml($response));
    }

    /**
     * 查询订单
     * @link https://pay.weixin.qq.com/wiki/doc/api/micropay_sl.php?chapter=9_2
     *
     * @param string $payOrderId    订单号
     * @param string $transactionId 微信支付流水号
     *
     * @return array
     */
    public function query($payOrderId, $transactionId = '')
    {
        $data = [
            'appid'        => $this->app_id,
            'mch_id'       => $this->mch_id,
            'out_trade_no' => $payOrderId,
            'nonce_str'    => self::buildNonce(16),
        ];
        $this->sub_appid && $data['sub_appid'] = $this->sub_appid;
        $this->sub_mch_id && $data['sub_mch_id'] = $this->sub_mch_id;
        $transactionId && $data['transaction_id'] = $transactionId;
        $data['sign'] = $this->getSign($data);
        $response = $this->post(self::toXml($data), 'https://api.mch.weixin.qq.com/pay/orderquery');

        return $this->parseResponseResult(self::fromXml($response));
    }

    /**
     * 撤销订单
     * @link https://pay.weixin.qq.com/wiki/doc/api/micropay_sl.php?chapter=9_11&index=3
     *
     * @param string $payOrderId    订单号
     * @param string $transactionId 微信支付流水号
     *
     * @return mixed
     */
    public function reverse($payOrderId, $transactionId = '')
    {
        $data = [
            'appid'        => $this->app_id,
            'mch_id'       => $this->mch_id,
            'out_trade_no' => $payOrderId,
            'nonce_str'    => self::buildNonce(16),
        ];
        $this->sub_appid && $data['sub_appid'] = $this->sub_appid;
        $this->sub_mch_id && $data['sub_mch_id'] = $this->sub_mch_id;
        $transactionId && $data['transaction_id'] = $transactionId;
        $data['sign'] = $this->getSign($data);
        $response = $this->post(self::toXml($data), 'https://api.mch.weixin.qq.com/secapi/pay/reverse');

        return $this->parseResponseResult(self::fromXml($response));
    }
}