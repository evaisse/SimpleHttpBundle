<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 02/06/15
 * Time: 17:23
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;


use evaisse\SimpleHttpBundle\Http\Exception\TransportException;

class SslTest extends AbstractTests
{
    public function testSslValidationException()
    {
        $this->expectException(TransportException::class);
        list($helper, $httpKernel, $container) = $this->createContext();

        $a = $helper->prepare("GET", 'https://invalid-expected-sct.badssl.com/');
        $a->execute($httpKernel);

    }


    public function testDisablingSslVerif()
    {

        list($helper, $httpKernel, $container) = $this->createContext();

        $a = $helper->prepare("GET", 'https://invalid-expected-sct.badssl.com/');
        $a->setIgnoreSslErrors(true);
        $a->execute($httpKernel);

        $this->assertEquals($a->getResponse()->getStatusCode(), 200);
    }

}