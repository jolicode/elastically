<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Tests\Symfony;

use Elastica\Query;
use JoliCode\Elastically\Client;
use JoliCode\Elastically\Messenger\IndexationRequest;
use JoliCode\Elastically\Tests\Messenger\TestDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class TestController extends AbstractController
{
    public function withException(MessageBusInterface $bus): void
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

    public function withSearch(Client $defaultClient): Response
    {
        $index = $defaultClient->getIndex('hop');
        $index->exists();
        $index->exists();

        try {
            $index->search(new Query(new Query\MatchAll()));
        } catch (\Throwable) {
            // The index does not exist
        }

        return new Response('Searched.', Response::HTTP_OK);
    }
}
