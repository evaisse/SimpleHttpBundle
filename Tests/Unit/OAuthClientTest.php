<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 15/06/15
 * Time: 10:34
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;


use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class OAuthClientTest extends AbstractTests
{


    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException
     */
    public function testOAuthUnauthorizedAccess()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $host = "http://127.0.0.1:8008";

        $helper->GET("$host/verify");
    }


    /**
     *
     */
    public function testPersistOAuthClientCredentials()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $host = "http://127.0.0.1:8008";

        try {

            $helper->prepare("GET", "$host/verify")->execute();

        } catch (UnauthorizedHttpException $e) {

            $data = $helper->prepare('POST', "$host/token", [
                'grant_type'        => "client_credentials",
                "client_id"         => "manu",
                "client_secret"     => "3yu1uuet67uowc404c8cc80os480os8",
                "scope"             => 'testing',
            ])->execute()->getResult();
            $accessToken = $data['access_token'];

        }

        $helper->prepare('GET', "$host/verify")
            ->setHeader('Authorization', "Bearer $accessToken")
            ->execute();
    }



    /**
     *
     */
    public function testOAuth2driver()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $host = "http://127.0.0.1:8008";

        $driver = new OAuth2ClientCredentialDriver("$host/token",
                                                  "manu",
                                                  "3yu1uuet67uowc404c8cc80os480os8");

        $helper->prepare('')

        try {

            $helper->prepare("GET", "$host/verify")->execute();

        } catch (UnauthorizedHttpException $e) {

            $data = $helper->prepare('POST', "$host/token", [
                'grant_type'        => "client_credentials",
                "client_id"         => "manu",
                "client_secret"     => "3yu1uuet67uowc404c8cc80os480os8",
                "scope"             => 'testing',
            ])->execute()->getResult();
            $accessToken = $data['access_token'];

        }

        $helper->prepare('GET', "$host/verify")
            ->setHeader('Authorization', "Bearer $accessToken")
            ->execute();
    }




}