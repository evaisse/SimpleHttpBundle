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
        parent::__construct(0, $message, $previous, array(), $code);
    }
}
