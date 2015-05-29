<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 29/05/15
 * Time: 14:21
 */
namespace evaisse\SimpleHttpBundle\Http;


use React\Promise\Deferred;
use React\Promise\Promise;

class Transaction
{

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


    public $container;

    /**
     * [__construct description]
     * @param Request $request [description]
     */
    public function __construct(Request $request)
    {
        $this->setRequest($request);
        $this->deferred = new Deferred();
        $this->promise = $this->deferred->promise();
    }



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
     * @return string
     */
    public function __toString()
    {
        return $this->getUid();
    }

    /**
     * Get unique id for this service
     * @return string string representation of current service
     */
    public function getUid()
    {
        return md5(spl_object_hash($this));
    }


    public function execute()
    {
        $this->container->get('simple_http')->execute([$this]);
        return $this->getResult();
    }

}