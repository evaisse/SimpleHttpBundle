<?php

namespace evaisse\SimpleHttpBundle\Http;


use evaisse\SimpleHttpBundle\Http\Error\InvalidResponseError;
use evaisse\SimpleHttpBundle\Http\Exception\InvalidJsonResponseException;
use evaisse\SimpleHttpBundle\Http\Exception\ServiceErrorException;

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
     * [__construct description]
     * @param string  $content [description]
     * @param integer $status  [description]
     * @param array   $headers [description]
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
        if ($this->headers->get('content-type') === "application/json") {
            $result = json_decode($this->getContent(), true);
            if (json_last_error()) {
                $this->error = new InvalidResponseError('Invalid Json response body');
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