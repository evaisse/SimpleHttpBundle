<?php
/**
 * A statement that prepare a request and execute them.
 * The statement contains request, response and errors
 *
 * User: evaisse
 * Date: 29/05/15
 * Time: 14:21
 */
namespace evaisse\SimpleHttpBundle\Http;


use React\Promise\Deferred;
use React\Promise\Promise;

use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class Statement
{

    use ContainerAwareTrait;

    /**
     * $request : Service Request
     *
     * @var Request
     * @access protected
     */
    protected $request;

    /**
     * $error : Error
     *
     * @var Error
     * @access protected
     */
    protected $error;

    /**
     * $response : Service response
     *
     * @var Response
     * @access protected
     */
    protected $response;

    /**
     * A Deferred object
     * @var Promise
     */
    protected $promise;


    /**
     * A Promise Deferred control object
     * @var \React\Promise\Deferred
     */
    protected $deffered;


    /**
     * timeout in milliseconds
     * @var integer timeout in milliseconds
     */
    protected $timeout;


    /**
     * true if request has already been sent, false otherwsie
     * @var boolean true if request has already been sent, false otherwsie
     */
    protected $sent;

    /**
     *
     * @param Request $request An http request object to send
     */
    public function __construct(Request $request)
    {
        $this->setRequest($request);
        $this->deferred = new Deferred();
        $this->promise = $this->deferred->promise();
    }


    /**
     * @return int timeout in milliseconds
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param int $timeout timeout in milliseconds
     */
    public function setTimeout($timeout)
    {
        $this->timeout = (int)$timeout;
    }


    /**
     * @return Promise A promise object that allow to decouple your execution from async execution
     */
    public function getPromise()
    {
        return $this->promise;
    }

    /**
     * Get service result if define
     * @return mixed
     * @throws Exception
     * @throws Error
     */
    public function getResult()
    {
        if (!$this->getResponse() && !$this->hasError()) {
            throw new Exception("Service has not been sent yet");
        }

        if (!$this->hasError()) {
            return $this->getResponse()->getResult();
        } else {
            throw $this->getError();
        }
    }


    public function addServiceListener(ServiceListenerInterface $listener)
    {
        $this->listeners[] = $listener;
    }

    public function getServiceListeners()
    {
        return $this->listeners;
    }

    public function onDataAvailable($callback)
    {
        $this->onDataAvailableCallback = $callback;
        return $this;
    }


    /**
     * Set value for $request
     *
     * @param  Request $value value to set to request
     * @return Object         instance for method chaining
     */
    protected function setRequest(Request $value)
    {
        $this->request = $value;

        return $this;
    }

    /**
     * Get value for $request
     * @return Request Service Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Set value for $response
     *
     * @param  Response $response value to set to response
     * @return Object          instance for method chaining
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;

        if ($this->response->hasError()) {
            $this->setError($this->response->getError());
        }

        $this->deferred->resolve($response->getResult());

        return $this;
    }

    /**
     * @param string $json a json string, if omitted, request params will be used to built json string
     * @return self
     */
    public function json($json = null)
    {
        $this->request->headers->set('content-type', 'application/json');
        $this->request->headers->set('charset', 'utf-8');
        $this->request->headers->set('accept', 'application/json');
        if ($this->request->getMethod() !== "GET") {
            $json = $json === null ? json_encode($this->request->request->all()) : (string)$json;
            $this->request->setContent($json);
        }
        return $this;
    }

    /**
     * Get value for $response
     * @return Response Service response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Set value for $error
     *
     * @param  Error $value value to set to error
     * @return Object       instance for method chaining
     */
    public function setError(Error $value)
    {
        $this->error = $value;

        $this->deferred->reject($this->error);

        return $this;
    }

    /**
     * Get value for $error
     * @return Error Error
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * If service has error
     *
     * @return boolean true if has error, false otherwise
     */
    public function hasError()
    {
        return (bool)$this->error;
    }


    /**
     * Get unique id for this service
     * @return string string representation of current service
     */
    public function getUid()
    {
        return md5(spl_object_hash($this));
    }


    /**
     * @param Kernel $httpKernel
     * @return mixed
     * @throws Error
     * @throws Exception
     */
    public function execute(Kernel $httpKernel = null)
    {
        $this->sent = true;
        $http = $httpKernel ? $httpKernel : $this->container->get('simple_http.helper');
        $http->execute([$this]);

        if ($this->hasError()) {
            throw $this->getError();
        }

        return $this;
    }


    /**
     * @return boolean
     */
    public function isSent()
    {
        return $this->sent;
    }

    /**
     * @param boolean $sent
     */
    public function setSent($sent)
    {
        $this->sent = $sent;
    }


    /**
     *
     * @return string
     */
    public function __toString()
    {
        if (!$this->isSent()) {
            $this->execute();
        }

        if ($this->hasError()) {
            return '';
        }

        return $this->response->getContent();
    }

}