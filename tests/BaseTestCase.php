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

use Elastica\Request;
use Http\Discovery\Psr17Factory;
use JoliCode\Elastically\Client;
use JoliCode\Elastically\Factory;
use PHPUnit\Framework\TestCase;

abstract class BaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        $this->getClient()->sendRequest((new Psr17Factory())->createRequest(Request::DELETE, '*'));
    }

    protected function getFactory(?string $path = null, array $config = []): Factory
    {
        return new Factory($config + [
            Factory::CONFIG_MAPPINGS_DIRECTORY => $path ?? __DIR__ . '/configs',
            'log' => false,
            'hosts' => ['http://127.0.0.1:9999'],
        ]);
    }

    protected function getClient(?string $path = null, array $config = []): Client
    {
        return $this->getFactory($path, $config)->buildClient();
    }
}
