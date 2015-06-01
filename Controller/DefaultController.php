<?php

namespace evaisse\SimpleHttpBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        $http = $this->get('http');

        $http->GET('http://httpbin.org/get');
        $http->POST('http://httpbin.org/post');
        $http->PUT('http://httpbin.org/put');
        $http->PATCH('http://httpbin.org/patch');
        $http->DELETE('http://httpbin.org/delete');

        $data = array_slice($_SERVER, 0, 4);

        $http->GET('http://httpbin.org/get', $data);
        $http->POST('http://httpbin.org/post', $data);
        $http->PUT('http://httpbin.org/put', $data);
        $http->PATCH('http://httpbin.org/patch', $data);
        $http->DELETE('http://httpbin.org/delete', $data);


        $http->GET('http://httpbin.org/status/:code', [
            'code' => 200,
        ]);


        /*
         * 406
         */
        $this->get('simple_http.helper')->DELETE('http://httpbin.org/post', $data);


        return $this->render('SimpleHttpBundle:Default:index.html.twig', array());
    }
}
