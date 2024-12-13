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

use JoliCode\Elastically\IndexNameMapper;
use JoliCode\Elastically\ResultSetBuilder;
use JoliCode\Elastically\Serializer\ContextBuilderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ResultSetBuilderTest extends TestCase
{
    public function testBuildModelFromIndexAndData(): void
    {
        $indexNameMapper = $this->getMockBuilder(IndexNameMapper::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $indexNameMapper
            ->expects($this->once())
            ->method('getPureIndexName')
            ->with($this->equalTo('indexName'))
            ->willReturn('indexName')
        ;

        $indexNameMapper
            ->expects($this->once())
            ->method('getClassFromIndexName')
            ->with($this->equalTo('indexName'))
            ->willReturn(TestDTO::class)
        ;

        $contextBuilder = $this->getMockBuilder(ContextBuilderInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $contextBuilder
            ->expects($this->once())
            ->method('buildContext')
            ->with($this->equalTo(TestDTO::class))
            ->willReturn([])
        ;

        $denormalizer = $this->getMockBuilder(DenormalizerInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $denormalizer
            ->expects($this->once())
            ->method('denormalize')
            ->with(['id' => 1234], TestDTO::class, null, [])
            ->willReturn([])
        ;

        $resultSetBuilder = new ResultSetBuilder($indexNameMapper, $contextBuilder, $denormalizer);
        $resultSetBuilder->buildModelFromIndexAndData('indexName', ['id' => 1234], []);
    }
}
