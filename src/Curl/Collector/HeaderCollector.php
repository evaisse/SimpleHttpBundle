<?php

namespace evaisse\SimpleHttpBundle\Curl\Collector;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\BrowserKit\Cookie as CookieParser;

class HeaderCollector implements CollectorInterface
{

    /**
     * @var string[] headers list
     */
    protected $headers = array();

    /**
     * @var string http version string like "1.1"
     */
    protected $version;

    /**
     * @var int http status code
     */
    protected $code;

    /**
     * @var string status message
     */
    protected $message;

    /**
     * @var string[] A list of prepended headers that give infos about transaction process
     * (i.e. HTTP/1.1 100 Continue, HTTP/1.1 200 Connection established)
     */
    protected $transactionHeaders = [];

    /**
     * @var Cookie[]
     */
    protected $cookies = [];

    /**
     * @var string
     */
    protected $rawHeaders = "";

    /**
     * @return int
     */
    public function collect() {

        list($handle, $headerString) = func_get_args();

        $this->rawHeaders .= $headerString;

        $cleanHeader = trim($headerString);

        // The HTTP/X.X XXX XXX header is also passed through this function
        // and must be parsed differently than the other HTTP headers
        if (!$this->parseHttpVersionheader($cleanHeader)) {
            $this->parseHeader($cleanHeader);
        }

        return strlen($headerString);
    }

    /**
     * handle the 100 continue & 200 Connection establish code
     * by stripping any extra headers directive before the latest one.
     *
     * e.g. "HTTP\/1.1 100 Continue\r\n\r\nHTTP\/1.1 200 Connection established\r\n\r\nHTTP\/1.1 200 OK\r\nContent-Type: application\/json; charset=utf-8\r\nDate: Mon, 17 Oct 2016 14:59:22 GMT\r\nExpires: Mon, 17 Oct 2016 14:59:22 GMT\r\nCache-Control: private, max-age=0\r\nX-Content-Type-Options: nosniff\r\nX-XSS-Protection: 1; mode=block\r\nServer: GSE\r\nAlt-Svc: quic=\":443\"; ma=2592000; v=\"36,35,34,33,32\"\r\nAccept-Ranges: none\r\nVary: Accept-Encoding\r\nTransfer-Encoding: chunked\r\n\r\n{\n??\"success\": true,\n??\"challenge_ts\": \"2016-10-17T14:59:12Z\",\n??\"hostname\": \"clients.boursorama.com\"\n}"
     *
     * let's celebrate : https://httpstatusdogs.com/100-continue
     * @param string $header
     */
    protected function parseHttpVersionheader($header)
    {
        if (!preg_match('/^http\/(\d+\.\d+)\s+(\d+)\s+(.+)/i', $header, $r)) {
            return;
        }

        $this->transactionHeaders[] = $header;

        // reset headers records since another transaction header has started, but keep cookies for redirections
        $this->headers = [];



        $this->version = $r[1];
        $this->code = (int)$r[2];
        $this->message = trim($r[3]);
    }

    /**
     * Parse the standard `Header-name: value' headers into
     * individual header name/value pairs
     *
     * @param string $header
     */
    protected function parseHeader($header)
    {

        // skip empty headers lines
        if (empty($header)) {
            return;
        }

        if (!preg_match('/([a-z0-9][a-z0-9\-]*)\:\s*(.*)/i', $header, $h)) {
            return;
        }

        $name = str_replace(' ', '-', ucwords(str_replace('-', ' ', strtolower($h[1]))));
        $value = $h[2];

        if (strtolower($name) == "set-cookie") {

            try {
                $cookie = CookieParser::fromString($value);
            } catch (\InvalidArgumentException $e) {
                // skip invalid cookie line
                return;
            }

            $this->cookies[] = new Cookie(
                $cookie->getName(),
                $cookie->getRawValue(),
                (int)$cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain(),
                $cookie->isSecure(),
                $cookie->isHttpOnly()
            );

        } else {
            $this->headers[$name] = $value;
        }

    }

    /**
     * @return string[]
     */
    public function retrieve()
    {
        return $this->headers;
    }


    /**
     * @return Cookie[] get a list of Cookie instances
     */
    public function getCookies()
    {
        return $this->cookies;
    }

    /**
     * @return string[]
     */
    public function getTransactionHeaders()
    {
        return $this->transactionHeaders;
    }

    /**
     * @return string[]
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return string
     */
    public function getAllTransactionHeaders()
    {
        return $this->rawHeaders;
    }

}
