<?php

namespace evaisse\SimpleHttpBundle\Http;

class ResponseException extends Exception
{

    /**
     * $response : Response object if provided
     *
     * @var ServiceResponse
     * @access protected
     */
    protected $response;

    /**
     * Set value for $response
     *
     * @param  ServiceResponse $value value to set to response
     * @return Object          instance for method chaining
     */
    public function setResponse(ServiceResponse $value)
    {
        $this->response = $value;

        return $this;
    }

    /**
     * Get value for $response
     * @return ServiceResponse Response object if provided
     */
    public function getResponse()
    {
        return $this->response;
    }

}
