<?php
/**
 * Testing promises
 * User: evaisse
 * Date: 04/06/15
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;


use React\Promise\Deferred;

class PromisesTest extends AbstractTests
{
    /**
     * @return array
     */
    public function provideErrors()
    {
        return [
            [200, ['success', 'done']],
            [404, ['error', 'done']],
            [500, ['error', 'done']],
        ];
    }

    /**
     * @dataProvider provideErrors
     */
    public function testPromises($code, $expectedResults)
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $stmt = $helper->prepare("GET", 'https://httpbin.org/status/:code', array(
            'code' => $code
        ));

        $events = new \ArrayObject();

        $stmt->getPromise()->then(function () use ($events) {
            $events[] = 'success';
        })->otherwise(function () use ($events) {
            $events[] = 'error';
        })->done(function () use ($events) {
            $events[] = 'done';
        });

        $httpKernel->execute([
            $stmt
        ]);

        $this->assertCount(2, $events);

        foreach ($expectedResults as $expectedResult) {
            $this->assertContains($expectedResult, $events);
        }

    }


    /**
     * @dataProvider provideErrors
     */
    public function testProxiedPromises($code, $expectedResults)
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $stmt = $helper->prepare("GET", 'https://httpbin.org/status/:code', array(
            'code' => $code
        ));

        $events = new \ArrayObject();


        $stmt->onSuccess(function () use ($events) {
            $events[] = 'success';
        })->onError(function () use ($events) {
            $events[] = 'error';
        })->onFinish(function () use ($events) {
            $events[] = 'done';
        });


        $httpKernel->execute([
            $stmt
        ]);

        $this->assertCount(2, $events);

        foreach ($expectedResults as $expectedResult) {
            $this->assertContains($expectedResult, $events);
        }

    }

    /**
     *
     */
    public function testPromisesOnTimeout()
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $stmt = $helper->prepare("GET", 'https://httpbin.org/delay/1');

        $stmt->setTimeout(400);

        $events = new \ArrayObject();

        $stmt->onSuccess(function () use ($events) {
            $events[] = 'success';
        })->onError(function () use ($events) {
            $events[] = 'error';
        })->onFinish(function () use ($events) {
            $events[] = 'done';
        });

        $httpKernel->execute([
            $stmt
        ]);

        $this->assertCount(2, $events);
        $this->assertContains("error", $events);
        $this->assertContains("done", $events);


    }

}