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

    public function testTimeoutException()
    {

    }

    public function testHostNotFoundException()
    {

    }

    /**
     * @expectedException evaisse\SimpleHttpBundle\Http\Exception\SslException
     */
    public function testSslValidationException()
    {

        list($helper, $httpKernel, $container) = $this->createContext();

        $a = $helper->prepare("GET", 'https://www.pcwebshop.co.uk/');
        $a->setTimeout(700);
        $a->execute($httpKernel);

    }
}