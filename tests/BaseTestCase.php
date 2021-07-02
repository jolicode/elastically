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

use JoliCode\Elastically\Client;
use PHPUnit\Framework\TestCase;

abstract class BaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        (new Client())->request('*', 'DELETE');
    }

    protected function getClient($path = null): Client
    {
        return new Client([
            Client::CONFIG_MAPPINGS_DIRECTORY => $path ?? __DIR__ . '/configs',
            'log' => false,
        ]);
    }
}
