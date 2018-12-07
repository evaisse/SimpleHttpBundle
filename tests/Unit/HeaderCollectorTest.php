<?php
/**
 * User: evaisse
 * Date: 07/12/2018
 * Time: 10:28
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;

use evaisse\SimpleHttpBundle\Curl\Collector\HeaderCollector;

/**
 * Class HeaderCollectorTest
 * @package evaisse\SimpleHttpBundle\Tests\Unit
 */
class HeaderCollectorTest extends AbstractTests
{


    public function testEmptyHttpHeaderParsing()
    {
        $collector = new HeaderCollector();
        $headers = [];

        foreach ($headers as $h) {
            $collector->collect(null, $h);
        }
        $this->assertEquals(null, $collector->getCode());
        $this->assertEquals(null, $collector->getMessage());
        $this->assertEquals(null, $collector->getVersion());
    }

    public function testHttpHeaderParsing()
    {
        $collector = new HeaderCollector();
        $headers = [
            "HTTP/1.1 200 Ok",
            "HTTP/1.1 200 Ok",
            "X-Debug: foo/http/",
            "User-Agent: blablablab",
        ];
        foreach ($headers as $h) {
            $collector->collect(null, $h);
        }

        $this->assertEquals(200, $collector->getCode());
        $this->assertEquals("Ok", $collector->getMessage());
        $this->assertEquals("1.1", $collector->getVersion());
    }

    /**
     * test em
     */
    public function testHttpEmptyStatusHeaderParsing()
    {
        $collector = new HeaderCollector();
        $headers = [
            "HTTP/1.1 100 Continue",
            "",
            "HTTP/1.1 200 Ok",
            "X-Debug: foo/http/",
            "User-Agent: blablablab",
        ];

        foreach ($headers as $h) {
            $collector->collect(null, $h);
        }

        $this->assertEquals(200, $collector->getCode());
        $this->assertEquals("Ok", $collector->getMessage());
        $this->assertEquals("1.1", $collector->getVersion());

    }

    public function testbadCookieHeaderParsing()
    {
        $collector = new HeaderCollector();
        $headers = [
            "HTTP/1.1 100 Continue",
            "",
            "HTTP/1.1 200 Ok",
            "",
            "X-Debug: foo/http/",
            "HTTP/1.1 302 Found",
            "X-Debug: foo/http/",
            "User-Agent: blablablab",
            "Set-Cookie: blabla",
        ];

        foreach ($headers as $h) {
            $collector->collect(null, $h);
        }

        $this->assertEquals(302, $collector->getCode());
        $this->assertEquals("Found", $collector->getMessage());
        $this->assertEquals("1.1", $collector->getVersion());
        $this->assertEquals([
            "X-Debug" => "foo/http/",
            "User-Agent" => "blablablab",
        ], $collector->getHeaders());
        $this->assertCount(0, $collector->getCookies());
    }


    public function testCookieHeaderParsing()
    {

        $collector = new HeaderCollector();
        $headers = [
            "HTTP/1.1 100 Continue",
            "",
            "HTTP/1.1 200 Ok",
            "X-Debug: foo/http/",
            "X-Debug: foo/http/",
            "HTTP/1.1 302 Found",
            "X-Debug: foo/http/",
            "User-Agent: blablablab",
            "Set-Cookie: blabla=1",
        ];

        foreach ($headers as $h) {
            $collector->collect(null, $h);
        }

        $this->assertEquals(302, $collector->getCode());
        $this->assertEquals("Found", $collector->getMessage());
        $this->assertEquals("1.1", $collector->getVersion());
        $this->assertEquals([
            "X-Debug" => "foo/http/",
            "User-Agent" => "blablablab",
        ], $collector->getHeaders());
        $this->assertCount(1, $collector->getCookies());
    }

}