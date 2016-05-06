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
        $queryParams = array_filter($queryParams);
        ksort($queryParams);

        return urldecode(http_build_query($queryParams));
    }

}
