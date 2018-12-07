<?php

namespace evaisse\SimpleHttpBundle\Http\Exception;

use evaisse\SimpleHttpBundle\Http\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class TransportException extends HttpException
{

    /**
     * @param string $message
     * @param int $code
     * @param \Exception $previous
     */
    public function __construct($message = null, $code = 0, \Exception $previous = null)
    {
        /*
         * @see https://en.wikipedia.org/wiki/List_of_HTTP_status_codes
         */
        parent::__construct(599, $message, $previous, array(), $code);
    }
}
