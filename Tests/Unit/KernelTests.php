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
        list($helper, $httpKernel) = $this->createContext();

        $stmt = $helper->prepare('GET', AbstractTests::$baseUrl . '/');

        $httpKernel->handle($stmt->getRequest());
    }

}