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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Container;



class AbstractTests extends \PHPUnit_Framework_TestCase
{

    protected function createContext()
    {
        $container = new Container(new ParameterBag());
        $helper = new Helper($container);
        $httpKernel = new Kernel($container);
        return [$helper, $httpKernel, $container];
    }


}