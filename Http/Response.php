<?php

namespace evaisse\SimpleHttpBundle\Http;


use evaisse\SimpleHttpBundle\Http\Exception\HttpClientError;
use evaisse\SimpleHttpBundle\Http\Exception\HttpServerError;
use evaisse\SimpleHttpBundle\Http\Exception\InvalidResponseBodyException;
use evaisse\SimpleHttpBundle\Http\Exception\ServiceErrorException;
use \Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

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
        if ($this->getStatusCode() >= 400) {

            switch ($this->getStatusCode()) {
                case 400:
                    $e = new BadRequestHttpException($this->getContent(), null, $this->getStatusCode());
                    break;
                case 401:
                    $e = new UnauthorizedHttpException("", $this->getContent(), null, $this->getStatusCode());
                    break;
                case 404:
                    $e = new NotFoundHttpException($this->getContent(), null, $this->getStatusCode());
                    break;
                default:
                    $e = new HttpException($this->getStatusCode(), $this->getContent(), null, $this->headers->all());
                    break;
            }

            $this->error = $e;
        }

        if ($this->headers->get('content-type') === "application/json") {
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