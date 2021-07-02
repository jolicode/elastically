<?php

declare(strict_types=1);

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
