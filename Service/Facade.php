<?php

namespace evaisse\SimpleHttpBundle\Service;


use evaisse\SimpleHttpBundle\Http\Request;
use evaisse\SimpleHttpBundle\Http\Transaction;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Symfony\Component\BrowserKit\CookieJar;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;


class Facade implements ContainerAwareInterface
{

    use ContainerAwareTrait;

    public function __construct(ContainerInterface $container)
    {
        $this->setContainer($container);
    }

    /**
     * Send a batch of services request and returns given list with
     * services populated with responses & results
     *
     *
     * @throws Exception
     *
     * @param  array  $servicesList A simple array of ServiceInstance
     * @param  SessionInterface $session      cookie store session
     * @return array  given service list
     */
    public function execute(array $servicesList, SessionInterface $session = null, $proxy = null)
    {
        $httpClient = $this->container->get("simple_http.proxy");
        
        /*
            Fetch Cookie jar from session
         */
        $cookieJar = $session ? $session->get('simple_http.cookies', false) : false;
        $cookieJar =  $cookieJar ? $cookieJar : new CookieJar();

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

            dump($responseCookies);

            $cookieJar->updateFromSetCookie($responseCookies, $service->getRequest()->getUri());

            dump($cookieJar->allValues($service->getRequest()->getUri()));

        }

        $this->container->get('session')->set('simple_http.cookies', $cookieJar);

        /*
            Throw error if needed
         */
        foreach ($servicesList as $service) {
            if ($service->hasError()) {
                if ($service->getError() instanceof ServiceAuthenticationException) {
                    throw new HttpException(302 ,'see other', null, array(
                        'location' => '/demoapp/logout',
                    ));
                } else {
                    throw $service->getError();
                }
            }
        }

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
     * @return Service
     */
    public function createService($method, $url, $parameters = array(), $cookies = array(), $files = array(), $server = array(), $content = NULL)
    {
        $service = new Transaction(Request::create($url, $method, $parameters, $cookies, $files, array(), $content));
        $service->container = $this->container;
        return $service;
    }


    public function GET($url, array $parameters = array())
    {
        return $this->createService('GET', $url, $parameters);
    }

    public function POST($url, array $parameters = array())
    {
        return $this->createService('POST', $url, array(), array(), array(), array(), json_encode($parameters));
    }

    public function PUT($url, array $parameters = array())
    {
        return $this->createService('PUT', $url, $parameters);
    }

    public function PATCH($url, array $parameters = array())
    {
        return $this->createService('PATCH', $url, $parameters);
    }

    public function HEAD($url, array $parameters = array())
    {
        return $this->createService('HEAD', $url, $parameters);
    }

    public function DELETE($url, array $parameters = array())
    {
        return $this->createService('DELETE', $url, $parameters);
    }

}
