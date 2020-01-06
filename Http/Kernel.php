<?php

/*
 * (c) Darrell Hamilton <darrell.noice@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace evaisse\SimpleHttpBundle\Http;


use evaisse\SimpleHttpBundle\Http\Exception\CurlTransportException;
use evaisse\SimpleHttpBundle\Http\Exception\HostNotFoundException;
use evaisse\SimpleHttpBundle\Http\Exception\ClientErrorHttpException;
use evaisse\SimpleHttpBundle\Http\Exception\HttpError;
use evaisse\SimpleHttpBundle\Http\Exception\ServerErrorHttpException;
use evaisse\SimpleHttpBundle\Http\Exception\SslException;
use evaisse\SimpleHttpBundle\Http\Exception\TimeoutException;
use evaisse\SimpleHttpBundle\Http\Exception\TransportException;


use evaisse\SimpleHttpBundle\Http\Kernel\RemoteHttpKernel;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\ParameterBag;

use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\HeaderBag;

use evaisse\SimpleHttpBundle\Curl\Request as CurlRequest;
use evaisse\SimpleHttpBundle\Curl\CurlHeaderCollector;
use evaisse\SimpleHttpBundle\Curl\Collector\ContentCollector;
use evaisse\SimpleHttpBundle\Curl\CurlErrorException;
use evaisse\SimpleHttpBundle\Curl\RequestGenerator;

use evaisse\SimpleHttpBundle\Curl\MultiManager;
use evaisse\SimpleHttpBundle\Curl\CurlEvents;
use evaisse\SimpleHttpBundle\Curl\MultiInfoEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;


/**
 * RemoteHttpKernel utilizes curl to convert a Request object into a Response
 *
 * @author Darrell Hamilton <darrell.noice@gmail.com>
 */
class Kernel extends RemoteHttpKernel
{

    use ContainerAwareTrait;


    /**
     * @var RequestGenerator An instance of Curl\RequestGenerator for getting preconfigured Curl\Request objects
     */
    protected $generator;


    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;


    /**
     * @var array a statement pool that contains statements during multicurl
     */
    protected $stmts;


    /**
     * @var string a tmp cookie filepath
     */
    protected $tmpCookieFile;


    /**
     * @var Statement[]
     */
    protected $services = [];

    /**
     * @param ContainerInterface $container a container interface
     * @param RequestGenerator $generator Optionnal generator to construct curlrequest
     */
    public function __construct(ContainerInterface $container, RequestGenerator $generator = null) 
    {
        $this->setContainer($container);
        $this->generator = $generator;
        $this->setEventDispatcher(new EventDispatcher());

        if ($this->container->has('simple_http.profiler.data_collector')) {
            $this->getEventDispatcher()->addSubscriber($this->container->get('simple_http.profiler.data_collector'));
        }
    }

