<?php
namespace Leo\Pay;

interface PayInterface
{
    /**
     * 获取请求签名串
     *
     * @param array $queryParams
     *
     * @return string
     */
    function getSign(array $queryParams);

    /**
     * 创建订单
     *
     * @param string $payOrderId
     * @param string $body
     * @param int    $fee 支付金额（单位分）
     * @param string $notifyAbsoluteUrl
     * @param string $ip
     * @param int    $timeExpire
     *
     * @return mixed
     */
    function buildOrder($payOrderId, $body, $fee, $notifyAbsoluteUrl, $ip, $timeExpire);

    /**
     * 请求退款
     *
     * @param string $payNumber     支付单号
     * @param string $refundNumber  退款单号
     * @param int    $refundFee     退款总金额
     * @param int    $totalFee      订单总金额，单位为分
     * @param string $transactionId 微信生成的订单号，在支付通知中有返回
     *
     * @return mixed
     */
    function refund($payNumber, $refundNumber, $refundFee, $totalFee, $transactionId);

    /**
     * 验证数据有效性
     *
     * @param array $data
     *
     * @return bool
     * @throw ParamException
     */
    function verifyData(array $data);

    /**
     *  支付通知
     *
     * @param array $data
     *
     * @return array
     * @throw ParamException
     */
    function notify(array $data);
}