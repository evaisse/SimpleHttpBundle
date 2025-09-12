<?php
/**
 * This cookiejar class invert dependency against the session interface
 * User: evaisse
 * Date: 01/06/15
 * Time: 11:13
 */

namespace evaisse\SimpleHttpBundle\Http;


use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class SessionCookieJar extends CookieJar
{

    /**
     * @var SessionInterface
     */
    protected $session;

    /**
     * key in which will be stored the cookie jar
     * @var string $cookieJarName, key in which will be stored the cookie jar
     */
    protected $cookieJarName;

    /**
     * @param SessionInterface $session Session storage to persist cookieJar
     * @param string $cookieJarName A key in which will be stored the cookie jar
     */
    function __construct(?SessionInterface $session = null, $cookieJarName = "_simple_http.cookiejar")
    {
        $session = $session ? $session : new Session(new MockArraySessionStorage());
        $this->setSession($session);
        $this->setCookieJarName($cookieJarName);
        $this->load();
        $this->save();
    }


    /**
     * @return string
     */
    public function getCookieJarName()
    {
        return $this->cookieJarName;
    }

    /**
     * @param string $cookieJarName
     */
    protected function setCookieJarName($cookieJarName)
    {
        $this->cookieJarName = $cookieJarName;
    }

    /**
     * @return SessionInterface
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * @param SessionInterface $session
     */
    protected function setSession($session)
    {
        $this->session = $session;
    }

    /**
     * Load cookie JAR from session
     */
    function load()
    {
        $this->cookieJar = $this->getSession()->get($this->getCookieJarName(), []);
    }

    /**
     * Save in session the current cookie JAR
     */
    function save()
    {
        $this->getSession()->set($this->getCookieJarName(), $this->cookieJar);
    }
}