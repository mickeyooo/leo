<?php
namespace Leo\Pay;

use Leo\Pay\Exception\ArgumentException;
use Leo\Pay\Exception\WeChatPayPostException;

class WeChatPay extends PayAbstract
{
    protected $app_id;
    protected $app_key;
    protected $mch_id;
    protected $sub_mch_id;
    protected $sub_appid = '';
    protected $cert_file_path;
    protected $key_file_path;


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
     *
     * @throws ArgumentException
     */
    public function __construct(array $config)
    {
        if (!isset($config['app_id']) || !isset($config['mch_id']) || !isset($config['app_key'])) {
            throw new ArgumentException("Invalid config array.");
        }

        $this->app_id = $config['app_id'];
        $this->app_key = $config['app_key'];
        $this->mch_id = $config['mch_id'];

        isset($config['sub_mch_id']) && $config['sub_mch_id'] && $this->sub_mch_id = $config['sub_mch_id'];
        isset($config['sub_appid']) && $config['sub_appid'] && $this->sub_appid = $config['sub_appid'];

        $this->cert_file_path = isset($config['cert_file_path']) ? $config['cert_file_path'] : null;
        $this->key_file_path = isset($config['key_file_path']) ? $config['key_file_path'] : null;
    }

    /**
     * 退款
     * @link  https://pay.weixin.qq.com/wiki/doc/api/jsapi_sl.php?chapter=9_4
     *
     * @param string $payOrderId    支付id
     * @param int    $refundFee     退款总金额
     * @param int    $totalFee      订单总金额，单位为分
     * @param string $transactionId 微信生成的订单号，在支付通知中有返回
     *
     * @return bool
     *
     * @throw \Exception
     */
    public function refund($payOrderId, $refundFee, $totalFee, $transactionId)
    {
        if (!($this->cert_file_path && is_file($this->cert_file_path))) {
            throw new ArgumentException("证书文件 {$this->cert_file_path} 不存在");
        }
        if (!($this->key_file_path && is_file($this->key_file_path))) {
            throw new ArgumentException("密钥文件 {$this->key_file_path} 不存在");
        }

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
        $response = $this->post($this->toXml($data), 'https://api.mch.weixin.qq.com/secapi/pay/refund', true);

        $this->parseResponseResult($response);

        return true;
    }

    /**
     * 获取请求参数签名
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

    protected function parseResponseResult($data)
    {
        if (!$data || !isset($data['return_code'])) {
            throw new ArgumentException("参数无效");
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

        throw new ArgumentException($exceptionMessage);
    }

    /**
     * 输出xml字符
     *
     * @param array $data
     *
     * @return xml
     **/
    protected function toXml(array $data)
    {
        $xml = "<xml>";
        foreach ($data as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";

        return $xml;
    }

    /**
     * 数据提交
     *
     * @param      $xml
     * @param      $url
     * @param bool $useCert 是否使用证书
     * @param int  $second  支付超时设置
     *
     * @return array
     */
    protected function post($xml, $url, $useCert = false, $second = 30)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // 设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        // 要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        if ($useCert == true) {
            //设置证书
            //使用证书：cert 与 key 分别属于两个.pem文件
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLCERT, $this->cert_file_path);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLKEY, $this->key_file_path);
        }

        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

        if ($data = curl_exec($ch)) {
            curl_close($ch);

            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);

            throw new WeChatPayPostException($xml, $url, "curl出错，错误码:$error");
        }
    }
}