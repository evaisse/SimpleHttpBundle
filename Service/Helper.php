<?php

namespace evaisse\SimpleHttpBundle\Service;


use evaisse\SimpleHttpBundle\Http\Kernel;
use evaisse\SimpleHttpBundle\Http\Request;
use evaisse\SimpleHttpBundle\Http\SessionCookieJar;
use evaisse\SimpleHttpBundle\Http\Statement;


use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Symfony\Component\BrowserKit\CookieJar;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;


class Helper implements ContainerAwareInterface
{

    use ContainerAwareTrait;

    public function __construct(ContainerInterface $container)
    {
        $this->setContainer($container);
    }

    /**
     * @param SessionInterface $session a given session
     * @return SessionCookieJar
     */
    public function createCookieSession(SessionInterface $session = null)
    {
        return new SessionCookieJar($session);
    }

    /**
     * Send a batch of services request and returns given list with
     * services populated with responses & results
     *
     * @throws Exception
     *
     * @param  array            $servicesList   A simple array of ServiceInstance
     * @param  SessionCookieJar $cookieJar      cookie store session
     * @param  Kernel           $client         http client proxy to use
     * @return array  given service list
     */
    public function execute(array $servicesList, SessionCookieJar $cookieJar = null, Kernel $client = null)
    {
        $httpClient = $client ? $client : $this->container->get("simple_http.proxy");
        
        /*
            Fetch Cookie jar from session
         */
        $cookieJar =  $cookieJar ? $cookieJar : new SessionCookieJar();

        foreach ($servicesList as $service) {
            $sessionCookies = $cookieJar->allValues($service->getRequest()->getUri());
            foreach ($sessionCookies as $cookieName => $cookieValue) {
                $service->getRequest()->cookies->set($cookieName, $cookieValue);
            }

        }

        $httpClient->execute($servicesList);

        /*
            Persist cookies
         */
        foreach ($servicesList as $service) {

            if (!$service->getResponse()) {
                continue;
            }

            $responseCookies = array();
            foreach ($service->getResponse()->headers->getCookies() as $k => $cookie) {
                $responseCookies[] = (string)$cookie;
            }

            $cookieJar->updateFromSetCookie($responseCookies, $service->getRequest()->getUri());
        }

        $cookieJar->save();

        return $this;
    }


    /**
     * @param string $method HTTP method
     * @param string $url
     * @param array $parameters
     * @param array $cookies
     * @param array $files
     * @param array $server
     * @param null $content
     * @return Transaction
     */
    public function prepare($method, $url, $parameters = array(), $cookies = array(), $files = array(), $server = array(), $content = NULL)
    {
        list($url, $parameters) = $this->transformUrl($url, $parameters);
        $service = new Statement(Request::create($url, $method, $parameters, $cookies, $files, array(), $content));
        $service->setContainer($this->container);
        return $service;
    }


    /**
     * Transform a given url pattern like /status/:code/fetch with parameters array('code' => 200) into /status/200
     *
     * @param string $urlPattern
     * @param array $parameters
     *
     * @return array transformed url and remainings params
     */
    public function transformUrl($urlPattern, array $parameters = array())
    {
        if (empty($parameters) || !preg_match('/:[a-z]/', $urlPattern)) {
            return array($urlPattern, $parameters); // no need to transform plain uri
        }

        foreach ($parameters as $key => $value) {
            $urlPattern = str_replace(":$key", $value, $urlPattern);
            unset($parameters[$key]);
        }

        return array($urlPattern, $parameters);
    }


    protected function fire($method, $url, array $parameters = array())
    {
        $transaction = $this->prepare($method, $url, $parameters);
        $transaction->execute();
        return $transaction->getResult();
    }



    public function GET($url, array $parameters = array())
    {
        return $this->fire('GET', $url, $parameters);
    }

    public function POST($url, array $parameters = array())
    {
        return $this->fire('POST', $url, $parameters);
    }

    public function PUT($url, array $parameters = array())
    {
        return $this->fire('PUT', $url, $parameters);
    }

    public function PATCH($url, array $parameters = array())
    {
        return $this->fire('PATCH', $url, $parameters);
    }

    public function HEAD($url, array $parameters = array())
    {
        return $this->fire('HEAD', $url, $parameters);
    }

    public function DELETE($url, array $parameters = array())
    {
        return $this->fire('DELETE', $url, $parameters);
    }



}
