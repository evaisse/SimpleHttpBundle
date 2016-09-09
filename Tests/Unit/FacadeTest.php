<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 01/06/15
 * Time: 11:21
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;



use evaisse\SimpleHttpBundle\Http\Kernel;
use evaisse\SimpleHttpBundle\Http\Request;
use evaisse\SimpleHttpBundle\Http\Statement;
use evaisse\SimpleHttpBundle\Service\Helper;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Container;


class FacadeTest extends AbstractTests
{


    public function provideBaseMethods()
    {
        $b = AbstractTests::$baseUrl . '';

        return [
            ['GET', $b . '/get', ],
            ['POST', $b . '/post', ],
            ['PUT', $b . '/put', ],
            ['PATCH', $b . '/patch', ],
            ['DELETE', $b . '/delete', ],
        ];
    }


    public function provideBaseTest()
    {
        $b = AbstractTests::$baseUrl . '';

        return array_merge($this->provideBaseMethods(), $this->provideWrongMethods());
    }

    public function provideWrongMethods()
    {
        $b = AbstractTests::$baseUrl . '';

        return [
            ['POST', $b . '/get', 405],
            ['PUT', $b . '/get', 405],
            ['PATCH', $b . '/get', 405],
            ['DELETE', $b . '/get', 405],
        ];

    }




    /**
     * @dataProvider provideBaseTest
     */
    public function testCalls($method, $url, $code = 200)
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $statement = $helper->prepare($method, $url);
        $request = $statement->getRequest();
        $this->assertTrue($statement instanceof Statement);
        $this->assertTrue($request instanceof Request);

        $httpKernel->execute([
            $statement
        ]);


        $this->assertEquals($statement->getResponse()->getStatusCode(), $code);

    }


    /**
     * @dataProvider provideBaseMethods
     */
    public function testCallsWithArgs($method, $url, $code = 200)
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $args = array_slice($_SERVER, 0, 3);
        $statement = $helper->prepare($method, $url, $args);
        $request = $statement->getRequest();
        $this->assertTrue($statement instanceof Statement);
        $this->assertTrue($request instanceof Request);

        $httpKernel->execute([
            $statement
        ]);

        $this->assertEquals($statement->getResponse()->getStatusCode(), $code);

    }


    /**
     * @dataProvider provideBaseMethods
     */
    public function testFacadeCalls($method, $url, $code = 200)
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $container->set('simple_http.helper', $helper);
        $container->set('simple_http.kernel', $httpKernel);

        $method = strtoupper($method);

        $args = array_slice($_SERVER, 0, 3);

        $data = $helper->$method(AbstractTests::$baseUrl . '/' . strtolower($method), $args);

        $this->assertEquals($data['headers']['Host'], explode('//', AbstractTests::$baseUrl)[1]);
    }


}