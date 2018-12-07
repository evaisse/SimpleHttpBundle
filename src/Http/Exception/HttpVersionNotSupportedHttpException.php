<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 17/06/15
 * Time: 10:54
 */

namespace evaisse\SimpleHttpBundle\Http\Exception;


class HttpVersionNotSupportedHttpException extends ServerErrorHttpException
{
    /**
     * Constructor.
     *
     * @param string     $message   The internal exception message
     * @param \Exception $previous  The previous exception
     * @param int        $code      The internal exception code
     */
    public function __construct($message = null, \Exception $previous = null, $code = 0)
    {
        parent::__construct(505, $message, $previous, array(), $code);
    }

}