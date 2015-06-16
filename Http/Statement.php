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


use evaisse\SimpleHttpBundle\Http\Exception\RequestNotSentException;
use React\Promise\Deferred;
use React\Promise\Promise;

use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;

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
     * @var Response statement response
     * @access protected
     */
    protected $response;

    /**
     * @var Promise A Deferred object
     */
    protected $promise;

    /**
     * @var \React\Promise\Deferred  A Promise Deferred control object
     */
    protected $deffered;


    /**
     * @var integer timeout in milliseconds
     */
    protected $timeout;

    /**
     * true if request has already been sent, false otherwsie
     * @var boolean true if request has already been sent, false otherwsie
     */
    protected $sent;


    /**
     * @var boolean ignore ssl verification and notifications
     */
    protected $ignoreSslErrors;


    /**
     * @var EventDispatcher dispatcher for http transaction events
     */
    protected $eventDispatcher;


    /**
     * Ignore SSL verification and notifications
     *
     * @return self
     */
    public function ignoreSslErrors()
    {
        $this->ignoreSslErrors = true;
        return $this;
    }

    /**
     * Set verification and notifications on ssl profiles
     * @return boolean
     */
    public function getIgnoreSslErrors()
    {
        return $this->ignoreSslErrors;
    }

    /**
     * Get verification and notifications on ssl profiles
     *
     * @param boolean $ignoreSslErrors
     * @return self
     */
    public function setIgnoreSslErrors($ignoreSslErrors)
    {
        $this->ignoreSslErrors = $ignoreSslErrors;
        return $this;
    }

    /**
     *
     * @param Request $request An http request object to send
     */
    public function __construct(Request $request)
    {
        $this->setRequest($request);
        $this->deferred = new Deferred();
        $this->promise = $this->deferred->promise();
        $this->eventDispatcher = new EventDispatcher();
    }


    /**
     * Get global (connect+wait+transfer) request timeout in milliseconds
     *
     * @return int timeout in milliseconds
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Set global (connect+wait+transfer) request timeout in milliseconds
     *
     * @param int $timeout timeout in milliseconds
     * @return self
     */
    public function setTimeout($timeout)
    {
        $this->timeout = (int)$timeout;
        return $this;
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
     *
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function getResult()
    {
        if (!$this->getResponse() && !$this->hasError()) {
            throw new RequestNotSentException("Request has not been sent yet");
        }

        if (!$this->hasError()) {
            return $this->getResponse()->getResult();
        } else {
            throw $this->getError();
        }
    }


    /**
     * @return EventDispatcher event dispatcher for internal http transactions events
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
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
        } else {
            $this->deferred->resolve($response->getResult());
        }

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
     * @param  HttpException  $value value to set to error
     * @return Object       instance for method chaining
     */
    public function setError(HttpException $value)
    {
        $this->error = $value;

        $this->deferred->reject($this->error);

        return $this;
    }

    /**
     * Get value for $error
     * @return HttpException Error
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
     * @param string $key multi-part form item key name
     * @param string $filepath path to file
     * @param string|null $mimetype file mimetype, if none provided, will be guess from filepath
     * @param string|null $clientName optionnal client filename, if none provided, basename of filepath will be used
     */
    public function attachFile($key, $filepath, $mimetype = null, $clientName = null)
    {
        $clientName = $clientName ? $clientName : basename($filepath);

        $file = new File($filepath, true);

        $file = new UploadedFile(
            $file->getRealPath(),
            $clientName,
            $file->getBasename(),
            $file->getMimeType(),
            $file->getSize(),
            0);

        $this->getRequest()->files->set($key, $file);
    }


    /**
     * A callbackto call on completed transaction, ethier on success or failure
     *
     * @param callable $callable callable proxyied to getPromise()->then($callable) to call on completed transaction, ethier on success or failure
     * @return self
     */
    public function onSuccess(callable $callable)
    {
        $this->getPromise()->then($callable);
        return $this;
    }


    /**
     * Assign a callback on error
     *
     * @param callable $callable callable proxyied to getPromise()->otherwise($callable) to call on completed transaction, ethier on success or failure
     * @return self
     */
    public function onError(callable $callable)
    {
        $this->getPromise()->then(null, $callable);
        return $this;
    }


    /**
     * Assign a callback on progress notification
     *
     * @param callable $callable callable proxyied to getPromise()->progress($callable) to call on completed transaction, ethier on success or failure
     * @return self
     */
    public function onProgress(callable $callable)
    {
        $this->getPromise()->then(null, null, $callable);
        return $this;
    }


    /**
     * Assign a callback to  on completed transaction, ethier on success or failure
     *
     * @param callable $callable callable proxyied to getPromise()->then($callable) to call on completed transaction, ethier on success or failure
     * @return self
     */
    public function onFinish(callable $callable)
    {
        $this->getPromise()->always($callable);
        return $this;
    }


    /**
     * @param string $consumerKey
     * @param string $consumerSecret
     */
    public function authorizeOAuth($consumerKey, $consumerSecret)
    {

        return $this;
    }

    /**
     * @param string	$key	The key
     * @param string|array	$values	The value or an array of values
     * @param bool	$replace	Whether to replace the actual value or not (true by default)
     *
     * @return self
     */
    public function setHeader($key, $values, $replace = true)
    {
        $this->request->headers->set($key, $values, $replace);
        return $this;
    }

}