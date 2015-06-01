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
use evaisse\SimpleHttpBundle\Http\Transaction;
use evaisse\SimpleHttpBundle\Service\Helper;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Container;


class FacadeTest extends \PHPUnit_Framework_TestCase
{


    protected function createContext()
    {
        $container = new Container(new ParameterBag());
        $helper = new Helper($container);
        $httpKernel = new Kernel($container);
        return [$helper, $httpKernel, $container];
    }


    public function provideBaseMethods()
    {
        $b = 'http://httpbin.org';

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
        $b = 'http://httpbin.org';

        return array_merge($this->provideBaseMethods(), $this->provideWrongMethods());
    }

    public function provideWrongMethods()
    {
        $b = 'http://httpbin.org';

        return [
            ['POST', $b . '/get', 405],
            ['PUT', $b . '/get', 405],
            ['PATCH', $b . '/get', 405],
            ['DELETE', $b . '/get', 405],
        ];

    }





    /**
     *
     */
    public function testArgumentsReturns()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $statement = $helper->prepare("GET", 'http://httpbin.org/ip');

        $request = $statement->getRequest();
        $this->assertTrue($statement instanceof Transaction);
        $this->assertTrue($request instanceof Request);
        $statement->execute($httpKernel);

        $res = $statement->getResult();

        $this->assertEquals(array_key_exists('origin', $res), true);


        $args = array(
            'foo' => 'bar',
        );


        $statement = $helper->prepare("GET", 'http://httpbin.org/post', $args);
        $statement->execute($httpKernel);
        $res = $statement->getResult();

        var_dump($res);

        $this->assertEquals($res['form'], $args);


        $statement = $helper->prepare("POST", 'http://httpbin.org/post', $args);
        $statement->execute($httpKernel);
        $res = $statement->getResult();

        $this->assertEquals($res['form'], $args);

        $statement = $helper->prepare("POST", 'http://httpbin.org/post', $args);
        $statement->jsonify();
        $statement->execute($httpKernel);
        $res = $statement->getResult();


        $this->assertEquals($res['json'], $args);

    }


    /**
     *
     */
    public function testTimeouts()
    {

    }



    /**
     *
     */
    public function testParrallelExecution()
    {

    }

    /**
     *
     */
    public function testPromises()
    {

    }

    /**
     *
     */
    public function testSynchronousExecution()
    {

    }






    /**
     * @dataProvider provideBaseTest
     */
    public function testCalls($method, $url, $code = 200)
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $statement = $helper->prepare($method, $url);
        $request = $statement->getRequest();
        $this->assertTrue($statement instanceof Transaction);
        $this->assertTrue($request instanceof Request);
        $statement->execute($httpKernel);

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
        $this->assertTrue($statement instanceof Transaction);
        $this->assertTrue($request instanceof Request);
        $statement->execute($httpKernel);

        $this->assertEquals($statement->getResponse()->getStatusCode(), $code);

    }



    /**
     * @dataProvider provideBaseMethods
     */
    public function testCallsWithRouteArguments($method, $url, $code = 200)
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $args = [
            "code" => 200,
            "another" => __FILE__
        ];

        $statement = $helper->prepare($method, 'http://httpbin.org/status/:code', $args);
        $request = $statement->getRequest();
        $this->assertTrue($statement instanceof Transaction);
        $this->assertTrue($request instanceof Request);
        $statement->execute($httpKernel);

        $this->assertEquals($statement->getResponse()->getStatusCode(), $code);

    }



}