<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Tests;

use JoliCode\Elastically\Factory;
use JoliCode\Elastically\IndexBuilder;
use PHPUnit\Framework\TestCase;

class FactoryTest extends TestCase
{
    public function testFactoryWithBadConfig()
    {
        // no config?!
        $factory = new Factory();

        $this->assertInstanceOf(IndexBuilder::class, $factory->buildIndexBuilder());

        $this->expectExceptionMessage(
            'Mapping file "/beers_mapping.yaml" not found. Have you correctly set the \JoliCode\Elastically\Factory::CONFIG_MAPPINGS_DIRECTORY option?'
        );

        $factory->buildIndexBuilder()->createIndex('beers');
    }
}
