<?php

declare(strict_types=1);

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
            Client::CONFIG_MAPPINGS_DIRECTORY => $path ?? __DIR__.'/configs',
            'log' => false,
        ]);
    }
}
