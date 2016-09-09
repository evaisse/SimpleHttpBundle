<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 19/05/15
 * Time: 10:15
 */

namespace evaisse\SimpleHttpBundle\Curl;

use evaisse\SimpleHttpBundle\Curl\Collector\HeaderCollector;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\BrowserKit\Cookie as CookieParser;

class CurlHeaderCollector extends HeaderCollector
{

    private $version;
    private $code;
    private $message;

    private $headers = array();
    private $cookies = array();

    public function collect() {

        list($handle, $headerString) = func_get_args();

        $cleanHeader = trim($headerString);

        // The HTTP/1.0 200 OK header is also passed through this function
        // and must be parsed differently than the other HTTP headers
        if(false !== stripos($cleanHeader,"http/")) {
            $this->parseHttp($cleanHeader);
        } else {
            $this->parseHeader($cleanHeader);
        }

        return strlen($headerString);
    }

    /**
     * Parse the `HTTP/1.0 200 OK' header into the proper
     * Status Code/Message and Protocol Version fields
     *
     * @param string $header
     */
    private function parseHttp($header) {
        list($version,$code,$message) = explode(" ", $header);

        $versionParts = explode("/",$version);
        $this->version = end($versionParts);
        $this->code = $code;
        $this->message = $message;
    }

    /**
     * Parse the standard `Header-name: value' headers into
     * individual header name/value pairs
     *
     * @param string $header
     */
    private function parseHeader($header) {


        if (empty($header)) {
            return;
        }

        $pos = strpos($header, ": ");

        if (false !== $pos) {

            $name = trim(substr($header, 0, $pos));
            $value = substr($header, $pos+2);

            if (strtolower($name) == "set-cookie") {

                $cookie = CookieParser::fromString($value);
                $this->cookies[] = new Cookie($cookie->getName(),
                    $cookie->getRawValue(),
                    (int)$cookie->getExpiresTime(),
                    $cookie->getPath(),
                    $cookie->getDomain(),
                    $cookie->isSecure(),
                    $cookie->isHttpOnly());

            } else {

                $this->headers[$name] = $value;

            }
        }

    }

    public function retrieve() {
        return $this->headers;
    }

    public function getVersion() {
        return $this->version;
    }

    public function getMessage() {
        return $this->message;
    }

    public function getCode() {
        return $this->code;
    }

    /**
     * @return array|Cookie get a list of Cookie instances
     */
    public function getCookies()
    {
        return $this->cookies;
    }



}