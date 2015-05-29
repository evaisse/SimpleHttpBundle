<?php

/*
 * (c) Darrell Hamilton <darrell.noice@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace evaisse\SimpleHttpBundle\Http;

use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\HeaderBag;

use Zeroem\CurlBundle\Curl\Request as CurlRequest;
use Zeroem\CurlBundle\Curl\Collector\HeaderCollector;
use Zeroem\CurlBundle\Curl\Collector\ContentCollector;
use Zeroem\CurlBundle\Curl\CurlErrorException;
use Zeroem\CurlBundle\Curl\RequestGenerator;

use Zeroem\CurlBundle\Curl\MultiManager;
use Zeroem\CurlBundle\Curl\CurlEvents;
use Zeroem\CurlBundle\Curl\MultiInfoEvent;

use Zeroem\CurlBundle\HttpKernel\RemoteHttpKernel;


/**
 * RemoteHttpKernel utilizes curl to convert a Request object into a Response
 *
 * @author Darrell Hamilton <darrell.noice@gmail.com>
 */
class Kernel extends RemoteHttpKernel
{

    use ContainerAwareTrait;


    /**
     * An instance of Curl\RequestGenerator for getting preconfigured
     * Curl\Request objects
     *
     * @var RequestGenerator
     */
    protected $generator;

    /**
     * [$lastCurlRequest description]
     * @var resource curlRequest
     */
    protected $lastCurlRequest;


    /**
     * [$eventDispatcher description]
     * @var \Symfony\Component\EventDispatcher\EventDispatcher
     */
    protected $eventDispatcher;



    /**
     * [$requests description]
     * @var [type]
     */
    protected $services;


    public function __construct(ContainerInterface $container, RequestGenerator $generator = null) 
    {
        $this->setContainer($container);
        $this->generator = $generator;
        $this->setEventDispatcher(new \Symfony\Component\EventDispatcher\EventDispatcher());
        $this->getEventDispatcher()->addSubscriber($this->container->get('simple_http.profiler.data_collector'));
    }


    public function handleMultiInfoEvent(MultiInfoEvent $e)
    {
        $r = $e->getRequest();

        foreach ($this->services as $key => $value) {

            if ($value[1][0] === $r) {
                break;
            }

        }
        $requestType = HttpKernelInterface::SUB_REQUEST;

        $service = $value[0];
        $request = $service->getRequest();
        list($curlRequest, $contentCollector, $headersCollector) = $value[1];

        $this->updateRequestHeadersFromCurlInfos($request, $e->getRequest()->getInfo());

        if (!$headersCollector->getCode()) {

            $e = new CurlErrorException();

            $service->setError(new Exception\TransportException("CURL connection error", 1, $e));
                $event = new Event\GetResponseForExceptionEvent(
                    $this, 
                    $request, 
                    $requestType,
                    $service->getError());

            $this->getEventDispatcher()->dispatch(KernelEvents::EXCEPTION, $event);

        } else {

            $response = new Response(
                $contentCollector->retrieve(),
                $headersCollector->getCode(),
                $headersCollector->retrieve()
            );

            foreach ($headersCollector->getCookies() as $cookie) {
                $response->headers->setCookie($cookie);
            }


            $response->setProtocolVersion($headersCollector->getVersion());
            $response->setStatusCode($headersCollector->getCode(), $headersCollector->getMessage());

            $response->setTransferInfos($e->getRequest()->getInfo());

            $event = new Event\FilterResponseEvent($this, $request, $requestType, $response);
            $this->getEventDispatcher()->dispatch(KernelEvents::RESPONSE, $event);


            /*
                populate response for service
             */
            $service->setResponse($response);

            $event = new Event\PostResponseEvent($this, $request, $response);
            $this->getEventDispatcher()->dispatch(KernelEvents::TERMINATE);

        }

    }

