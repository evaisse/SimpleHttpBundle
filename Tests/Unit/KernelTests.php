<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 04/06/15
 * Time: 11:52
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;


class KernelTests extends AbstractTests
{


    public function testClassicKernelApi()
    {
        list($helper, $httpKernel, $container) = $this->createContext();


        $stmt = $helper->prepare('GET', 'http://httpbin.org/');

        $httpKernel->handle($stmt->getRequest());
    }

}