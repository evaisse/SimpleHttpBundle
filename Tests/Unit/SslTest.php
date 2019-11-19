<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 02/06/15
 * Time: 17:23
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;


class SslTest extends AbstractTests
{

    /**
     * @expectedException evaisse\SimpleHttpBundle\Http\Exception\TransportException
     */
    public function testSslValidationException()
    {

        list($helper, $httpKernel, $container) = $this->createContext();

        $a = $helper->prepare("GET", 'https://badssl.com/');
        $a->execute($httpKernel);

    }


    public function testDisablingSslVerif()
    {

        list($helper, $httpKernel, $container) = $this->createContext();

        $a = $helper->prepare("GET", 'https://badssl.com/');
        $a->setIgnoreSslErrors(true);
        $a->execute($httpKernel);

        $this->assertEquals($a->getResponse()->getStatusCode(), 200);
    }

}