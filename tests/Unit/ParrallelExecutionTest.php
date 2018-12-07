<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 02/06/15
 * Time: 11:17
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;

use evaisse\SimpleHttpBundle\Http\Kernel;
use evaisse\SimpleHttpBundle\Http\Request;
use evaisse\SimpleHttpBundle\Http\Statement;
use evaisse\SimpleHttpBundle\Service\Helper;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Container;


class ParrallelExecutionTest extends AbstractTests
{


    /**
     *
     */
    public function testParrelelExecution()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $a = $helper->prepare("GET", AbstractTests::$baseUrl . '/ip');
        $b = $helper->prepare("PUT", AbstractTests::$baseUrl . '/put');
        $c = $helper->prepare("POST", AbstractTests::$baseUrl . '/post');


        $helper->execute([
            $a, $b, $c
        ], null, $httpKernel);

        $this->assertEquals(array_key_exists('origin', $a->getResult()), true);
        $this->assertEquals(array_key_exists('form', $b->getResult()), true);
        $this->assertEquals(array_key_exists('form', $c->getResult()), true);
    }



    /**
     *
     */
    public function testParrellelExecutionWithError()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $a = $helper->prepare("GET", AbstractTests::$baseUrl . '/delay/3');
        $a->setTimeout(800);

        $b = $helper->prepare("PUT", AbstractTests::$baseUrl . '/put');
        $c = $helper->prepare("POST", AbstractTests::$baseUrl . '/post');


        $helper->execute([
            $a, $b, $c
        ], null, $httpKernel);

        $this->assertEquals($a->hasError(), true);
        $this->assertEquals(array_key_exists('form', $b->getResult()), true);
        $this->assertEquals(array_key_exists('form', $c->getResult()), true);
    }


    /**
     *
     */
    public function testSequenceExecution()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $a = $helper->prepare("GET", AbstractTests::$baseUrl . '/ip');
        $b = $helper->prepare("PUT", AbstractTests::$baseUrl . '/put');
        $c = $helper->prepare("POST", AbstractTests::$baseUrl . '/post');

        $helper
            ->execute([$a], null, $httpKernel)
            ->execute([$b], null, $httpKernel)
            ->execute([$c], null, $httpKernel);

        $this->assertEquals(array_key_exists('origin', $a->getResult()), true);
        $this->assertEquals(array_key_exists('form', $b->getResult()), true);
        $this->assertEquals(array_key_exists('form', $c->getResult()), true);
    }


}