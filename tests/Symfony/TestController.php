<?php

declare(strict_types=1);

namespace JoliCode\Elastically\Tests\Symfony;

use JoliCode\Elastically\Messenger\IndexationRequest;
use JoliCode\Elastically\Tests\Messenger\TestDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class TestController extends AbstractController
{
    public function withException(MessageBusInterface $bus)
    {
        $bus->dispatch(new IndexationRequest(TestDTO::class, '1234567890'));
        $bus->dispatch(new IndexationRequest(TestDTO::class, '1234567891'));

        throw new \RuntimeException('My big error.');
    }

    public function withResponse(MessageBusInterface $bus)
    {
        $bus->dispatch(new IndexationRequest(TestDTO::class, '1234567890'));
        $bus->dispatch(new IndexationRequest(TestDTO::class, '1234567891'));

        return new Response('Everything is fine.', Response::HTTP_OK);
    }
}
