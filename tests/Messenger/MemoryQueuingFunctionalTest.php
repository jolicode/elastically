<?php

declare(strict_types=1);

namespace JoliCode\Elastically\Tests\Messenger;

use JoliCode\Elastically\Messenger\IndexationRequest;
use JoliCode\Elastically\Messenger\MultipleIndexationRequest;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

final class MemoryQueuingFunctionalTest extends KernelTestCase
{
    public function testFrameworkQueue(): void
    {
        static::bootKernel(['debug' => false]);

        /* @var InMemoryTransport $transport */
        $transport = self::$container->get('messenger.transport.queuing.test');
        $this->assertCount(0, $transport->getSent());
    }

    public function testFrameworkKernelTerminateResend(): void
    {
        static::bootKernel(['debug' => false]);

        /* @var MessageBus $bus */
        $bus = self::$container->get('messenger.default_bus');

        $bus->dispatch(new IndexationRequest(TestDTO::class, '1234567890'));
        $bus->dispatch(new IndexationRequest(TestDTO::class, '1234567891'));
        $bus->dispatch(new IndexationRequest(TestDTO::class, '1234567892'));

        /* @var InMemoryTransport $transport */
        $transport = self::$container->get('messenger.transport.queuing.test');
        $this->assertCount(3, $transport->getSent());
        $this->assertEmpty($transport->getAcknowledged());
        $this->assertEmpty($transport->getRejected());

        /* @var EventDispatcher $dispatcher */
        $dispatcher = self::$container->get('event_dispatcher');

        // Simulate Kernel Response
        $dispatcher->dispatch(
            new ResponseEvent(static::$kernel, new Request(), Kernel::MASTER_REQUEST, new Response()),
            KernelEvents::RESPONSE
        );

        $this->assertCount(3, $transport->getAcknowledged());
        $this->assertEmpty($transport->getRejected());

        /* @var InMemoryTransport $transportBulk */
        $transportBulk = self::$container->get('messenger.transport.async.test');
        $this->assertCount(1, $transportBulk->getSent());
        $this->assertEmpty($transportBulk->getAcknowledged());
        $this->assertEmpty($transportBulk->getRejected());

        $message = $transportBulk->get();
        $this->assertCount(1, $message);
        $message = reset($message);
        /* @var Envelope $message */
        $this->assertInstanceOf(MultipleIndexationRequest::class, $message->getMessage());
        $this->assertCount(3, $message->getMessage()->getOperations());
    }

    public function testFrameworkKernelTerminateWithNoMessage(): void
    {
        static::bootKernel(['debug' => false]);

        /* @var MessageBus $bus */
        $bus = self::$container->get('messenger.default_bus');

        /* @var InMemoryTransport $transport */
        $transport = self::$container->get('messenger.transport.queuing.test');
        $this->assertCount(0, $transport->getSent());

        /* @var EventDispatcher $dispatcher */
        $dispatcher = self::$container->get('event_dispatcher');

        // Simulate Kernel Response
        $dispatcher->dispatch(
            new ResponseEvent(static::$kernel, new Request(), Kernel::MASTER_REQUEST, new Response()),
            KernelEvents::RESPONSE
        );

        $this->assertCount(0, $transport->getAcknowledged());

        /* @var InMemoryTransport $transportBulk */
        $transportBulk = self::$container->get('messenger.transport.async.test');
        $this->assertCount(0, $transportBulk->getSent());
    }
}
