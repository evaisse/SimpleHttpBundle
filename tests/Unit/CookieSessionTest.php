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


class CookieSessionTest extends AbstractTests
{



    public function testCookieStore()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $i = (int)rand(0,100);
        $j = (int)rand(0,100);

        $cookieSession = $helper->getDefaultCookieJar();

        /*
         * Sent a tmp cookie value
         */

        $stmt = $helper->prepare('GET', AbstractTests::$baseUrl . '/cookies/set', [
            'tmp'  => 1,
        ]);

        $helper->execute([
            $stmt
        ], $cookieSession, $httpKernel);


        $cookies = $cookieSession->allValues(AbstractTests::$baseUrl . '');

        $this->assertEquals($cookies['tmp'], 1);


        /*
         * Set and delete some async
         */

        $stmt1 = $helper->prepare('GET', AbstractTests::$baseUrl . '/cookies/set', [
            'foo'  => $i,
            'bar'  => $j,
        ]);

        $stmt2 = $helper->prepare('GET', AbstractTests::$baseUrl . '/cookies/set', [
            'also'  => $i,
        ]);

        $stmt3 = $helper->prepare('GET', AbstractTests::$baseUrl . '/cookies/delete', [
            'tmp'  => 1,
        ]);

        $helper->execute([
            $stmt1,
            $stmt2,
            $stmt3
        ], $cookieSession, $httpKernel);


        $cookies = $cookieSession->allValues(AbstractTests::$baseUrl . '');

        $this->assertEquals($cookies['foo'], $i);
        $this->assertEquals($cookies['bar'], $j);
        $this->assertEquals($cookies['also'], $i);

        $this->assertArrayNotHasKey('deleted', $cookies);


        /*
         *  Final cookies check
         */

        $stmt = $helper->prepare('GET', AbstractTests::$baseUrl . '/cookies', [
            'tmp'  => 1,
        ]);

        $helper->execute([
            $stmt
        ], $cookieSession, $httpKernel);

        $res = $stmt->getResult();
        $cookies = $res['cookies'];

        $this->assertEquals($cookies['foo'], $i);
        $this->assertEquals($cookies['bar'], $j);
        $this->assertEquals($cookies['also'], $i);

    }


}