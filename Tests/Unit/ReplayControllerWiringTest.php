<?php

namespace evaisse\SimpleHttpBundle\Tests\Unit;

use evaisse\SimpleHttpBundle\Controller\ReplayController;
use evaisse\SimpleHttpBundle\DependencyInjection\SimpleHttpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ReplayControllerWiringTest extends TestCase
{
    public function testReplayControllerIsRegisteredAsCallableControllerService(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.secret', 'test-secret');
        $container->setParameter('kernel.root_dir', sys_get_temp_dir());
        $container->setParameter('kernel.environment', 'test');

        $container->register('event_dispatcher', EventDispatcher::class);
        $container->setAlias(EventDispatcherInterface::class, 'event_dispatcher')->setPublic(true);
        $container->register('debug.stopwatch', Stopwatch::class);
        $container->register('twig.loader', \stdClass::class);

        $extension = new SimpleHttpExtension();
        $extension->load([], $container);

        $definition = $container->getDefinition(ReplayController::class);

        $this->assertTrue(
            $definition->isPublic(),
            'ReplayController must stay public so Symfony can fetch it from the container.'
        );
        $this->assertNotEmpty(
            $definition->getTag('controller.service_arguments'),
            'ReplayController must be tagged as controller.service_arguments.'
        );

        $container->compile();

        $this->assertInstanceOf(ReplayController::class, $container->get(ReplayController::class));
    }
}
