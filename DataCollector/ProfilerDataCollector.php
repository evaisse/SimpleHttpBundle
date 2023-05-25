<?php

namespace evaisse\SimpleHttpBundle\DataCollector;

use evaisse\SimpleHttpBundle\Http\Event\AbstractStatementPrepareEvent;
use evaisse\SimpleHttpBundle\Http\Event\StatementErrorEvent;
use evaisse\SimpleHttpBundle\Http\Event\StatementPrepareEvent;
use evaisse\SimpleHttpBundle\Http\Event\StatementSuccessEventInterface;
use evaisse\SimpleHttpBundle\Http\StatementEventMap;
use evaisse\SimpleHttpBundle\Serializer\CustomGetSetNormalizer;
use evaisse\SimpleHttpBundle\Http\Exception;

use evaisse\SimpleHttpBundle\Serializer\RequestNormalizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;

use Symfony\Component\Stopwatch\Stopwatch;



/*
 * @author Emmanuel VAISSE
 */
class ProfilerDataCollector extends DataCollector implements EventSubscriberInterface
{
    /** @var bool */
    protected $debug;

    /**
     * List of emitted requests
     *
     * @var array
     */
    protected $calls = array();

    /**
     * list of potential errors indexed by requests
     * @var array
     */
    protected $errors = 0;


    /**
     * Stopwatch component
     * @var StopWatch
     */
    protected $stopwatch;

    /**
     * blackfire instance id
     * @var string
     */
    protected string $blackfireClientId;

    /**
     * Access key to blackfire instance
     * @var string
     */
    protected string $blackfireClientToken;

    /**
     * sample amount used by blackfire commande
     * number of times that the curl will be executed
     * @var int
     */
    protected int $blackfireSamples;

    /**
     * @param bool $debug
     */
    public function __construct(bool $debug)
    {
        $this->debug = $debug;
    }

    /**
     * (non-PHPdoc)
     * @see \Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface::collect()
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        $this->data = array(
            'countRequests'              => count($this->calls),
            'countErrors'                => $this->errors,
            'totalExecutionTime'         => 0,
            'calls'                      => $this->normalizeCalls(),
        );
    }

    /**
     * @return array collected infos
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param  [type] $key          [description]
     * @param  [type] $defaultValue [description]
     * @return [type]               [description]
     */
    public function get($key, $defaultValue = null)
    {
        return isset($this->data[$key]) ? $this->data[$key] : $defaultValue;
    }

    /**
     * (non-PHPdoc)
     * @see \Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface::getName()
     */
    public function getName()
    {
        return 'simplehttpprofiler';
    }

    public static function getSubscribedEvents()
    {
        return [
            StatementEventMap::KEY_PREPARE => 'onPrepare',
            StatementEventMap::KEY_ERROR => 'onError',
            StatementEventMap::KEY_SUCCESS => 'onSuccess',
        ];
    }


    public function normalizeCalls()
    {
        $calls = array();

        foreach ($this->calls as $k => $v) {

            $calls[$k] = array(
                'time'          => $this->fetchTransferInfos($v),
                'request'       => $this->fetchRequestInfos($v['request']),
                'response'      => !empty($v['response']) ? $this->fetchResponseInfos($v['response']) : false,
                'error'         => !empty($v['error']) ? $this->fetchErrorInfos($v['error']) : false,
                'debugLink'     => false,
                'sfDebugLink'   => false,
                'trace'         => array_slice($v['trace'], 3),
                'curlCommand'   => $this->buildCurlCommand($v['request']),
                'blackfireCommand'   => $this->buildBlackfireCommand($v['request']),
            );

            if (isset($v['response'])) {
                $calls[$k]['response']['fromHttpCache'] = false;
                foreach ($calls[$k]['response']['headers'] as $h) {
                    foreach(['x-debug-uri:', 'x-debug-link:'] as $hk) {
                        if (stripos($h, $hk) === 0) {
                            list($hv, $url) = explode(':', $h, 2);
                            $url = trim($url);
                            $calls[$k]['debugLink'] = $url;
                            break;
                        }
                    }

                    if (stripos($h, 'x-debug-token-link:') === 0) {
                        list($hv, $url) = explode(':', $h, 2);
                        $calls[$k]['sfDebugLink'] = trim($url);
                    }

                    if (stripos($h, "X-Cache:") !== false && strpos($h, "HIT") !== false) {
                        $calls[$k]['response']['fromHttpCache'] = true;
                    }
                }
            }
        }

        return $calls;
    }