    /**
     * @param MultiInfoEvent $e
     */
    public function handleMultiInfoEvent(MultiInfoEvent $e)
    {
        $r = $e->getRequest();

        if (empty($this->services)) {
            return;
        }

        $value = [];
        foreach ($this->services as $key => $value) {
            if ($value[1][0] === $r) {
                break;
            }
        }

        $requestType = HttpKernelInterface::SUB_REQUEST;


        $stmt = $value[0];
        $request = $stmt->getRequest();

        list($curlRequest, $contentCollector, $headersCollector) = $value[1];

        $this->updateRequestHeadersFromCurlInfos($request, $e->getRequest()->getInfo());

        if (!$headersCollector->getCode() || $e->getInfo()->getResult() !== CURLE_OK) {

            /*
             * Here we need to use return code from multi event because curl_errno return invalid results
             */
            $error = new CurlTransportException(curl_error($curlRequest->getHandle()), $e->getInfo()->getResult());

            $error = $error->transformToGenericTransportException();

            $stmt->setError($error);

            $event = new Event\ExceptionEvent(
                    $this,
                    $request,
                    $requestType,
                    $error);

            $this->getEventDispatcher()->dispatch($event, KernelEvents::EXCEPTION);

        } else {

            $response = new Response(
                $contentCollector->retrieve(),
                (int)$headersCollector->getCode(),
                $headersCollector->retrieve()
            );

            foreach ($headersCollector->getCookies() as $cookie) {
                $response->headers->setCookie($cookie);
            }

            $response->setProtocolVersion($headersCollector->getVersion());
            $response->setStatusCode($headersCollector->getCode(), $headersCollector->getMessage());

            $response->setTransferInfos(array_merge($e->getRequest()->getInfo(), [
               'additionnalProxyHeaders' =>  $headersCollector->getTransactionHeaders()
            ]));

            $event = new Event\ResponseEvent($this, $request, $requestType, $response);
            $this->getEventDispatcher()->dispatch($event, KernelEvents::RESPONSE);

            /*
                populate response for service
             */
            $stmt->setResponse($response);

            $event = new Event\TerminateEvent($this, $request, $response);
            $this->getEventDispatcher()->dispatch($event, KernelEvents::TERMINATE);

        }

    }

