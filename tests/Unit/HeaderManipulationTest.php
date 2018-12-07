<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 15/06/15
 * Time: 14:43
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;


class HeaderManipulationTest extends AbstractTests
{


    public function testHeaderManipulation()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $stmt = $helper->prepare('GET', 'http://127.0.0.1:8008/verify');

        $stmt->setHeader('Authorization', "Bearer 123");

        $this->assertEquals("Bearer 123", $stmt->getRequest()->headers->get('Authorization'));
        $this->assertEquals("Bearer 123", $stmt->getRequest()->headers->get('authorization'));

        $stmt->setHeader('Authorization', "Bearer 456", false);

        $this->assertEquals("Bearer 123", $stmt->getRequest()->headers->get('Authorization'));
        $this->assertEquals("Bearer 123", $stmt->getRequest()->headers->get('authorization'));

        $stmt
            ->setHeader('Authorization', "Bearer 456", true)
            ->setHeader('Authorization', "Bearer 456", true)
            ->setHeader('Authorization', "Bearer 456", true);

        $this->assertEquals("Bearer 456", $stmt->getRequest()->headers->get('Authorization'));
        $this->assertEquals("Bearer 456", $stmt->getRequest()->headers->get('authorization'));
    }

}