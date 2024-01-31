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

namespace JoliCode\Elastically\Tests\Messenger;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

final class TestControllerFunctionalTest extends WebTestCase
{
    public function testControllerWithException(): void
    {
        $client = self::createClient();
        $client->request('GET', '/with_exception');

        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.queuing.test');
        $this->assertCount(2, $transport->getSent());

        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async.test');
        $this->assertCount(0, $transport->getSent());

        $this->assertSame(500, $client->getResponse()->getStatusCode());
    }

    public function testControllerWithResponse(): void
    {
        $client = self::createClient();
        $client->request('GET', '/with_response');

        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.queuing.test');
        $this->assertCount(2, $transport->getSent());
        $this->assertCount(2, $transport->getAcknowledged());

        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async.test');
        $this->assertCount(1, $transport->getSent());

        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }

    protected static function getContainer(): Container
    {
        if (method_exists(KernelTestCase::class, 'getContainer')) {
            return parent::getContainer();
        }

        return self::$container;
    }
}
