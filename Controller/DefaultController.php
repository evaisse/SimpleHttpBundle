<?php

namespace evaisse\SimpleHttpBundle\Controller;

use evaisse\SimpleHttpBundle\Http\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class DefaultController extends Controller
{
    public function indexAction()
    {
        $data = [
            'foo' => "bar",
            'test' => [
                'i' => 'j',
            ]
        ];


        $http = $this->get('http');

        $host = "http://localhost:8009/oauth2";
        $accessToken = false;

        try {
            $http->prepare('GET', "$host/verify")->execute();
        } catch (UnauthorizedHttpException $e) {
            try {
                $accessToken = $http->POST("$host/token", [
                    'grant_type' => "client_credentials",
                    "client_id" => "demouser",
                    "client_secret" => "demopassword",
                    "scope" => 'testing',
                ])['access_token'];
            } catch (\Exception $e) {
            }
        } catch (\Exception $e) {
//            return $this->render('SimpleHttpBundle:Default:index.html.twig', array());
        }

        if ($e) {
            dump($e);
        }

        if (!$accessToken) {
            return $this->render('SimpleHttpBundle:Default:index.html.twig', array());
        }


        $http->prepare('GET', "$host/verify")
             ->setHeader('Authorization', "Bearer $accessToken")
             ->execute();

        return $this->render('SimpleHttpBundle:Default:index.html.twig', array());
    }
}
