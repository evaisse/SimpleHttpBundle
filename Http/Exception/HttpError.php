<?php
/**
 * base HTTP error for return codes
 * User: evaisse
 * Date: 04/06/15
 */

namespace evaisse\SimpleHttpBundle\Http\Exception;


abstract class HttpError extends ResponseException
{

    /**
     * @return \Symfony\Component\HttpKernel\Exception\HttpException exception for given http error
     */
    public function createHttpFoundationException()
    {
        $message = HttpResponse::$statusTexts[$this->getStatusCode()];

        $cls = str_replace(" ", "", $message) . "HttpException";
        $cls = "\\Symfony\\Component\\HttpKernel\\Exception\\" . $cls;

        if (!class_exists($cls)) {
            $cls = "\\Symfony\\Component\\HttpKernel\\Exception\\HttpException";
        }

        return new $cls($this->getStatusCode(), $this->getResponse()->getContent(), $this, $this->getResponse()->headers->all());

    }
}