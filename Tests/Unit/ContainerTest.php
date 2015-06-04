<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 04/06/15
 * Time: 11:51
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;


class ContainerTest extends AbstractTests
{

    function testCOntainer()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $container->set('simple_http.helper', $helper);
        $container->set('simple_http.kernel', $httpKernel);
    }


}