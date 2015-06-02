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

        $statement = $helper->prepare("GET", 'http://httpbin.org/status/:code', $args);
        $request = $statement->getRequest();

        $statement->execute($httpKernel);

        $this->assertEquals($statement->getResponse()->getStatusCode(), $code);

    }

}