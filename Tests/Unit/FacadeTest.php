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
    public function testPOSTJSON()
    {

    }


    /**
     *
     */
    public function testPUTJSON()
    {

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
     * test using PUT, POST
     */
    public function testFilesUpload()
    {

    }

    /**
     *
     */
    public function testFilesDownload()
    {

    }


    /**
     *
     */
    public function testHttpCode100()
    {

    }

    public function testCookiePersistance()
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
        $this->assertTrue($statement instanceof Statement);
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
        $this->assertTrue($statement instanceof Statement);
        $this->assertTrue($request instanceof Request);
        $statement->execute($httpKernel);

        $this->assertEquals($statement->getResponse()->getStatusCode(), $code);

    }






}