<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 02/06/15
 * Time: 11:19
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;


use evaisse\SimpleHttpBundle\Http\Exception\InvalidResponseBodyException;
use evaisse\SimpleHttpBundle\Http\Response;

class ContentTypesTest extends AbstractTests
{


    /**
     * @return array
     */
    public function provideInvalidContentTypes()
    {
        $data = [];

        $data[] = [
            'application/json',
            utf8_decode('{"a":"é"}'),
        ];

        $data[] = [
            'application/json',
            '{',
        ];

        return $data;
    }

    public function testContentTypesDetection()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $stmt = $helper->prepare('GET', 'http://httpbin.org/ip');

        $stmt->execute($httpKernel);

        $res = $stmt->getResult();

        $this->assertEquals($stmt->getResponse()->headers->get('content-type'), 'application/json');
        $this->assertArrayHasKey('origin', $res);
    }


    public function testFileObjectDetection()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $stmt = $helper->prepare('GET', 'http://httpbin.org/image/png');

        $stmt->execute($httpKernel);

        $this->assertEquals($stmt->getResponse()->headers->get('content-type'), 'image/png');
        $this->assertTrue(is_string($stmt->getResult()));

    }

    /**
     * @dataProvider provideInvalidContentTypes
     */
    public function testInvalidJsonData($contentType, $body)
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $response = new Response($body, 200, [
            'Content-Type' => $contentType,
        ]);

        $this->assertInstanceOf('\evaisse\SimpleHttpBundle\Http\Exception\InvalidResponseBodyException',
                                $response->getError());
    }
}