    public function onPrepare(RequestEvent $event)
    {
        if (!$this->debug) {
            return;
        }
        $request = $event->getRequest();

        $eventName = "#" . count($this->calls) . ' ' . $request->getMethod() . ' ' . $request->getUri();

        try {
            throw new \Exception("");
        } catch (\Exception $e) {
            $trace = explode("\n", $e->getTraceAsString());
        }

        $this->calls[] = array(
            "start"          => microtime(true),
            'stop'           => 0,
            "request"        => $request,
            'response'       => null,
            "error"          => null,
            'stopWatchEvent' => $this->getStopwatch()->start($eventName, 'doctrine'),
            "trace"          => $trace
        );
    }


    /**
     * @param $request
     * @return int|null|string
     */
    public function getRequestKey($request)
    {
        foreach ($this->calls as $key => $value) {
            if ($request === $value['request']) {
                return $key;
            }
        }
        return null;
    }

    public function onError(ExceptionEvent $event)
    {
        if (!$this->debug) {
            return;
        }
        $key = $this->getRequestKey($event->getRequest());

        $this->errors++;

        $this->calls[$key] = array_merge($this->calls[$key], array(
            'response' => $event->getResponse(),
            "error"    => $event->getThrowable(),
            "stop"     => microtime(true),
        ));

        $this->finishEvent($key);
    }

    public function onSuccess(ResponseEvent $event)
    {
        if (!$this->debug) {
            return;
        }
        $key = $this->getRequestKey($event->getRequest());

        $this->calls[$key] = array_merge($this->calls[$key], array(
            'response' => $event->getResponse(),
            "error"    => false,
            "stop"     => microtime(true),
        ));

        if ($event->getResponse()->getStatusCode() >= 400) {
            $this->errors++;
        }

        /*
         *  If there is some cache hit informative headers,
         *  we set cache infos for the timeline
         */
        if ($event->getResponse()
            && $event->getResponse()->headers->get('X-Cache')
            && stripos($event->getResponse()->headers->get('X-Cache')[0], 'HIT') !== false
        ) {
            $this->calls[$key]['cache'] = true;
        }

        $this->finishEvent($key);
    }


    public function finishEvent($key)
    {
        $this->calls[$key]['stopWatchEvent']->stop();
        unset($this->calls[$key]['stopWatchEvent']);
    }

    /**
     * @param Request $request
     * @return string
     */
    public function buildCurlCommand(Request $request)
    {
        $command = 'curl -i
-X '.$request->getRealMethod();
        foreach($request->headers->all() as $headerName => $headerValues) {
            foreach($headerValues as $headerValue) {
                $command .= "
-H \"$headerName: " . (string)$headerValue . "\"";
            }
        }

        if (in_array($request->getRealMethod(), ['POST', 'PUT', 'PATCH']) && !empty($request->getContent())) {
            $command.= '
--data "'.addcslashes($request->getContent(), '"').'"';
        }

        $command.='
"'.$request->getSchemeAndHttpHost().$request->getRequestUri().'"';
        return str_replace("\n", " \\\n", $command);
    }

    /**
     * @param Request $request
     * @return string
     */
    public function buildBlackfireCommand(Request $request)
    {
        $command = "blackfire \\\n";
        if (!empty($this->blackfireClientId)) {
            $command .= "--client-id=\"$this->blackfireClientId\" \\\n";
        }
        if (!empty($this->blackfireClientToken)) {
            $command .= "--client-token=\"$this->blackfireClientToken\" \\\n";
        }
        $command .= "--samples $this->blackfireSamples \\\n";

        $curl = $this->buildCurlCommand($request);
        return $command . $curl;
    }

    public function fetchTransferInfos(array $call)
    {
        $call['stop'] = isset($call['stop']) ? $call['stop'] : 0;

        $timing = array(
            'start'      => $call['start'],
            'stop'       => $call['stop'],
            'connection' => 0,
            // Stop can be equal to 0 if transaction is still in progress
            'total'      => $call['stop'] !== 0 ? $call['stop'] - $call['start'] : 0,
        );

        if (!empty($call['response']) && method_exists($call['response'], 'getTransferInfos')) {
            $timing['connection'] = $call['response']->getTransferInfos()['connect_time'];
            $timing['total'] = $call['response']->getTransferInfos()['total_time'];
        }

        return $timing;
    }

