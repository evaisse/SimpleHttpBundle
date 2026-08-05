<?php

namespace evaisse\SimpleHttpBundle\Tests\Unit;

use evaisse\SimpleHttpBundle\Http\Statement;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use ReflectionProperty;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StatementPromiseHandlersTest extends TestCase
{
    private function createLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var string[] */
            public array $warnings = [];

            public function log($level, $message, array $context = []): void
            {
                if ($level === 'warning') {
                    $this->warnings[] = (string) $message;
                }
            }
        };
    }

    public function testProxiedHandlersConsumeRejectedChildPromises(): void
    {
        $logger = $this->createLogger();

        $statement = new Statement(
            Request::create('/status/404', 'GET'),
            new EventDispatcher(),
        );
        $statement->setLogger($logger);

        $events = [];

        $statement
            ->onSuccess(static function () use (&$events): void {
                $events[] = 'success';
            })
            ->onError(static function () use (&$events): void {
                $events[] = 'error';
            })
            ->onFinish(static function () use (&$events): void {
                $events[] = 'done';
            });

        $statement->setError(new NotFoundHttpException('Not found'));

        self::assertSame(['error', 'done'], $events);
        self::assertSame([], $logger->warnings);
    }

    public function testOnProgressIsCompatibleAcrossPromiseVersions(): void
    {
        $logger = $this->createLogger();
        $statement = new Statement(
            Request::create('/status/200', 'GET'),
            new EventDispatcher(),
        );
        $statement->setLogger($logger);

        $progressEvents = [];

        self::assertSame(
            $statement,
            $statement->onProgress(static function ($payload) use (&$progressEvents): void {
                $progressEvents[] = $payload;
            })
        );

        $deferredProperty = new ReflectionProperty(Statement::class, 'deferred');
        $deferred = $deferredProperty->getValue($statement);

        if (method_exists($statement->getPromise(), 'progress') && method_exists($deferred, 'notify')) {
            $deferred->notify('tick');

            self::assertSame(['tick'], $progressEvents);
            self::assertSame([], $logger->warnings);

            return;
        }

        self::assertSame([], $progressEvents);
        self::assertCount(1, $logger->warnings);
        self::assertStringContainsString('onProgress() is ignored', $logger->warnings[0]);
    }
}
