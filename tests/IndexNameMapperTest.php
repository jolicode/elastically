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

    public function testPureIndexNameFromIndex(): void
    {
        $mapper = new IndexNameMapper(null, [
            'todo' => TestDTO::class,
        ]);

        $pureIndexName = $mapper->getPureIndexName('todo_2222-22-22-000001');
        $this->assertSame('todo', $pureIndexName);
    }

    public function testPureIndexNameFromIndexPrefix(): void
    {
        $mapper = new IndexNameMapper('foo', [
            'todo' => TestDTO::class,
        ]);

        $pureIndexName = $mapper->getPureIndexName('foo_todo_2222-22-22-000001');
        $this->assertSame('todo', $pureIndexName);
    }
    public function testPureIndexNameFromIndexAlias(): void
    {
        $mapper = new IndexNameMapper(null, [
            'todo' => TestDTO::class,
        ]);

        $pureIndexName = $mapper->getPureIndexName('todo');
        $this->assertSame('todo', $pureIndexName);
    }

    public function testPureIndexNameFromIndexAliasPrefix(): void
    {
        $mapper = new IndexNameMapper('foo', [
            'todo' => TestDTO::class,
        ]);

        $pureIndexName = $mapper->getPureIndexName('foo_todo');
        $this->assertSame('todo', $pureIndexName);
    }

    public function testMappedIndices(): void
    {
        $mapper = new IndexNameMapper('foo', [
            'todo' => TestDTO::class,
            'bar' => TestBarDTO::class,
        ]);
        $mappedIndices = $mapper->getMappedIndices();
        $this->assertCount(2, $mappedIndices);
        $this->assertContains('todo', $mappedIndices);
        $this->assertContains('bar', $mappedIndices);
    }
}

class TestBarDTO
{
    public $bar;
    public $baz;
}
