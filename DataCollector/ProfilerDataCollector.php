<?php

namespace evaisse\SimpleHttpBundle\DataCollector;

use evaisse\SimpleHttpBundle\Http\StatementEventMap;
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
use Symfony\Component\Stopwatch\Stopwatch;

/*
 * @author Emmanuel VAISSE
 */
class ProfilerDataCollector extends DataCollector implements EventSubscriberInterface
{
    /**
     * List of emitted requests
     */
    protected array $calls = [];

    /**
     * list of potential errors indexed by requests
     */
    protected int $errors = 0;

    protected null|Stopwatch $stopwatch = null;

    /**
     * blackfire instance id
     */
    protected ?string $blackfireClientId;

    /**
     * Access key to blackfire instance
     */
    protected ?string $blackfireClientToken;

    /**
     * sample amount used by blackfire commande
     * number of times that the curl will be executed
     */
    protected int $blackfireSamples;

    public function __construct(protected bool $debug)
    {
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null): void
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
    public function getData(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $defaultValue = null): mixed
    {
        return $this->data[$key] ?? $defaultValue;
    }

    public function getName(): string
    {
        return 'simplehttpprofiler';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StatementEventMap::KEY_PREPARE => 'onPrepare',
            StatementEventMap::KEY_ERROR => 'onError',
            StatementEventMap::KEY_SUCCESS => 'onSuccess',
        ];
    }


    public function normalizeCalls(): array
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
                    foreach (['x-debug-uri:', 'x-debug-link:'] as $hk) {
                        if (stripos($h, $hk) === 0) {
                            [, $url] = explode(':', $h, 2);
                            $url = trim($url);
                            $calls[$k]['debugLink'] = $url;
                            break;
                        }
                    }

                    if (stripos($h, 'x-debug-token-link:') === 0) {
                        [, $url]  = explode(':', $h, 2);
                        $calls[$k]['sfDebugLink'] = trim($url);
                    }

                    if (stripos($h, "X-Cache:") !== false && str_contains($h, "HIT")) {
                        $calls[$k]['response']['fromHttpCache'] = true;
                    }
                }
            }
        }

        return $calls;
    }


    public function onPrepare(RequestEvent $event): void
    {
        if (!$this->debug) {
            return;
        }
        $request = $event->getRequest();

        $eventName = "#" . count($this->calls) . ' ' . $request->getMethod() . ' ' . $request->getUri();

        $this->calls[] = array(
            "start"          => microtime(true),
            'stop'           => 0,
            "request"        => $request,
            'response'       => null,
            "error"          => null,
            'stopWatchEvent' => $this->getStopwatch()->start($eventName, 'doctrine'),
            "trace"          => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        );
    }

    public function getRequestKey(Request $request): int|null|string
    {
        foreach ($this->calls as $key => $value) {
            if ($request === $value['request']) {
                return $key;
            }
        }

        return null;
    }

    public function onError(ExceptionEvent $event): void
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

    public function onSuccess(ResponseEvent $event): void
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
        if (
            $event->getResponse()
            && $event->getResponse()->headers->get('X-Cache')
            && stripos($event->getResponse()->headers->get('X-Cache')[0], 'HIT') !== false
        ) {
            $this->calls[$key]['cache'] = true;
        }

        $this->finishEvent($key);
    }


    public function finishEvent($key): void
    {
        $this->calls[$key]['stopWatchEvent']->stop();
        unset($this->calls[$key]['stopWatchEvent']);
    }

    public function buildCurlCommand(Request $request): string
    {
        $command = 'curl -i -X ' . $request->getRealMethod();
        foreach ($request->headers->all() as $headerName => $headerValues) {
            foreach ($headerValues as $headerValue) {
                $command .= " -H \"$headerName: " . $headerValue . "\"";
            }
        }

        if (in_array($request->getRealMethod(), ['POST', 'PUT', 'PATCH']) && !empty($request->getContent())) {
            $command .= ' --data "' . addcslashes($request->getContent(), '"') . '"';
        }

        $command .= ' "' . $request->getSchemeAndHttpHost() . $request->getRequestUri() . '"';

        return str_replace("\n", " \\\n", $command);
    }

    public function buildBlackfireCommand(Request $request): string
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

    public function fetchTransferInfos(array $call): array
    {
        $call['stop'] = $call['stop'] ?? 0;

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

    public function fetchRequestInfos(Request $request): array
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

    public function fetchResponseInfos(Response $response): array
    {
        $data = [
            'statusCode' => $response->getStatusCode(),
        ];

        $parts = explode("\r\n\r\n", (string)$response, 2);
        $data['headers'] = $parts[0] ?? "";
        $data['body'] = $parts[1] ?? "";
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

    public function fetchErrorInfos(\Throwable $error): array
    {
        return [
            'class'         => get_class($error),
            'message'       => $error->getMessage(),
            'code'          => $error->getCode(),
            'file'          => $error->getFile(),
            'line'          => $error->getLine(),
            'trace'         => $error->getTraceAsString(),
            'previous'      => $error->getPrevious() ? $this->fetchErrorInfos($error->getPrevious()) : array(),
        ];
    }

    public function countRequests(): int
    {
        return $this->get('countRequests', 0);
    }

    /**
     * @return int
     */
    public function countErrors(): int
    {
        return $this->get('countErrors', 0);
    }

    public function getTotalTime(): float
    {
        $t = 0;
        foreach ($this->data['calls'] as $value) {
            $t += $value['time']['total'];
        }
        return $t;
    }


    public function getCalls(): array
    {
        return array_map(array($this, 'filterCall'), $this->data['calls']);
    }


    /**
     * @deprecated Use countSuccessfulRequest instead
     */
    public function countSuccessfullRequest(): int
    {
        return $this->countSuccessfulRequest();
    }

    public function countSuccessfulRequest(): int
    {
        return $this->countRequests() - $this->countErrors();
    }


    public function getHosts(): array
    {
        $hosts = [];

        foreach ($this->data['calls'] as $value) {
            $host = md5($value['request']['schemeAndHttpHost']);
            $hosts[$host] = $hosts[$host] ?? [];
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
     */
    public function getClientErrorsCount(): int
    {
        return count($this->getClientErrors());
    }


    /**
     * Test if current calls stack contains http client errors 4XX
     */
    public function hasClientErrors(): bool
    {
        return $this->getClientErrorsCount() > 0;
    }


    /**
     * Get all HTTP 4XX client errors calls
     * @return array[]
     */
    public function getClientErrors(): array
    {
        return array_filter($this->getCalls(), static function ($call) {
            return is_array($call['response'])
                && array_key_exists('statusCode', $call['response'])
                && $call['response']['statusCode'] < 500
                && $call['response']['statusCode'] >= 400;
        });
    }

    /**
     * current calls stack contains http server errors 5XX
     */
    public function getServerErrorsCount(): int
    {
        return count($this->getServerErrors());
    }

    /**
     * Test if current calls stack contains http server errors 5XX
     */
    public function hasServerErrors(): bool
    {
        return $this->getServerErrorsCount() > 0;
    }


    /**
     * Get all HTTP 5XX server errors calls
     * @return array[]
     */
    public function getServerErrors(): array
    {
        return array_filter($this->getCalls(), static function ($call) {
            return is_array($call['response'])
                && array_key_exists('statusCode', $call['response'])
                && $call['response']['statusCode'] >= 500;
        });
    }

    public function getStopwatch(): null|Stopwatch
    {
        return $this->stopwatch;
    }

    public function setStopwatch(StopWatch $stopwatch): self
    {
        $this->stopwatch = $stopwatch;

        return $this;
    }

    /**
     * Parse additional infos for request/response results, like auth mechanism
     */
    protected function filterCall(array $call): array
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
     * @return null|array{
     *     encoded: string,
     *     decoded: array{header: string|null, payload: string|null, signature: string|null}
     * }
     */
    protected function fetchJwtInfosFromHeaders(array $headers): null|array
    {
        $jwt = null;

        foreach ($headers as $h) {
            if (!preg_match('/Authorization:\s*Bearer\s+(\w+\.\w+.\w+)/i', $h, $m)) {
                continue;
            }
            $parts = explode('.', $m[1]);
            $jwtHeader = $parts[0] ?? null;
            $jwtPayload = $parts[1] ?? null;
            $jwtSignature = $parts[2] ?? null;

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
     */
    public function urlsafeB64Decode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        try {
            return base64_decode(strtr($input, '-_', '+/'));
        } catch (\Exception) {
            return "";
        }
    }

    public function reset(): void
    {
        $this->data = [];
        $this->calls = [];
        $this->errors = 0;
        $this->stopwatch->reset();
    }

    public function setBlackfireConfig(array $config): void
    {
        $this->blackfireClientId = $config['client_id'] ?? '';
        $this->blackfireClientToken = $config['client_token'] ?? '';
        $this->blackfireSamples = $config['samples'] ?? 10;
    }
}
