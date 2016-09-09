<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 02/06/15
 * Time: 11:15
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;



use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadsTest extends AbstractTests
{


    public function testPutFiles()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $stmt = $helper->prepare("PUT", AbstractTests::$baseUrl . '/put');

        $stmt->attachFile('file', __FILE__);
        $stmt->attachFile('file2', __DIR__ . '/../Fixtures/greenimg.jpg');

        $stmt->execute($httpKernel);

        $res = $stmt->getResult();

        $this->assertTrue(is_array($res));
        $this->assertArrayHasKey('file', $res['files']);
        $this->assertArrayHasKey('file2', $res['files']);
    }


    public function testPostFiles()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $stmt = $helper->prepare("POST", AbstractTests::$baseUrl . '/post');

        $stmt->attachFile('file', __FILE__);
        $stmt->attachFile('file2', __DIR__ . '/../Fixtures/greenimg.jpg');

        $stmt->execute($httpKernel);

        $res = $stmt->getResult();

        $this->assertTrue(is_array($res));
        $this->assertArrayHasKey('file', $res['files']);
        $this->assertArrayHasKey('file2', $res['files']);
    }

    public function testPostFilesWithOptionnalArgs()
    {
        $arg = 'é"("("(éù%"';

        list($helper, $httpKernel, $container) = $this->createContext();

        $stmt = $helper->prepare("POST", AbstractTests::$baseUrl . '/post', array(
            'exemple' => $arg,
        ));

        $stmt->attachFile('file', __FILE__);
        $stmt->attachFile('file2', __DIR__ . '/../Fixtures/greenimg.jpg');

        $stmt->execute($httpKernel);

        $res = $stmt->getResult();

        $this->assertTrue(is_array($res));
        $this->assertArrayHasKey('file', $res['files']);
        $this->assertArrayHasKey('file2', $res['files']);
        $this->assertEquals($arg, $res['form']['exemple']);
    }



}