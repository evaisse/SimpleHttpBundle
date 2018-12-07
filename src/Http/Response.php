<?php

namespace evaisse\SimpleHttpBundle\Http;

use evaisse\SimpleHttpBundle\Http\Exception\ErrorHttpException;
use evaisse\SimpleHttpBundle\Http\Exception\InvalidResponseBodyException;

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
        if ($status < 100 || $status >= 600) {
            $status = 580;
        } else {
            $status = (int)$status;
        }

        parent::__construct($content, $status, $headers);
        $this->parseResponse();
    }

    /**
     * Parse response body and extract results
     */
    protected function parseResponse()
    {
        if ($this->getStatusCode() >= 400) {
            $this->error = ErrorHttpException::createHttpException($this);
        }

        if (fnmatch("application/json*", $this->headers->get('content-type'))) {
            $result = json_decode($this->getContent(), true);
            if (json_last_error()) {
                $this->error = new InvalidResponseBodyException($this, 'Invalid Json response body. ' . $this->getJsonLastErrorMessage());
            }
            $this->setResult($result);
        }
    }


    /**
     * wrap json_last_error_msg() function for php<5.5 versions
     *
     * @return string last json decoding/encoding error
     */
    protected function getJsonLastErrorMessage()
    {
        if (function_exists('json_last_error_msg')) {
            return json_last_error_msg();
        }

        static $errors = array(
            JSON_ERROR_NONE             => null,
            JSON_ERROR_DEPTH            => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH   => 'Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR        => 'Unexpected control character found',
            JSON_ERROR_SYNTAX           => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8             => 'Malformed UTF-8 characters, possibly incorrectly encoded'
        );

        $error = json_last_error();
        return array_key_exists($error, $errors) ? $errors[$error] : "Unknown error ({$error})";
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