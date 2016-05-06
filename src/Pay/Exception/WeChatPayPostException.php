<?php

namespace Leo\Pay\Exception;

class WeChatPayPostException extends \RuntimeException
{
    private $postData;

    private $requestUri;

    public function __construct($data, $requestUri, $message, $code = null, Exception $previous = null)
    {
        $this->postData = $data;
        $this->requestUri = $requestUri;

        parent::__construct($message, $code, $previous);
    }

    public function getPostData()
    {
        return $this->postData;
    }

    public function getRequestUri()
    {
        return $this->requestUri;
    }
}