    /**
     * handle multi curl
     * @param Statement[] $stmts a list of Service instances
     * @return HttpKernel current httpkernel for method chaining
     */
    public function execute(array $stmts)
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            CurlEvents::MULTI_INFO,
            array($this,"handleMultiInfoEvent")
        );

        $mm = new MultiManager($dispatcher, false);

        $this->services = array();

        foreach ($stmts as $stmt) {

            $request = $stmt->getRequest();
            $requestType = HttpKernelInterface::SUB_REQUEST;

            try {

                $event = new Event\RequestEvent($this, $request, $requestType);
                $this->getEventDispatcher()->dispatch($event, KernelEvents::REQUEST);

                list($curlHandler, $contentCollector, $headerCollector) = $this->prepareRawCurlHandler($stmt);

                $mm->addRequest($curlHandler);

                $this->services[] = [
                    $stmt,
                    [$curlHandler, $contentCollector, $headerCollector],
                ];

            } catch (CurlErrorException $e) {

                $stmt->setError(new Exception\CurlTransportException("CURL connection error", 1, $e));

                $event = new Event\ExceptionEvent(
                    $this, $request, 
                    $requestType,
                    $stmt->getError());
                $this->getEventDispatcher()->dispatch($event, KernelEvents::EXCEPTION);
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
            $stmt = new Statement($request);

            $this->execute([
                $stmt
            ]);

            if ($stmt->hasError()) {
                if ($stmt->getError() instanceof HttpError) {
                    throw $stmt->getError()->createHttpFoundationException();
                } else {
                    throw $stmt->getError();
                }
            }

            return $stmt->getResponse();

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
    protected function handleException(\Exception $e, HttpRequest $request) 
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
     * @param Statement $stmt the request to execute
     *
     * @return Response
     *
     * @throws CurlErrorException 
     */
    protected function prepareRawCurlHandler(Statement $stmt)
    {
        $request = $stmt->getRequest();

        $curl = $this->getCurlRequest();

        $curl->setOptionArray(array(
            CURLOPT_URL            => $request->getUri(),
            CURLOPT_COOKIE         => $this->buildCookieString($request->cookies),
            CURLINFO_HEADER_OUT    => true,
            CURLOPT_FOLLOWLOCATION => true,
        ));

        // Set timeout
        if ($stmt->getTimeout() !== null) {
            $curl->setOption(CURLOPT_TIMEOUT_MS, $stmt->getTimeout());
        }

        if ($stmt->getIgnoreSslErrors()) {
            $curl->setOptionArray([
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
        }


        if ($request->getMethod() != "GET") {
            /*
             * When body is sent as a raw string, we need to use customrequest option
             */
            $curl->setOption(CURLOPT_CUSTOMREQUEST, $request->getMethod());
            $this->setPostFields($curl, $request);

        } else {
            /*
             * Classic case
             */
            $curl->setMethod($request->getMethod());

        }

        $content = new ContentCollector();
        $headers = new CurlHeaderCollector();

        // These options must not be tampered with to ensure proper functionality
        $curl->setOptionArray(
            array(
                CURLOPT_HTTPHEADER     => $this->buildHeadersArray($request->headers),
                CURLOPT_HEADERFUNCTION => array($headers, "collect"),
                CURLOPT_WRITEFUNCTION  => array($content, "collect"),
            )
        );

        return array(
            $curl, $content, $headers
        );
    }


    /**
     * Create a cURL file object from a given set of params,
     * if version >= 5.5, CURLFile instance will be return, otherwise a string resource
     *
     * @param string $filename realpath to file
     * @param string $mimetype mime content type
     * @param string $postname base name for file
     *
     * @throws \InvalidArgumentException
     *
     * @return mixed|string|CURLFile if version >= 5.5, CURLFile instance will be return, otherwise a string resource
     */
    protected function createCurlFile($filename, $mimetype, $postname = null)
    {
        if (!realpath($filename) && is_file($filename)) {
            throw new \InvalidArgumentException('invalid given filepath : ' . $filename);
        }

        if (function_exists('curl_file_create')) {
            return new \CURLFile($filename, $mimetype, $postname);
        }

        $postname = $postname ? $postname : basename($filename);
        return "@$filename;filename=$postname;type=$mimetype";
    }

    /**
     * Populate the POSTFIELDS option
     *
     * @param CurlRequest $curl cURL request object
     * @param Request $request the Request object we're populating
     */
    protected function setPostFields(CurlRequest $curl, HttpRequest $request)
    {
        $postfields = null;
        $content = $request->getContent();

        if (!empty($content)) {
            $postfields = $content;
            $a = 1;
        } else if (count($request->files)) {
            // Add files to postfields as curl resources
            foreach ($request->files->all() as $key => $file) {
                $file = $this->createCurlFile($file->getRealPath(), $file->getMimeType(), basename($file->getClientOriginalName()));
                $request->request->set($key, $file);
            }
            $postfields = $request->request->all();
            // we need to manually set content-type
            $request->headers->set('Content-Type', "multipart/form-data");
            $a = 2;
        } else if (count($request->request)) {
            $postfields = http_build_query($request->request->all());
            $a = 3;
        } else {
            return;
        }


        if (is_string($postfields)) {
            $curl->setOption(CURLOPT_POSTFIELDS, $postfields);
            $request->headers->set('content-length', strlen($postfields));
        } else {
            $curl->setOption(CURLOPT_POSTFIELDS, $postfields);
        }
    }

    /**
     * @param ParameterBag $cookiesBag
     *
     * @return string
     */
    protected function buildCookieString(ParameterBag $cookiesBag)
    {
        $cookies = [];

        foreach ($cookiesBag as $key => $value) {
            $cookies[] = "$key=$value";
        }

        return join(';', $cookies);
    }


    /**
     * Some headers like user-agent can be overrided by curl so we need to re-fetch postward the headers sent
     * to reset them in the original request object
     *
     * @param Request $request request object after being sent
     * @param array $curlInfo curl info needed to update the request object with final curl headers sent
     */
    protected function updateRequestHeadersFromCurlInfos(Request $request, array $curlInfo)
    {
        if (!isset($curlInfo['request_header'])) {
            return;
        }
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
    protected function buildHeadersArray(HeaderBag $headerBag) 
    {
        return explode("\r\n", $headerBag);
    }


    /**
     * Gets the [$eventDispatcher description].
     *
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * Sets the [$eventDispatcher description].
     *
     * @param EventDispatcherInterface $eventDispatcher the event dispatcher
     *
     * @return self
     */
    protected function setEventDispatcher(EventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }


}