<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 17/06/15
 * Time: 09:40
 */

namespace evaisse\SimpleHttpBundle\Http\Exception;


use Exception;

class ForbiddenHttpException extends ClientErrorHttpException
{
    /**
     * Constructor.
     *
     * @param string     $message   The internal exception message
     * @param Exception|null $previous  The previous exception
     * @param int        $code      The internal exception code
     */
    public function __construct($message = null, ?Exception $previous = null, $code = 0)
    {
        parent::__construct(403, $message, $previous, array(), $code);
    }

}