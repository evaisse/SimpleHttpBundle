<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 02/06/15
 * Time: 11:27
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;


class SimpleRoutingTest extends AbstractTests {

    /**
     * @return array
     */
    public function provideTestCodes()
    {
        return [
            [200],
            [201],
        ];
    }



    /**
     * @dataProvider provideTestCodes
     */
    public function testCallsWithRouteArguments($code)
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $args = [
            "code" => $code,
            "another" => __FILE__
        ];

        $statement = $helper->prepare("GET", 'http://httpbin.org/status/{code}', $args);
        $request = $statement->getRequest();

        $statement->execute($httpKernel);

        $this->assertEquals($statement->getResponse()->getStatusCode(), $code);
    }

    public function testUrlTransformation()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $result = $helper->transformUrl('/foo/doh');
        $this->assertEquals($result[0], '/foo/doh');

        $result = $helper->transformUrl('/foo/doh/:param', ['param' => 'test']);
        $this->assertEquals($result[0], '/foo/doh/test');
        $this->assertEquals($result[1], []);

        $result = $helper->transformUrl('/foo/doh/:param', ['param' => 'test', 'param2' => 'test2']);
        $this->assertEquals($result[0], '/foo/doh/test');
        $this->assertEquals($result[1], ['param2' => 'test2']);

        $result = $helper->transformUrl('/foo/doh/{param}', ['param' => 'test']);
        $this->assertEquals($result[0], '/foo/doh/test');
        $this->assertEquals($result[1], []);

        $result = $helper->transformUrl('/foo/doh/{param}', ['param' => 'test', 'param2' => 'test2']);
        $this->assertEquals($result[0], '/foo/doh/test');
        $this->assertEquals($result[1], ['param2' => 'test2']);

        $result = $helper->transformUrl('/foo/doh', ['param' => 'test', 'param2' => 'test2']);
        $this->assertEquals($result[0], '/foo/doh');
        $this->assertEquals($result[1], ['param' => 'test', 'param2' => 'test2']);
    }
}
