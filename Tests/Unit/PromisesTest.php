<?php
/**
 * Testing promises
 * User: evaisse
 * Date: 04/06/15
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;


use evaisse\SimpleHttpBundle\Http\Statement;
use React\Promise\Deferred;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

        $stmt = $helper->prepare("GET", 'https://httpbin.org/status/{code}', array(
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

        $stmt = $helper->prepare("GET", 'https://httpbin.org/status/{code}', array(
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



    /**
     * @dataProvider provideErrors
     */
    public function testPromisesValues($code, $events)
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $stmt = $helper->prepare("GET", 'https://httpbin.org/status/{code}', [
            'code' => $code,
        ]);

        $events = new \ArrayObject();

        $stmt->onSuccess(function ($data) use ($events) {
            $events["success"] = $data;
        })->onError(function (HttpException $e) use ($events) {
            $events["error"] = $e;
        })->onFinish(function () use ($events) {
            $events["finish"] = true;
        });

        $httpKernel->execute([
            $stmt
        ]);

        $this->assertCount(2, $events);

        if (isset($events['success'])) {
            $this->assertTrue(array_key_exists("success", $events));
        } else {
            $this->assertInstanceOf("\\Symfony\\Component\\HttpKernel\\Exception\\HttpException", $events["error"]);
        }

        $this->assertTrue($events["finish"]);
    }



    /**
     * @dataProvider provideErrors
     */
    public function testPromisesAfterwards($code, $events)
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $stmt = $helper->prepare("GET", 'https://httpbin.org/status/{code}', [
            'code' => $code,
        ]);


        $httpKernel->execute([
            $stmt
        ]);

        $events = new \ArrayObject();

        $stmt->onSuccess(function ($data) use ($events) {
            $events["success"] = $data;
        })->onError(function (HttpException $e) use ($events) {
            $events["error"] = $e;
        })->onFinish(function () use ($events) {
            $events["finish"] = true;
        });


        $this->assertCount(2, $events);

        if (isset($events['success'])) {
            $this->assertTrue(array_key_exists("success", $events));
        } else {
            $this->assertInstanceOf("\\Symfony\\Component\\HttpKernel\\Exception\\HttpException", $events["error"]);
        }

        $this->assertTrue($events["finish"]);
    }



    /**
     * @dataProvider provideErrors
     */
    public function testAllPromisesFulfillsMultipleTimesAfterwards($code, $events)
    {
        list($helper, $httpKernel, $container) = $this->createContext();

        $stmt = $helper->prepare("GET", 'https://httpbin.org/status/{code}', [
            'code' => $code,
        ]);

        $httpKernel->execute([
            $stmt
        ]);

        $events = new \ArrayObject();

        $stmt->onSuccess(function ($data) use ($events) {
            $events[] = $data;
        })->onError(function (HttpException $e) use ($events) {
            $events[] = $e;
        })->onFinish(function () use ($events) {
            $events[] = true;
        });

        $stmt->onSuccess(function ($data) use ($events) {
            $events[] = $data;
        })->onError(function (HttpException $e) use ($events) {
            $events[] = $e;
        })->onFinish(function () use ($events) {
            $events[] = true;
        });

        $this->assertCount(4, $events);
    }

}