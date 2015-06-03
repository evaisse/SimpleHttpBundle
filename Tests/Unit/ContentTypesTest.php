<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 02/06/15
 * Time: 11:19
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;


class ContentTypesTest extends AbstractTests
{


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


}