    public function fetchRequestInfos(Request $request)
    {
        $normalizers = array(new RequestNormalizer());
        $encoders = array(new JsonEncoder());
        $serializer = new Serializer($normalizers, $encoders);

        $data = json_decode($serializer->serialize($request, 'json'), true);
        $data['headers'] = explode("\r\n\r\n", (string)$request, 2)[0];
        $data['headers'] = explode("\r\n", $data['headers']);

        $content = $request->getContent();
        if (empty($content)) {
            $data['content'] = http_build_query($request->request->all());
        }

        parse_str($data['queryString'], $data['query']);
        $data['contentType'] = $request->headers->get('content-type');
        $data['cookies'] = $request->cookies->all();
        return $data;
    }

    /**
     * @param Response $response
     * @return mixed
     */
    public function fetchResponseInfos(Response $response)
    {
        $data = [
            'statusCode' => $response->getStatusCode(),
        ];

        $parts = explode("\r\n\r\n", (string)$response, 2);
        $data['headers'] = isset($parts[0]) ? $parts[0] : "";
        $data['body'] = isset($parts[1]) ? $parts[1] : "";
        $data['headers'] = explode("\r\n", $data['headers']);
        $data['contentType'] = $response->headers->get('content-type');
        $cookies = $response->headers->getCookies();

        $data['cookies'] = [];

        foreach ($cookies as $c) {
            $data['cookies'][$c->getName()] = array(
                "value"     => $c->getValue(),
                "domain"    => $c->getDomain(),
                "expires"   => $c->getExpiresTime() === 0 ? 'on session close' : date('Y-m-d H:i:s', $c->getExpiresTime()),
                "path"      => $c->getPath(),
                "secure"    => $c->isSecure(),
                "httpOnly"  => $c->isHttpOnly(),
                "cleared"   => $c->getExpiresTime() !== 0
                            && time() > $c->getExpiresTime(),
            );
        }

        $data['statusPhrase'] = $data['headers'][0];

        return $data;
    }

    public function fetchErrorInfos(\Throwable $error)
    {
        return array(
            'class'         => get_class($error),
            'message'       => $error->getMessage(),
            'code'          => $error->getCode(),
            'file'          => $error->getFile(),
            'line'          => $error->getLine(),
            'trace'         => $error->getTraceAsString(),
            'previous'      => $error->getPrevious() ? $this->fetchErrorInfos($error->getPrevious()) : array(),
        );
    }

    /**
     * @return int
     */
    public function countRequests()
    {
        return $this->get('countRequests', 0);
    }

    /**
     * @return int
     */
    public function countErrors()
    {
        return $this->get('countErrors', 0);
    }

    /**
     * @return int
     */
    public function getTotalTime()
    {
        $t = 0;
        foreach ($this->data['calls'] as $key => $value) {
            $t += $value['time']['total'];
        }
        return $t;
    }


    public function getCalls()
    {
        return array_map(array($this, 'filterCall'), $this->data['calls']);
    }


    public function countSuccessfullRequest()
    {
        return $this->countRequests() - $this->countErrors();
    }


    /**
     * [getHosts description]
     * @return array [description]
     */
    public function getHosts()
    {
        $hosts = array();

        foreach ($this->data['calls'] as $value) {
            $host = md5($value['request']['schemeAndHttpHost']);
            $hosts[$host] = isset($hosts[$host]) ? $hosts[$host] : array();
            $hosts[$host][] = $value['request']['schemeAndHttpHost'];
        }

        foreach ($hosts as $key => $value) {
            $hosts[$key] = $value[0] . ' (' . count($value) . ')';
        }

        array_unshift($hosts, '--- all (' . count($this->data['calls']) . ') ---');

        return $hosts;
    }


    /**
     * Current calls stack contains http client errors 4XX
     * @return int
     */
    public function getClientErrorsCount()
    {
        return count($this->getClientErrors());
    }


    /**
     * Test if current calls stack contains http client errors 4XX
     * @return bool
     */
    public function hasClientErrors()
    {
        return (bool)$this->getClientErrorsCount();
    }


    /**
     * Get all HTTP 4XX client errors calls
     * @return array[]
     */
    public function getClientErrors()
    {
        return array_filter($this->getCalls(), static function ($call) {
            if ($call['response']
                && array_key_exists('statusCode', $call['response'])
                && $call['response']['statusCode'] < 500
                && $call['response']['statusCode'] >= 400) {
                return true;
            }
        });
    }

