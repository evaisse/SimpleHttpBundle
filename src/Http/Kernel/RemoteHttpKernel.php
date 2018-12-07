<?php

/*
 * (c) Darrell Hamilton <darrell.noice@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace evaisse\SimpleHttpBundle\Http\Kernel;

use Symfony\Component\HttpKernel\HttpKernelInterface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\HeaderBag;

use evaisse\SimpleHttpBundle\Curl\Request as CurlRequest;
use evaisse\SimpleHttpBundle\Curl\Collector\HeaderCollector;
use evaisse\SimpleHttpBundle\Curl\Collector\ContentCollector;
use evaisse\SimpleHttpBundle\Curl\CurlErrorException;
use evaisse\SimpleHttpBundle\Curl\RequestGenerator;


/**
 * RemoteHttpKernel utilizes curl to convert a Request object into a Response
 *
 * @author Darrell Hamilton <darrell.noice@gmail.com>
 */
class RemoteHttpKernel implements HttpKernelInterface
{
    /**
     * An instance of Curl\RequestGenerator for getting preconfigured
     * Curl\Request objects
     *
     * @var RequestGenerator
     */
    private $generator;

    private $lastCurlRequest;

    public function __construct(RequestGenerator $generator = null) {
        $this->generator = $generator;
    }


    /**
     * Handles a Request to convert it to a Response.
     *
     * When $catch is true, the implementation must catch all exceptions
     * and do its best to convert them to a Response instance.
     *
     * @param  Request $request A Request instance
     * @param  integer $type    The type of the request
     *                          (one of HttpKernelInterface::MASTER_REQUEST or HttpKernelInterface::SUB_REQUEST)
     * @param  Boolean $catch   Whether to catch exceptions or not
     *
     * @return Response A Response instance
     *
     * @throws \Exception When an Exception occurs during processing
     */
    public function handle(Request $request, $type = HttpKernelInterface::SUB_REQUEST, $catch = true) {
        try {
            return $this->handleRaw($request);
        } catch (\Exception $e) {
            if (false === $catch) {
                throw $e;
            }

            return $this->handleException($e, $request);
        }
    }

    
    private function handleException(\Exception $e, Request $request) {
        return new Response(
            $e->getMessage(),
            500
        );
    }

    private function getCurlRequest() {
        if(isset($this->generator)) {
            return $this->generator->getRequest();
        } else {
            return new CurlRequest();
        }
    }

    /**
     * Execute a Request object via cURL
     *
     * @param Request $request the request to execute
     * @param array $options additional curl options to set/override
     *
     * @return Response
     *
     * @throws CurlErrorException 
     */
    private function handleRaw(Request $request) {
        $curl = $this->lastCurlRequest = $this->getCurlRequest();
        $curl->setOptionArray(
            array(
                CURLOPT_URL=>$request->getUri(),
                CURLOPT_HTTPHEADER=>$this->buildHeadersArray($request->headers),
                CURLINFO_HEADER_OUT=>true
            )
        );

        $curl->setMethod($request->getMethod());

        if ("POST" === $request->getMethod()) {
            $this->setPostFields($curl, $request);
        }

        if("PUT" === $request->getMethod() && count($request->files->all()) > 0) {
            $file = current($request->files->all());

            $curl->setOptionArray(
                array(
                    CURLOPT_INFILE=>'@'.$file->getRealPath(),
                    CURLOPT_INFILESIZE=>$file->getSize()
                )
            );
        }

        $content = new ContentCollector();
        $headers = new HeaderCollector();

        // These options must not be tampered with to ensure proper functionality
        $curl->setOptionArray(
            array(
                CURLOPT_HEADERFUNCTION=>array($headers, "collect"),
                CURLOPT_WRITEFUNCTION=>array($content, "collect"),
            )
        );

        $curl->execute();

        $response = new Response(
            $content->retrieve(),
            $headers->getCode(),
            $headers->retrieve()
        );

        $response->setProtocolVersion($headers->getVersion());
        $response->setStatusCode($headers->getCode(), $headers->getMessage());

        return $response;
    }


    /**
     * Populate the POSTFIELDS option
     *
     * @param CurlRequest $curl cURL request object
     * @param Request $request the Request object we're populating
     */
    private function setPostFields(CurlRequest $curl, Request $request) {
        $postfields = null;
        $content = $request->getContent();

        if (!empty($content)) {
            $postfields = $content;
        } else if (count($request->request->all()) > 0) {
            $postfields = $request->request->all();
        }

        $curl->setOption(CURLOPT_POSTFIELDS, $postfields);
    }

    /**
     * Convert a HeaderBag into an array of headers appropriate for cURL
     *
     * @param HeaderBag $headerBag headers to parse
     *
     * @return array An array of header strings
     */
    private function buildHeadersArray(HeaderBag $headerBag) {
        return explode("\r\n",$headerBag);
    }

    public function getLastCurlRequest() {
        return $this->lastCurlRequest;
    }
}
