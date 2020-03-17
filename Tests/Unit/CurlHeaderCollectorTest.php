<?php


namespace evaisse\SimpleHttpBundle\Tests\Unit;


use evaisse\SimpleHttpBundle\Curl\CurlHeaderCollector;
use PHPUnit\Framework\TestCase;

class CurlHeaderCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testMultipleHeaderParsing()
    {
        $headerCollector = new CurlHeaderCollector();

        $headersString = '
content-type: application/json
content-length: 755
cache-control: max-age=0, no-cache, no-store, must-revalidate
content-language: fr
date: Tue, 17 Mar 2020 10:33:29 GMT
expires: Tue, 17 Mar 2020 10:33:29 GMT
server: meinheld/1.0.1
set-cookie: dwf_sg_task_completion=False; Domain=developer.mozilla.org; expires=Thu, 16 Apr 2020 10:33:29 GMT; Max-Age=2592000; Path=/; Secure
strict-transport-security: max-age=63072000
vary: Cookie
xkey: foo1
xkey: foo2
xkey: foo3, foo4
x-content-type-options: nosniff
xkey: foo5,foo6
ykey: foo1
x-xss-protection: 1; mode=block';

        foreach (explode("\n", $headersString) as $headerLine) {
            $headerCollector->collect(null, $headerLine);
        }
        $collectedHeaders = $headerCollector->retrieve();

        // Test for header parsing
        
        $this->assertArrayHasKey('xkey', $collectedHeaders);
        $this->assertArrayHasKey('ykey', $collectedHeaders);
        $this->assertInternalType('array', $collectedHeaders['xkey']);
        $this->assertInternalType('string', $collectedHeaders['ykey']);
        $this->assertArraySubset(['foo1', 'foo2', 'foo3, foo4', 'foo5,foo6'], $collectedHeaders['xkey']);
        $this->assertContains('foo1', $collectedHeaders['ykey']);

        //  Test for cookie header parsing

        $collectedCookies = $headerCollector->getCookies();
        $this->assertCount(1, $collectedCookies);
        $firstCookie = $collectedCookies[0];
        $this->assertEquals('dwf_sg_task_completion', $firstCookie->getName(), 'correct cookie name');
        $this->assertEquals('False', $firstCookie->getValue(), 'correct cookie value');
        $this->assertEquals('developer.mozilla.org', $firstCookie->getDomain(), 'correct cookie domain');
        $this->assertEquals('1587033209', $firstCookie->getExpiresTime(), 'correct cookie expire');
        $this->assertEquals('/', $firstCookie->getPath(), 'correct cookie path');
        // Cannot be tested as we don't provide the url
        // $this->assertEquals(true, $firstCookie->isSecure(), 'correct cookie secure');
        $this->assertEquals(false, $firstCookie->isHttpOnly(), 'correct cookie httpOnly');
    }
}