    /**
     * handle multi curl
     * @param array $services a list of Service instances
     * @return HttpKernel current httpkernel for method chaining
     */
    public function execute(array $services)
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            CurlEvents::MULTI_INFO,
            array($this,"handleMultiInfoEvent")
        );

        $mm = new MultiManager($dispatcher, false);

        $this->services = array();

        foreach ($services as $service) {

            $request = $service->getRequest();
            $requestType = HttpKernelInterface::SUB_REQUEST;

            try {

                $event = new Event\GetResponseEvent($this, $request, $requestType);
                $this->getEventDispatcher()->dispatch(KernelEvents::REQUEST, $event);

                $prepared = $this->prepareRawCurlHandler($request, $requestType, false);

                $mm->addRequest($prepared[0]);

                $this->services[] = array(
                    $service,
                    $prepared,
                );

            } catch (CurlErrorException $e) {
                $service->setError(new Exception\TransportException("CURL connection error", 1, $e));
                $event = new Event\GetResponseForExceptionEvent(
                    $this, $request, 
                    $requestType,
                    $service->getError());
                $this->getEventDispatcher()->dispatch(KernelEvents::EXCEPTION, $event);
                continue;
            }

        }


        $mm->execute();
        
        // for the "non blocking" multi manager, we need to trigger the destructor
        unset($mm);


        return $this;

    }

    /**
     * Handles a Request to convert it to a Response.
     *
     * When $catch is true, the implementation must catch all exceptions
     * and do its best to convert them to a Response instance.
     *
     * @param  HttpRequest $request A Request instance
     * @param  integer     $type    The type of the request
     *                              (one of HttpKernelInterface::MASTER_REQUEST or HttpKernelInterface::SUB_REQUEST)
     * @param  Boolean     $catch   Whether to catch exceptions or not
     *
     * @return Response A Response instance
     *
     * @throws \Exception When an Exception occurs during processing
     */
    public function handle(HttpRequest $request, $type = HttpKernelInterface::SUB_REQUEST, $catch = true) 
    {
        try {
            return $this->handleRaw($request);
        } catch (\Exception $e) {
            if (false === $catch) {
                throw $e;
            }
            return $this->handleException($e, $request);
        }
    }

    
    /**
     * 
     * @param  \Exception  $e       [description]
     * @param  HttpRequest $request [description]
     * @return Response http response 
     */
    private function handleException(\Exception $e, HttpRequest $request) 
    {
        return new Response(
            $e->getMessage(),
            500
        );
    }


    /**
     * Get generated curl request
     * @return CurlRequest generated curl request
     */
    protected function getCurlRequest() 
    {
        if (isset($this->generator)) {
            return $this->generator->getRequest();
        } else {
            return new CurlRequest();
        }
    }

    /**
     * Execute a Request object via cURL
     *
     * @param HttpRequest $request the request to execute
     * @param array $options additional curl options to set/override
     *
     * @return Response
     *
     * @throws CurlErrorException 
     */
    private function prepareRawCurlHandler(HttpRequest $request) 
    {
        $curl = $this->lastCurlRequest = $this->getCurlRequest();

        $curl->setOptionArray(array(
            CURLOPT_URL         => $request->getUri(),
            CURLOPT_HTTPHEADER  => $this->buildHeadersArray($request->headers),
            CURLOPT_COOKIE      => $this->buildCookieString($request->cookies),
            CURLINFO_HEADER_OUT => true,
        ));

        $curl->setMethod($request->getMethod());

        if ("POST" === $request->getMethod()) {
            $this->setPostFields($curl, $request);
        }

        if ("PUT" === $request->getMethod() && count($request->files->all()) > 0) {
            $file = current($request->files->all());

            $curl->setOptionArray(array(
                CURLOPT_INFILE     => '@' . $file->getRealPath(),
                CURLOPT_INFILESIZE => $file->getSize(),
            ));
        }

        $content = new ContentCollector();
        $headers = new CurlHeaderCollector();

        // These options must not be tampered with to ensure proper functionality
        $curl->setOptionArray(
            array(
                CURLOPT_HEADERFUNCTION => array($headers, "collect"),
                CURLOPT_WRITEFUNCTION  => array($content, "collect"),
            )
        );

        return array(
            $curl, $content, $headers
        );

        // $curl->execute();

        // return $response;
    }


    /**
     * Populate the POSTFIELDS option
     *
     * @param CurlRequest $curl cURL request object
     * @param Request $request the Request object we're populating
     */
    private function setPostFields(CurlRequest $curl, HttpRequest $request) {
        $postfields = null;
        $content = $request->getContent();


        if (!empty($content)) {
            $postfields = $content;
        } else if (count($request->request->all()) > 0) {
            $postfields = http_build_query($request->request->all());
        }

        $curl->setOption(CURLOPT_POSTFIELDS, $postfields);
    }

    /**
     * @param ParameterBag $cookiesBag
     *
     * @return string
     */
    protected function buildCookieString(ParameterBag $cookiesBag) {
        $cookies = [];

        foreach ($cookiesBag as $key => $value) {
            $cookies[] = "$key=$value";
        }

        return join(';', $cookies);
    }


    /**
     * @param Request $request
     * @param array $curlInfo
     */
    protected function updateRequestHeadersFromCurlInfos(Request $request, array $curlInfo)
    {
        $headers = explode("\r\n", $curlInfo['request_header']);
        array_shift($headers);
        $replacementsHeaders = array();
        foreach ($headers as $header) {
            if (strpos($header, ':')) {
                list($k, $v) = explode(':', $header, 2);
                $v = trim($v);
                $k = trim($k);
                $replacementsHeaders[$k] = $v;
            }
        }
        $request->headers->replace($replacementsHeaders);
    }

    /**
     * Convert a HeaderBag into an array of headers appropriate for cURL
     *
     * @param HeaderBag $headerBag headers to parse
     *
     * @return array An array of header strings
     */
    private function buildHeadersArray(HeaderBag $headerBag) {
        return explode("\r\n", $headerBag);
    }

    public function getLastCurlRequest() {
        return $this->lastCurlRequest;
    }

    /**
     * Gets the [$eventDispatcher description].
     *
     * @return \Symfony\Component\EventDispatcher\EventDispatcher
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * Sets the [$eventDispatcher description].
     *
     * @param \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher the event dispatcher
     *
     * @return self
     */
    protected function setEventDispatcher(EventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }
}