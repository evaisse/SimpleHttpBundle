<?php

namespace evaisse\SimpleHttpBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

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
//
//        $http->GET('http://httpbin.org/get');
//        $http->POST('http://httpbin.org/post');
//        $http->PUT('http://httpbin.org/put');
//        $http->PATCH('http://httpbin.org/patch');
//        $http->DELETE('http://httpbin.org/delete');
//
//        $http->GET('http://httpbin.org/get', $data);
//        $http->POST('http://httpbin.org/post', $data);
//        $http->PUT('http://httpbin.org/put', $data);
//        $http->PATCH('http://httpbin.org/patch', $data);
//        $http->DELETE('http://httpbin.org/delete', $data);
//
//
//        $http->GET('http://httpbin.org/status/:code', [
//            'code' => 200,
//        ]);


//        /*
//         * 406
//         */
//        $this->get('simple_http.helper')->DELETE('http://httpbin.org/post', $data);


//        dump($this->get('simple_http.helper')->POST('http://httpbin.org/post', $data));


//        $update_json = json_encode($data);
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, 'http://httpbin.org/put');
//        curl_setopt($ch, CURLOPT_USERAGENT, 'SugarConnector/1.4');
//        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($update_json)));
//        curl_setopt($ch, CURLOPT_VERBOSE, 1);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
//        curl_setopt($ch, CURLOPT_POSTFIELDS, $update_json);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
//        $chleadresult = curl_exec($ch);
//        $chleadapierr = curl_errno($ch);
//        $chleaderrmsg = curl_error($ch);
//        curl_close($ch);

//        dump($chleadresult);

        $stmt = $http->prepare('GET', 'https://staging.meselus.com/', [
            'seconds' => 1
        ]);

        $stmt->json();
        $stmt->setTimeout(800);
        $stmt->execute();

        dump($stmt->getResult());

        return $this->render('SimpleHttpBundle:Default:index.html.twig', array());
    }
}
