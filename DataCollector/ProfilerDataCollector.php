<?php

namespace evaisse\SimpleHttpBundle\DataCollector;

use evaisse\SimpleHttpBundle\Serializer\CustomGetSetNormalizer;
use evaisse\SimpleHttpBundle\Http\Exception;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

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
     * (non-PHPdoc)
     * @see \Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface::collect()
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
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
        return 'profiler';
    }

    public static function getSubscribedEvents()
    {
        return [
            'kernel.request'   => array('onRequest', 9999),
            'kernel.exception' => array('onException', 9999),
            'kernel.response'  => array('onResponse', 9999),
        ];
    }


    public function normalizeCalls()
    {
        $calls = array();

        foreach ($this->calls as $k => $v) {

            $calls[$k] = array(
                'time'      => $this->fetchTransferInfos($v),
                'request'   => $this->fetchRequestInfos($v['request']),
                'response'  => !empty($v['response']) ? $this->fetchResponseInfos($v['response']) : false,
                'error'     => !empty($v['error']) ? $this->fetchErrorInfos($v['error']) : false,
                'debugLink' => false,
            );

            if ($v['response']) {
                foreach ($calls[$k]['response']['headers'] as $h) {
                    foreach(['x-debug-uri', 'x-debug-link'] as $hk) {
                        if (stripos($h, $hk) !== false) {
                            list($hv, $url) = explode(':', $h, 2);
                            $url = trim($url);
                            $calls[$k]['debugLink'] = $url;
                            break;
                        }
                    }
                }
            }
        }

        return $calls;
    }


    public function onRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $eventName = "#" . count($this->calls) . ' ' . $request->getMethod() . ' ' . $request->getUri();
        
        $this->calls[] = array(
            "start"          => microtime(true),
            "request"        => $request,
            'stopWatchEvent' => $this->getStopwatch()->start($eventName, 'doctrine')
        );
    }


    public function getRequestKey($request)
    {
        foreach ($this->calls as $key => $value) {
            if ($request === $value['request']) {
                return $key;
            }
        }
    }

    public function onException(GetResponseForExceptionEvent $event)
    {
        $key = $this->getRequestKey($event->getRequest());

        $this->errors++;
        $this->calls[$key] = array_merge($this->calls[$key], array(
            'response' => $event->getResponse(),
            "error"    => $event->getException(),
            "stop"     => microtime(true),
        ));

        $this->finishEvent($key);

    }

    public function onResponse(FilterResponseEvent $event)
    {
        $key = $this->getRequestKey($event->getRequest());

        $this->calls[$key] = array_merge($this->calls[$key], array(
            'response' => $event->getResponse(),
            "error"    => false,
            "stop"     => microtime(true),
        ));

        if ($event->getResponse()->getStatusCode() >= 400) {
            $this->errors++;
        }

        $this->finishEvent($key);
    }


    public function finishEvent($key)
    {
        $this->calls[$key]['stopWatchEvent']->stop();
        // dump('stop' . $key, $this->calls[$key]['stopWatchEvent']);
        unset($this->calls[$key]['stopWatchEvent']);
    }


    public function fetchTransferInfos(array $call)
    {
        $timing = array(
            'start'      => $call['start'],
            'stop'       => $call['stop'],
            'connection' => 0,
            'total'      => $call['stop'] - $call['start'],
        );

        if (!empty($call['response']) && method_exists($call['response'], 'getTransferInfos')) {
            $timing['connection'] = $call['response']->getTransferInfos()['connect_time'];
            $timing['total'] = $call['response']->getTransferInfos()['total_time'];
        }

        return $timing;
    }

    public function fetchRequestInfos(Request $request)
    {
        $normalizers = array(new CustomGetSetNormalizer());
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
        $normalizers = array(new CustomGetSetNormalizer());
        $encoders = array(new JsonEncoder());
        $serializer = new Serializer($normalizers, $encoders);

        $data = json_decode($serializer->serialize($response, 'json'), true);
        $data['headers'] = explode("\r\n\r\n", (string)$response, 2)[0];
        $data['headers'] = explode("\r\n", $data['headers']);
        $data['contentType'] = $response->headers->get('content-type');
        $cookies = $response->headers->getCookies();

        $data['cookies'] = [];

        foreach ($cookies as $c) {
            $data['cookies'][$c->getName()] = array(
                "value"     => $c->getValue(),
                "domain"    => $c->getDomain(),
                "expires"   => date('Y-m-d H:i:s', $c->getExpiresTime()),
                "path"      => $c->getPath(),
                "secure"    => $c->isSecure(),
                "httpOnly"  => $c->isHttpOnly(),
                "cleared"   => $c->isCleared(),
            );
        }

        $data['statusPhrase'] = $data['headers'][0];

        return $data;
    }

    public function fetchErrorInfos(\Exception $error)
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
        return $this->data['calls'];
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
}
