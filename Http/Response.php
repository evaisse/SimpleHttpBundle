<?php

namespace evaisse\SimpleHttpBundle\Http;


use evaisse\SimpleHttpBundle\Http\Exception\HttpClientError;
use evaisse\SimpleHttpBundle\Http\Exception\HttpServerError;
use evaisse\SimpleHttpBundle\Http\Exception\InvalidResponseBodyException;
use evaisse\SimpleHttpBundle\Http\Exception\ServiceErrorException;
use \Symfony\Component\HttpFoundation\Response as HttpResponse;

class Response extends \Symfony\Component\HttpFoundation\Response
{
 
    /**
     * Service response result var
     * @var mixed
     */
    protected $result;

    /**
     * @var
     */
    protected $hasParsedResult;



    /**
     * [$error description]
     * @var Error
     */
    protected $error;

    /**
     * Raw transfer infos (for cURL)
     * @var array
     */
    protected $transferInfos = array();

    /**
     * Create a new HTTP response object
     *
     * @param string  $content content string
     * @param integer $status  http status code
     * @param array   $headers A hash of HTTP headers
     */
    public function __construct($content = '', $status = 200, array $headers = array())
    {
        parent::__construct($content, $status, $headers);
        $this->parseResponse();
    }

    /**
     * Parse response body and extract results
     */
    protected function parseResponse()
    {

        if ($this->getStatusCode() >= 500) {
            $message = HttpResponse::$statusTexts[$this->getStatusCode()];
            $this->error = new HttpServerError($this, $message, $this->getStatusCode());
        } else if ($this->getStatusCode() >= 400) {
            $message = HttpResponse::$statusTexts[$this->getStatusCode()];
            $this->error = new HttpClientError($this, $message, $this->getStatusCode());
        }

        if ($this->headers->get('content-type') === "application/json") {
            $result = json_decode($this->getContent(), true);
            if (json_last_error()) {
                $this->error = new InvalidResponseBodyException($this, 'Invalid Json response body. ' . json_last_error_msg());
            }
            $this->setResult($result);
        }
    }

    /** 
     * 
     */
    public function hasError()
    {
        return !!$this->error;
    }


    /**
     * @return Exception 
     */
    public function getError()
    {
        return $this->error;
    }



    /**
     * Get service response result var
     * @return mixed get service response result var
     */
    public function getResult()
    {
        if ($this->hasParsedResult === null) {
            $this->hasParsedResult = false;
            $this->parseResponse();
        }

        if ($this->hasParsedResult) {
            return $this->result;
        } else {
            return $this->content;
        }
    }

    /**
     * @param mixed $result parsed result
     * @return self
     */
    public function setResult($result)
    {
        $this->result = $result;
        $this->hasParsedResult = true;
        return $this;
    }

    /**
     * Gets the Raw transfer infos (for cURL).
     *
     * @return array
     */
    public function getTransferInfos()
    {
        return $this->transferInfos;
    }

    /**
     * Sets the Raw transfer infos (for cURL).
     *
     * @param array $transferInfos the transfer infos
     */
    public function setTransferInfos(array $transferInfos)
    {
        $this->transferInfos = $transferInfos;
    }
}