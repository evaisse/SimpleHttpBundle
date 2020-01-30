<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 02/06/15
 * Time: 11:15
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;



use evaisse\SimpleHttpBundle\Http\Kernel;
use evaisse\SimpleHttpBundle\Http\Request;
use evaisse\SimpleHttpBundle\Http\Statement;
use evaisse\SimpleHttpBundle\Service\Helper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;


class AbstractTests extends TestCase
{

//    public static $baseUrl = "http://127.0.0.1:8989";
    public static $baseUrl = "http://httpbin.org";

    protected function createContext()
    {
        $eventDispatcher = new EventDispatcher();
        $httpKernel = new Kernel($eventDispatcher);
        $helper = new Helper($httpKernel, $eventDispatcher);

        return [$helper, $httpKernel];
    }


}