<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 02/06/15
 * Time: 11:21
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;





use evaisse\SimpleHttpBundle\Http\Exception\TimeoutException;

class TimeoutTest extends AbstractTests
{



    public function testEmptyResponseOnTransportErrors()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $a = $helper->prepare("GET", AbstractTests::$baseUrl . '/delay/1');
        $a->setTimeout(800);

        $httpKernel->execute([
            $a,
        ]);

        $this->assertEquals($a->getResponse(), null);
    }

    public function testTimeoutExecutionWithError()
    {
        $this->expectException(TimeoutException::class);
        list($helper, $httpKernel, $container) = $this->createContext();

        $a = $helper->prepare("GET", AbstractTests::$baseUrl . '/delay/1');
        $a->setTimeout(800);
        $a->execute($httpKernel);
    }



}