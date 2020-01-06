<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 19/06/15
 * Time: 10:23
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;


use evaisse\SimpleHttpBundle\Http\Exception\CurlTransportException;
use evaisse\SimpleHttpBundle\Http\Exception\HostNotFoundException;

class CurlExceptionTest extends AbstractTests
{

    public function testHostNotFoundTransportException()
    {
        $this->expectException(HostNotFoundException::class);
        list($helper, $httpKernel, $container) = $this->createContext();


        $helper->GET('http://fooooooooooooooooooooooooooooooooooooooooooooo/');

    }


    /**
     *
     */
    public function testUnknownTransportException()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $e = new CurlTransportException("test", 5000);

        $this->assertInstanceOf("evaisse\\SimpleHttpBundle\\Http\\Exception\\UnknownTransportException", $e->transformToGenericTransportException());
        $this->assertEquals("test", $e->transformToGenericTransportException()->getMessage());

    }

}