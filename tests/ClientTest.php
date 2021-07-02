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

namespace JoliCode\Elastically\Tests;

use Elastica\Exception\RuntimeException;
use JoliCode\Elastically\Client;

final class ClientTest extends BaseTestCase
{
    public function testClientIndexNameFromClass(): void
    {
        $this->expectException(RuntimeException::class);

        $client = new Client([
            Client::CONFIG_INDEX_CLASS_MAPPING => [
                'todo' => TestDTO::class,
            ],
        ]);

        $client->getIndexNameFromClass('OLA');
    }

    public function testIndexNameFromClassWithBackslashes(): void
    {
        $this->expectException(RuntimeException::class);

        $client = new Client([
            Client::CONFIG_INDEX_CLASS_MAPPING => [
                'todo' => '\\' . TestDTO::class,
            ],
        ]);

        $client->getIndexNameFromClass(TestDTO::class);
    }

    public function testIndexNameFromClassWithConfigIndexPrefix(): void
    {
        $client = new Client([
            Client::CONFIG_INDEX_CLASS_MAPPING => [
                'todo' => TestDTO::class,
            ],
            Client::CONFIG_INDEX_PREFIX => 'foo',
        ]);

        $indexName = $client->getIndexNameFromClass(TestDTO::class);
        $this->assertSame('foo_todo', $indexName);
    }
}
