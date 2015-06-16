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
     * @expectedException \evaisse\SimpleHttpBundle\Http\Exception\RequestNotSentException
     */
    public function testResultException()
    {

        list($helper, $httpKernel, $container) = $this->createContext();

        $stmt = $helper->prepare('GET', 'http://httpbin.org/ip');

        $stmt->getResult();
    }


    /**
     * @dataProvider provideHttpClientErrorCodes
     * @expectedException \Symfony\Component\HttpKernel\Exception\HttpException
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

        $this->assertInstanceOf('\Symfony\Component\HttpKernel\Exception\HttpException', $stmt->getError());

        $stmt->getResult();
    }

    /**
     * @dataProvider provideHttpServerErrorCodes
     * @expectedException \Symfony\Component\HttpKernel\Exception\HttpException
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

        $this->assertInstanceOf('\Symfony\Component\HttpKernel\Exception\HttpException', $stmt->getError());

        $stmt->getResult();
    }

}