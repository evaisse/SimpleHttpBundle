<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 02/06/15
 * Time: 15:41
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;


class ExceptionsTest extends AbstractTests
{

    /**
     * @return array
     */
    public function provideHttpClientErrorCodes()
    {
        $codes = array();
        foreach (range(400, 417) as $code) {
            $codes[] = array($code);
        }
        return $codes;
    }

    /**
     * @return array
     */
    public function provideHttpServerErrorCodes()
    {
        $codes = array();
        foreach (range(500, 508) as $code) {
            $codes[] = array($code);
        }
        return $codes;
    }

    /**
     * @expectedException evaisse\SimpleHttpBundle\Http\Exception\RequestNotSentException
     */
    public function testResultException()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $stmt = $helper->prepare('GET', 'http://httpbin.org/ip');

        $stmt->getResult();
    }


    /**
     * @dataProvider provideHttpClientErrorCodes
     * @expectedException evaisse\SimpleHttpBundle\Http\Exception\HttpClientError
     */
    public function testResultWithClientErrorException($code)
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $stmt = $helper->prepare('GET', 'http://httpbin.org/status/:code', array(
            'code' => $code,
        ));

        $httpKernel->execute([
            $stmt
        ]);

        $this->assertInstanceOf('evaisse\SimpleHttpBundle\Http\Exception', $stmt->getError());

        $stmt->getResult();
    }

    /**
     * @dataProvider provideHttpServerErrorCodes
     * @expectedException evaisse\SimpleHttpBundle\Http\Exception\HttpServerError
     */
    public function testResultWithServerErrorException($code)
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $stmt = $helper->prepare('GET', 'http://httpbin.org/status/:code', array(
            'code' => $code,
        ));

        $httpKernel->execute([
            $stmt
        ]);

        $this->assertInstanceOf('evaisse\SimpleHttpBundle\Http\Exception', $stmt->getError());

        $stmt->getResult();
    }

}