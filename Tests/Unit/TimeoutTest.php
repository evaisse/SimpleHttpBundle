<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 02/06/15
 * Time: 11:21
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;



class TimeoutTest extends AbstractTests
{


    /**
     * @expectedException evaisse\SimpleHttpBundle\Http\Error
     */
    public function testParrellelExecutionWithError()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $a = $helper->prepare("GET", 'http://httpbin.org/delay/1');
        $a->setTimeout(800);
        $a->execute($httpKernel);


//        $this->assertEquals($a->hasError(), true);
    }



}