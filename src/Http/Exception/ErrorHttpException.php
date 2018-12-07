<?php
/**
 * base HTTP error for return codes
 * User: evaisse
 * Date: 04/06/15
 */
namespace evaisse\SimpleHttpBundle\Http\Exception;

use evaisse\SimpleHttpBundle\Http\Response;

use Symfony\Component\HttpKernel\Exception\HttpException;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\LengthRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

abstract class ErrorHttpException extends HttpException
{

    /**
     * @param Response $response a response to build the foundation exception
     *
     * @return \Symfony\Component\HttpKernel\Exception\HttpException exception for given http error
     */
    public static function createHttpException(Response $response)
    {
        /*
         * We create a standard Response exception to forward exception response content if necessary
         */
        $previous = new ResponseException($response, "Http Error : " . $response->getStatusCode(), 0);

        switch ($response->getStatusCode()) {
            case 0:
                $e = new InternalServerErrorHttpException("Unknow error code ".$response->getStatusCode(), $previous);
                break;
            case 400:
                $e = new BadRequestHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 401:
                $e = new UnauthorizedHttpException($response->headers->get('WWW-Authenticate'), $response->getContent(), $previous, $response->getStatusCode());
                break;
            case 403:
                $e = new ForbiddenHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 404:
                $e = new NotFoundHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 405:
                $allowMethods = explode(',', $response->headers->get('allow'));
                foreach ($allowMethods as $k => $m) {
                    $allowMethods[$k] = trim(strtoupper($m));
                }
                $e = new MethodNotAllowedHttpException($allowMethods, $response->getContent(), $previous, $response->getStatusCode());
                break;
            case 406:
                $e = new NotAcceptableHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 407:
                $e = new ProxyAuthenticationRequiredHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 408:
                $e = new RequestTimeoutHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 409:
                $e = new ConflictHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 410:
                $e = new GoneHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 411:
                $e = new LengthRequiredHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 412:
                $e = new PreconditionFailedHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 413:
                $e = new RequestEntityTooLargeHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 414:
                $e = new RequestUriTooLongHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 415:
                $e = new UnsupportedMediaTypeHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 416:
                $e = new RequestedRangeNotSatisfiableHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 417:
                $e = new ExpectationFailedHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 500:
                $e = new InternalServerErrorHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 501:
                $e = new NotImplementedHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 502:
                $e = new BadGatewayHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 503:
                $e = new ServiceUnavailableHttpException($response->headers->get('Retry-After'), $response->getContent(), $previous, $response->getStatusCode());
                break;
            case 504:
                $e = new GatewayTimeoutHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            case 505:
                $e = new HttpVersionNotSupportedHttpException($response->getContent(), $previous, $response->getStatusCode());
                break;
            default:
                $e = $response->getStatusCode() >= 500
                   ? new InternalServerErrorHttpException("Unknow error code ".$response->getStatusCode(), $previous)
                   : new BadRequestHttpException("Unknow error code ".$response->getStatusCode(), $previous);
                break;
        }

        return $e;

    }


}