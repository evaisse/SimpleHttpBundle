<?php

namespace evaisse\SimpleHttpBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        $data = array_slice($_SERVER, 0, 4);

        $this->get('simple_http')->GET('http://httpbin.org/get', $data)->execute();
        $this->get('simple_http')->POST('http://httpbin.org/post', $data)->execute();
        $this->get('simple_http')->PUT('http://httpbin.org/put', $data)->execute();
        $this->get('simple_http')->PATCH('http://httpbin.org/patch', $data)->execute();
        $this->get('simple_http')->DELETE('http://httpbin.org/delete', $data)->execute();

        /*
         * 406
         */
        $this->get('simple_http')->DELETE('http://httpbin.org/post', $data)->execute();


        return $this->render('SimpleHttpBundle:Default:index.html.twig', array());
    }
}
