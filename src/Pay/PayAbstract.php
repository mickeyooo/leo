<?php
namespace Leo\Pay;

abstract class PayAbstract
{
    /**
     * 生成待签名字符串
     *
     * @param array $queryParams
     *
     * @return string
     */
    protected function buildSignQueryString(array $queryParams)
    {
        $queryParams = array_filter($queryParams, function($p) {
            return $p !== '';
        });
        ksort($queryParams);

        return urldecode(http_build_query($queryParams));
    }

    /**
     * 生成nonce
     *
     * @param int $length
     *
     * @return string
     */
    public static function buildNonce($length = 8)
    {
        $nonce = '';

        for ($i = 0; $i < $length; $i++) {
            $nonce .= chr(mt_rand(1, 100) % 2 ? mt_rand(65, 90) : mt_rand(97, 122));
        }

        return $nonce;
    }

}
