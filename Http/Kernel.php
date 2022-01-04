<?php

/*
 * (c) Darrell Hamilton <darrell.noice@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace evaisse\SimpleHttpBundle\Http;


use CURLFile;
use evaisse\SimpleHttpBundle\Curl\Collector\ContentCollector;
use evaisse\SimpleHttpBundle\Curl\CurlErrorException;
use evaisse\SimpleHttpBundle\Curl\CurlEvents;
use evaisse\SimpleHttpBundle\Curl\CurlHeaderCollector;
use evaisse\SimpleHttpBundle\Curl\MultiInfoEvent;
use evaisse\SimpleHttpBundle\Curl\MultiManager;
use evaisse\SimpleHttpBundle\Curl\Request as CurlRequest;
use evaisse\SimpleHttpBundle\Curl\RequestGenerator;
use evaisse\SimpleHttpBundle\Http\Exception\CurlTransportException;
use evaisse\SimpleHttpBundle\Http\Kernel\RemoteHttpKernel;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;


/**
 * RemoteHttpKernel utilizes curl to convert a Request object into a Response
 *
 * @author Darrell Hamilton <darrell.noice@gmail.com>
 */
class Kernel extends RemoteHttpKernel
{
    /**
     * @var RequestGenerator|null An instance of Curl\RequestGenerator for getting preconfigured Curl\Request objects
     */
    protected ?RequestGenerator $generator = null;

    /**
     * @var EventDispatcherInterface
     */
    protected EventDispatcherInterface $eventDispatcher;

    /**
     * @var array a statement pool that contains statements during multicurl
     */
    protected array $stmts;


    /**
     * @var string a tmp cookie filepath
     */
    protected string $tmpCookieFile;


    /**
     * @var Statement[]
     */
    protected array $services = [];

    /**
     * @param EventDispatcherInterface $eventDispatcher
     * @param RequestGenerator|null $generator Optionnal generator to construct curlrequest
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, RequestGenerator $generator = null)
    {
        $this->setEventDispatcher($eventDispatcher);
        parent::__construct($generator);
    }

    /**
     * @param MultiInfoEvent $e
     */
    public function handleMultiInfoEvent(MultiInfoEvent $e): void
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

            $event = new ExceptionEvent($this, $request, HttpKernelInterface::SUB_REQUEST, $error);

            $this->getEventDispatcher()->dispatch($event, StatementEventMap::KEY_ERROR);

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

            $event = new ResponseEvent($this, $request, HttpKernelInterface::SUB_REQUEST, $response);
            $this->getEventDispatcher()->dispatch($event, StatementEventMap::KEY_SUCCESS);

            /*
                populate response for service
             */
            $stmt->setResponse($response);
        }

    }

    /**
     * handle multi curl
     * @param Statement[] $stmts a list of Service instances
     * @return Kernel current httpkernel for method chaining
     */
    public function execute(array $stmts): static
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

                $event = new RequestEvent($this, $request, HttpKernelInterface::SUB_REQUEST);
                $this->getEventDispatcher()->dispatch($event, StatementEventMap::KEY_PREPARE);

                list($curlHandler, $contentCollector, $headerCollector) = $this->prepareRawCurlHandler($stmt);

                $mm->addRequest($curlHandler);

                $this->services[] = [
                    $stmt,
                    [$curlHandler, $contentCollector, $headerCollector],
                ];

            } catch (CurlErrorException $e) {

                $stmt->setError(new Exception\CurlTransportException("CURL connection error", 1, $e));

                $event = new ExceptionEvent(
                    $this,
                    $request,
                    HttpKernelInterface::SUB_REQUEST,
                    $stmt->getError()
                );
                $this->getEventDispatcher()->dispatch($event, StatementEventMap::KEY_ERROR);
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
    public function handle(HttpRequest $request, int $type = HttpKernelInterface::SUB_REQUEST, bool $catch = true): HttpResponse
    {
        try {
            $stmt = new Statement($request, $this->eventDispatcher);

            $this->execute([
                $stmt
            ]);

            if ($stmt->hasError()) {
                throw $stmt->getError();
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
    protected function handleException(\Exception $e, HttpRequest $request): Response
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
    protected function getCurlRequest(): CurlRequest
    {
        if ($this->generator !== null) {
            return $this->generator->getRequest();
        }

        return new CurlRequest();
    }

    /**
     * Execute a Request object via cURL
     *
     * @param Statement $stmt the request to execute
     *
     * @return array
     *
     * @throws CurlErrorException 
     */
    protected function prepareRawCurlHandler(Statement $stmt): array
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
            /** @noinspection CurlSslServerSpoofingInspection */
            $curl->setOptionArray([
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
        }


        if ($request->getMethod() !== "GET") {
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
    protected function createCurlFile(string $filename, string $mimetype, string $postname = null): mixed
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
    protected function setPostFields(CurlRequest $curl, HttpRequest $request): void
    {
        $postfields = null;
        $content = $request->getContent();

        if (!empty($content)) {
            $postfields = $content;
            $a = 1;
        } else if (count($request->files)) {
            $postfields = $request->request->all();
            // Add files to postfields as curl resources
            foreach ($request->files->all() as $key => $file) {
                $file = $this->createCurlFile($file->getRealPath(), $file->getMimeType(), basename($file->getClientOriginalName()));
                $postfields[$key] = $file;
            }
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
    protected function buildCookieString(ParameterBag $cookiesBag): string
    {
        $cookies = [];

        foreach ($cookiesBag as $key => $value) {
            $cookies[] = "$key=$value";
        }

        return implode(';', $cookies);
    }


    /**
     * Some headers like user-agent can be overrided by curl so we need to re-fetch postward the headers sent
     * to reset them in the original request object
     *
     * @param Request $request request object after being sent
     * @param array $curlInfo curl info needed to update the request object with final curl headers sent
     */
    protected function updateRequestHeadersFromCurlInfos(Request $request, array $curlInfo): void
    {
        if (!isset($curlInfo['request_header'])) {
            return;
        }
        $headers = explode("\r\n", $curlInfo['request_header']);
        array_shift($headers);
        $replacementsHeaders = array();
        foreach ($headers as $header) {
            if (strpos($header, ':')) {
                [$k, $v] = explode(':', $header, 2);
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
    protected function buildHeadersArray(HeaderBag $headerBag): array
    {
        return explode("\r\n", $headerBag);
    }


    /**
     * Gets the [$eventDispatcher description].
     *
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher(): EventDispatcherInterface
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
    protected function setEventDispatcher(EventDispatcherInterface $eventDispatcher): static
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }


}