    /**
     * current calls stack contains http server errors 5XX
     * @return int
     */
    public function getServerErrorsCount()
    {
        return count($this->getServerErrors());
    }

    /**
     * Test if current calls stack contains http server errors 5XX
     * @return bool
     */
    public function hasServerErrors()
    {
        return (bool)$this->getServerErrorsCount();
    }


    /**
     * Get all HTTP 5XX server errors calls
     * @return array[]
     */
    public function getServerErrors()
    {
        return array_filter($this->getCalls(), static function ($call) {
            if (is_array($call['response']) && array_key_exists('statusCode', $call['response']) && $call['response']['statusCode'] >= 500) {
                return true;
            }
        });
    }


    /**
     * Gets the Stopwatch component.
     *
     * @return StopWatch
     */
    public function getStopwatch()
    {
        return $this->stopwatch;
    }

    /**
     * Sets the Stopwatch component.
     *
     * @param StopWatch $stopwatch the stopwatch
     *
     * @return self
     */
    public function setStopwatch(StopWatch $stopwatch)
    {
        $this->stopwatch = $stopwatch;

        return $this;
    }

    /**
     * Parse additionnal infos for request/response results, like auth mechanism
     * @param array $call call info
     * @return array filtered call info
     */
    protected function filterCall(array $call)
    {
        $call['auth'] = !empty($call['auth']) ? $call['auth'] : [];
        $call['request']['jwt'] = $call['response']['jwt'] = false;

        try {
            if ($requestJwt = $this->fetchJwtInfosFromHeaders($call['request']['headers'])) {
                $call['request']['jwt'] = $requestJwt;
            }

            if ($responseJwt = $this->fetchJwtInfosFromHeaders($call['response']['headers'])) {
                $call['response']['jwt'] = $responseJwt;
            }


            if ($requestJwt || $responseJwt) {
                $call['auth']['type'] = "JWT";
            }

        } catch (\Exception $e) {
            // prevent
        }


        return $call;
    }


    /**
     * @param string[] $headers
     * @return array|null null if no jwt, infos otherwise [
     *  'encoded' => $m[1],
     *  'decoded' => [
     *    "header"    => $jwtHeader,
     *    "payload"   => $jwtPayload,
     *    "signature" => $jwtSignature,
     *  ],
     * ]
     */
    protected function fetchJwtInfosFromHeaders(array $headers)
    {
        $jwt = null;

        foreach ($headers as $h) {
            if (!preg_match('/Authorization:\s*Bearer\s+(\w+\.\w+.\w+)/i', $h, $m)) {
                continue;
            }
            $parts = explode('.', $m[1]);
            $jwtHeader = isset($parts[0]) ? $parts[0] : null;
            $jwtPayload = isset($parts[1]) ? $parts[1] : null;
            $jwtSignature = isset($parts[2]) ? $parts[2] : null;

            $jwt = [
                'encoded' => $m[1],
                'decoded' => [
                    "header"    => $this->urlsafeB64Decode($jwtHeader),
                    "payload"   => $this->urlsafeB64Decode($jwtPayload),
                    "signature" => $jwtSignature,
                ],
            ];
        }

        return $jwt;
    }

    /**
     * Decode a string with URL-safe Base64.
     *
     * @param string $input A Base64 encoded string
     *
     * @return string A decoded string
     */
    public function urlsafeB64Decode($input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        try {
            return base64_decode(strtr($input, '-_', '+/'));
        } catch (\Exception $e) {
            return "";
        }
    }
    /**
     * Encode a string with URL-safe Base64.
     *
     * @param string $input The string you want encoded
     *
     * @return string The base64 encode of what you passed in
     */
    public function urlsafeB64Encode($input)
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    public function reset()
    {
        $this->data = [];
        $this->calls = [];
        $this->errors = [];
        $this->stopwatch->reset();
    }

    /**
     * Set blackfire properties
     * @param $config array of items that can be used to set blackfire config
     * @return void
     */
    public function setBlackfireConfig(array $config): void
    {
        $this->blackfireClientId = $config["client_id"] ?? "";
        $this->blackfireClientToken = $config["client_token"] ?? "";
        $this->blackfireSamples = $config["samples"] ?? 10;
    }
}
