<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Messenger;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Same idea as https://github.com/symfony/swiftmailer-bundle/blob/master/EventListener/EmailSenderListener.php.
 */
class IndexationRequestSpoolSubscriber implements EventSubscriberInterface, ResetInterface
{
    private $wasExceptionThrown = false;

    private $singleTransport;

    private $bus;

    public function __construct(TransportInterface $singleTransport, MessageBusInterface $bus)
    {
        $this->singleTransport = $singleTransport;
        $this->bus = $bus;
    }

    public function onException()
    {
        $this->wasExceptionThrown = true;
    }

    public function onTerminate()
    {
        if ($this->wasExceptionThrown) {
            return;
        }

        $operations = [];

        foreach ($this->singleTransport->get() as $envelope) {
            $operations[] = $envelope->getMessage();

            $this->singleTransport->ack($envelope);
        }

        if (empty($operations)) {
            return;
        }

        $message = new MultipleIndexationRequest($operations);
        $this->bus->dispatch($message);
    }

    public function onResponse(ResponseEvent $event)
    {
        if (method_exists($event, 'isMainRequest')) {
            if (!$event->isMainRequest()) {
                return;
            }
        } elseif (!$event->isMasterRequest()) {
            return;
        }

        $this->onTerminate();
    }

    public static function getSubscribedEvents()
    {
        $listeners = [
            KernelEvents::EXCEPTION => 'onException',
            KernelEvents::RESPONSE => ['onResponse', -10],
            ConsoleEvents::ERROR => 'onException',
            ConsoleEvents::TERMINATE => 'onTerminate',
        ];

        return $listeners;
    }

    public function reset()
    {
        $this->wasExceptionThrown = false;
    }
}
