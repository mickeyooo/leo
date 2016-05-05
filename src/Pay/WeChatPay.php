<?php
namespace Leo\Pay;

use Leo\Pay\Exception\ArgumentException;

class WeChatPay extends PayAbstract
{
    private $_curlHelper = null;

    protected $app_id;
    protected $app_key;
    protected $partner_id;
    protected $cert_file_path;
    protected $key_file_path;


    /**
     * @param array $config
     *
     * <code>
     * $config = [
     *     "app_id" => "wxd930ea5d5a258f4f",
     *     "partner_id" => "1900000109",
     *     "app_key" => "192006250b4c09247ec02edce69f6a2d",
     *     "cert_file_path" => "/tmp/apiclient_cert.pem",
     *     "key_file_path" => "/tmp/apiclient_key.pem"
     *      ]
     * </code>
     * 
     * @throws ArgumentException
     */
    public function __construct(array $config)
    {
        if (!isset($config['app_id']) || !isset($config['partner_id']) || !isset($config['app_key'])) {
            throw new ArgumentException("Invalid config array.");
        }

        $this->app_id = $config['app_id'];
        $this->partner_id = $config['partner_id'];
        $this->app_key = $config['app_key'];

        $this->cert_file_path = isset($config['cert_file_path']) ? $config['cert_file_path'] : "";
        $this->key_file_path = isset($config['key_file_path']) ? $config['key_file_path'] : "";
    }

    /**
     *  退款
     *
     * @param string $payOrderId 支付id
     * @param int $refundFee 退款总金额
     * @param int $totalFee 订单总金额，单位为分
     * @param string $transactionId 微信生成的订单号，在支付通知中有返回
     *
     * @return bool
     *
     * @throw \Exception
     */
    public function refund($payOrderId, $refundFee, $totalFee, $transactionId)
    {
        $data = [
            "appid"          => $this->_app_id,
            "mch_id"         => $this->_partner_id,
            "op_user_id"     => $this->_partner_id,
            "nonce_str"      => '',
            "out_refund_no"  => $payOrderId,
            "out_trade_no"   => $payOrderId,
            "refund_fee"     => $refundFee,
            "total_fee"      => $totalFee,
            "transaction_id" => $transactionId,
        ];
        $data['sign'] = $this->getSign($data);
        $response = $this->refundPost($data);

        $this->parseResponseResult($response);

        return true;
    }

    /**
     *  获取请求参数签名
     *
     * @link https://pay.weixin.qq.com/wiki/doc/api/app.php?chapter=4_3
     *
     * @param array $params
     *
     * @return string
     */
    public function getSign(array $params)
    {
        $queryString = $this->buildSignQueryString($params);

        return strtoupper(md5($queryString . "&key=" . $this->app_key));
    }

    /**
     *  验证数据有效性
     *
     * @param array $data
     *
     * @return bool
     *
     * @throw ArgumentException
     */
    public function verifyData(array $data)
    {
        if (!isset($data['sign']) || empty($data['sign'])) {
            throw new ArgumentException("sign签名不存在或为空");
        }

        $sign = $data['sign'];
        unset($data['sign']);
        $calcSign = $this->getSign($data);

        return $calcSign == $sign;
    }

    /**
     *  支付通知
     *
     * @param array $data
     *
     * @return array
     *
     * @throw ArgumentException
     */
    public function notify(array $data)
    {
        $data = $this->parseResponseResult($data);

        if ($this->verifyData($data)) {
            return $data;
        }

        throw new ArgumentException("签名无效");
    }

    protected function refundPost($payload)
    {
        $xml = array2xml($payload);
        $curlHelper = $this->getCurlHelper();

        $curlHelper->option(CURLOPT_SSLCERTTYPE, 'PEM');
        $curlHelper->option(CURLOPT_SSLCERT, $this->_cert_file_path);
        $curlHelper->option(CURLOPT_SSLKEYTYPE, 'PEM');
        $curlHelper->option(CURLOPT_SSLKEY, $this->_key_file_path);

        return $curlHelper->post('secapi/pay/refund', $xml, 'xml');
    }

    protected function parseResponseResult($data)
    {
        if (!$data || !isset($data['return_code'])) {
            throw new ParamException("参数无效");
        }

        if ($data['return_code'] == 'SUCCESS') {
            if ($data['result_code'] == 'SUCCESS') {
                return $data;
            } else {
                $exceptionMessage = $data['err_code'];
            }
        } else {
            $exceptionMessage = $data['return_msg'];
        }

        throw new ParamException($exceptionMessage);
    }

    protected function getCurlHelper()
    {
        if ($this->_curlHelper == null) {
            $this->_curlHelper = loadHelper('CurlHelper');
            $this->_curlHelper->initCURL([
                'server'          => 'https://api.mch.weixin.qq.com',
                'ssl_verify_peer' => false,
            ]);
        }

        return $this->_curlHelper;
    }
}