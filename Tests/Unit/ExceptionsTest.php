<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 02/06/15
 * Time: 15:41
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;


use evaisse\SimpleHttpBundle\Http\Exception\RequestNotSentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExceptionsTest extends AbstractTests
{

    /**
     * @return array
     */
    public function provideClientErrorHttpExceptionCodes()
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
    public function provideServerErrorHttpExceptionCodes()
    {
        $codes = array();
        foreach (range(500, 508) as $code) {
            $codes[] = array($code);
        }
        return $codes;
    }

    /**
     * @return array
     */
    public function provideErrorExceptionsClasses()
    {
        return [
            [400, "\\Symfony\\Component\\HttpKernel\\Exception\\BadRequestHttpException"],
            [401, "\\Symfony\\Component\\HttpKernel\\Exception\\UnauthorizedHttpException"],
            [403, "\\evaisse\\SimpleHttpBundle\\Http\\Exception\\ForbiddenHttpException"],
            [404, "\\Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException"],
            [405, "\\Symfony\\Component\\HttpKernel\\Exception\\MethodNotAllowedHttpException"],
            [406, "\\Symfony\\Component\\HttpKernel\\Exception\\NotAcceptableHttpException"],
            [407, "\\evaisse\\SimpleHttpBundle\\Http\\Exception\\ProxyAuthenticationRequiredHttpException"],
            [408, "\\evaisse\\SimpleHttpBundle\\Http\\Exception\\RequestTimeoutHttpException"],
            [409, "\\Symfony\\Component\\HttpKernel\\Exception\\ConflictHttpException"],
            [410, "\\Symfony\\Component\\HttpKernel\\Exception\\GoneHttpException"],
            [411, "\\Symfony\\Component\\HttpKernel\\Exception\\LengthRequiredHttpException"],
            [412, "\\Symfony\\Component\\HttpKernel\\Exception\\PreconditionFailedHttpException"],
            [413, "\\evaisse\\SimpleHttpBundle\\Http\\Exception\\RequestEntityTooLargeHttpException"],
            [414, "\\evaisse\\SimpleHttpBundle\\Http\\Exception\\RequestUriTooLongHttpException"],
            [415, "\\Symfony\\Component\\HttpKernel\\Exception\\UnsupportedMediaTypeHttpException"],
            [416, "\\evaisse\\SimpleHttpBundle\\Http\\Exception\\RequestedRangeNotSatisfiableHttpException"],
            [417, "\\evaisse\\SimpleHttpBundle\\Http\\Exception\\ExpectationFailedHttpException"],
            [500, "\\evaisse\\SimpleHttpBundle\\Http\\Exception\\InternalServerErrorHttpException"],
            [501, "\\evaisse\\SimpleHttpBundle\\Http\\Exception\\NotImplementedHttpException"],
            [502, "\\evaisse\\SimpleHttpBundle\\Http\\Exception\\BadGatewayHttpException"],
            [503, "\\Symfony\\Component\\HttpKernel\\Exception\\ServiceUnavailableHttpException"],
            [504, "\\evaisse\\SimpleHttpBundle\\Http\\Exception\\GatewayTimeoutHttpException"],
            [505, "\\evaisse\\SimpleHttpBundle\\Http\\Exception\\HttpVersionNotSupportedHttpException"],
        ];
    }

    public function testResultException()
    {
        $this->expectException(RequestNotSentException::class);
        list($helper, $httpKernel) = $this->createContext();

        $stmt = $helper->prepare('GET', AbstractTests::$baseUrl . '/ip');

        $stmt->getResult();
    }


    /**
     * @dataProvider provideClientErrorHttpExceptionCodes
     */
    public function testResultWithClientErrorException($code)
    {
        $this->expectException(HttpException::class);
        list($helper, $httpKernel) = $this->createContext();

        $stmt = $helper->prepare('GET', AbstractTests::$baseUrl . '/status/{code}', array(
            'code' => $code,
        ));

        $httpKernel->execute([
            $stmt
        ]);

        $this->assertInstanceOf('\Symfony\Component\HttpKernel\Exception\HttpException', $stmt->getError());

        $stmt->getResult();
    }

    /**
     * @dataProvider provideServerErrorHttpExceptionCodes
     */
    public function testResultWithServerErrorException($code)
    {
        $this->expectException(HttpException::class);
        $this->expectException(HttpException::class);
        list($helper, $httpKernel) = $this->createContext();

        $stmt = $helper->prepare('GET', AbstractTests::$baseUrl . '/status/{code}', array(
            'code' => $code,
        ));

        $httpKernel->execute([
            $stmt
        ]);

        $this->assertInstanceOf('\Symfony\Component\HttpKernel\Exception\HttpException', $stmt->getError());

        $stmt->getResult();
    }



    /**
     * @dataProvider provideErrorExceptionsClasses
     */
    public function testExpectedInstancesOfExceptions($code, $cls)
    {
        list($helper, $httpKernel) = $this->createContext();

        $stmt = $helper->prepare('GET', AbstractTests::$baseUrl . '/status/{code}', array(
            'code' => $code,
        ));

        $httpKernel->execute([
            $stmt
        ]);


        $this->assertInstanceOf($cls, $stmt->getError());

        $this->assertEquals($code, $stmt->getError()->getStatusCode());

        if (strpos($cls, "SimpleHttpBundle")) {
            if ($code >= 500) {
                $this->assertInstanceOf("\\evaisse\\SimpleHttpBundle\\Http\\Exception\\ServerErrorHttpException", $stmt->getError());
            } else {
                $this->assertInstanceOf("\\evaisse\\SimpleHttpBundle\\Http\\Exception\\ClientErrorHttpException", $stmt->getError());
            }
        }

    }



    /**
     * Test response header allow: POST, PUT, ...
     */
    public function testAllowedMethodsFor405()
    {
        list($helper, $httpKernel) = $this->createContext();

        $stmt = $helper->prepare('GET', AbstractTests::$baseUrl . '/put');

        $httpKernel->execute([
            $stmt
        ]);

        $this->assertInstanceOf('\Symfony\Component\HttpKernel\Exception\HttpException', $stmt->getError());
        $this->assertInstanceOf('\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException', $stmt->getError());

        $headers = $stmt->getError()->getHeaders();

        $allowedMethods = explode(', ', $headers['Allow']);

        $this->assertContains('PUT', $allowedMethods);

    }
}