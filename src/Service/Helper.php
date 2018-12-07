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
     *
     * @return CookieJar
     */
    public function getDefaultCookieJar()
    {
        return $this->createCookieJar();
    }

    /**
     * @param SessionInterface $session session to store the cookies
     * @return SessionCookieJar
     */
    public function createCookieJar(SessionInterface $session = null)
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
        $httpClient = $client ? $client : $this->container->get("simple_http.kernel");
        
        /*
            Fetch Cookie jar from session
         */
        $cookieJar =  $cookieJar ? $cookieJar : $this->getDefaultCookieJar();

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
     * @param Request $request request to start a statement
     * @return Statement
     */
    protected function createStatement(Request $request)
    {
        return new Statement($request);
    }


    /**
     * Creates a Request based on a given URI and configuration.
     *
     * The information contained in the URI always take precedence
     * over the other information (server and parameters).
     *
     * @param string $uri        The URI
     * @param string $method     The HTTP method
     * @param array  $parameters The query (GET) or request (POST) parameters
     * @param array  $cookies    The request cookies ($_COOKIE)
     * @param array  $files      The request files ($_FILES)
     * @param array  $server     The server parameters ($_SERVER)
     * @param string $content    The raw body data
     *
     * @return \Symfony\Component\HttpFoundation\Request
     */
    protected function createRequest($uri, $method = 'GET', $parameters = array(), $cookies = array(), $files = array(), $server = array(), $content = null)
    {
        return Request::create($uri, $method, $parameters, $cookies, $files, array(), $content);
    }

    /**
     * Prepare a statement service for a given request, alias for Request::create()
     *
     * @param string $method HTTP method
     * @param string $url url or url pattern like /status/:code/fetch with parameters array('code' => 200) into /status/200
     * @param array $parameters a list of parameters interpolated with url route if needed
     * @param array $cookies an hash of cookieName => Value
     * @param array $files a hash of files infos
     * @param array $server server infos
     * @param string $content optionnal RAW body content
     * @return \evaisse\SimpleHttpBundle\Http\Statement
     */
    public function prepare($method, $url, $parameters = array(), $cookies = array(), $files = array(), $server = array(), $content = NULL)
    {
        list($url, $parameters) = $this->transformUrl($url, $parameters);
        $request = $this->createRequest($url, $method, $parameters, $cookies, $files, array(), $content);
        $service = $this->createStatement($request);
        $service->setContainer($this->container);
        return $service;
    }

    /**
     * Transform a given url pattern like /status/{code}/fetch with parameters array('code' => 200) into /status/200
     *
     * @param string $urlPattern
     * @param array $parameters
     *
     * @return array transformed url and remainings params
     */
    public function transformUrl($urlPattern, array $parameters = array())
    {
        if (empty($parameters)
            || !preg_match('/\{[a-z+][a-z0-9_]+\}/i', $urlPattern)
        ) {
            return array($urlPattern, $parameters); // no need to transform plain uri
        }

        foreach ($parameters as $key => $value) {
            if (strpos($urlPattern, '{' . $key . '}') !== false) {
                $urlPattern = str_replace('{' . $key . '}', urlencode($value), $urlPattern);
                unset($parameters[$key]);
            }
        }

        return array($urlPattern, $parameters);
    }

    /**
     * @param string $method http method to send
     * @param string $url url pattern
     * @param array $parameters a list of parameters interpolated with url route if needed
     * @return mixed http transaction body result, automaticaly parsed if json is returned
     */
    protected function fire($method, $url, array $parameters = array())
    {
        $transaction = $this->prepare($method, $url, $parameters);

        $this->execute([
            $transaction
        ]);

        if ($transaction->hasError()) {
            throw $transaction->getError();
        }

        return $transaction->getResult();
    }


    /**
     * @param string $url url pattern
     * @param array $parameters a list of parameters interpolated with url route if needed
     * @return mixed http transaction body result, automaticaly parsed if json is returned
     */
    public function GET($url, array $parameters = array())
    {
        return $this->fire('GET', $url, $parameters);
    }

    /**
     * @param string $url url pattern
     * @param array $parameters a list of parameters interpolated with url route if needed
     * @return mixed http transaction body result, automaticaly parsed if json is returned
     */
    public function POST($url, array $parameters = array())
    {
        return $this->fire('POST', $url, $parameters);
    }

    /**
     * @param string $url url pattern
     * @param array $parameters a list of parameters interpolated with url route if needed
     * @return mixed http transaction body result, automaticaly parsed if json is returned
     */
    public function PUT($url, array $parameters = array())
    {
        return $this->fire('PUT', $url, $parameters);
    }

    /**
     * @param string $url url pattern
     * @param array $parameters a list of parameters interpolated with url route if needed
     * @return mixed http transaction body result, automaticaly parsed if json is returned
     */
    public function PATCH($url, array $parameters = array())
    {
        return $this->fire('PATCH', $url, $parameters);
    }

    /**
     * @param string $url url pattern
     * @param array $parameters a list of parameters interpolated with url route if needed
     * @return mixed http transaction body result, automaticaly parsed if json is returned
     */
    public function DELETE($url, array $parameters = array())
    {
        return $this->fire('DELETE', $url, $parameters);
    }



}
