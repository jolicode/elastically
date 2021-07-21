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
use JoliCode\Elastically\IndexNameMapper;

final class IndexNameMapperTest extends BaseTestCase
{
    public function testClientIndexNameFromClass(): void
    {
        $this->expectException(RuntimeException::class);

        $mapper = new IndexNameMapper(null, [
            'todo' => TestDTO::class,
        ]);

        $mapper->getIndexNameFromClass('OLA');
    }

    public function testIndexNameFromClassWithBackslashes(): void
    {
        $this->expectException(RuntimeException::class);

        $mapper = new IndexNameMapper(null, [
            'todo' => '\\' . TestDTO::class,
        ]);

        $mapper->getIndexNameFromClass(TestDTO::class);
    }

    public function testIndexNameFromClassWithConfigIndexPrefix(): void
    {
        $mapper = new IndexNameMapper('foo', [
            'todo' => TestDTO::class,
        ]);

        $indexName = $mapper->getIndexNameFromClass(TestDTO::class);
        $this->assertSame('foo_todo', $indexName);
    }
}
