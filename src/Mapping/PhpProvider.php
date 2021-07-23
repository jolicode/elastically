<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace JoliCode\Elastically\Mapping;

use Elastica\Exception\InvalidException;

final class PhpProvider implements MappingProviderInterface
{
    private string $configurationDirectory;

    public function __construct(string $configurationDirectory)
    {
        $this->configurationDirectory = $configurationDirectory;
    }

    public function provideMapping(string $indexName, array $context = []): ?array
    {
        $fileName = $context['filename'] ?? ($indexName.'_mapping.php');
        $mappingFilePath = $this->configurationDirectory.\DIRECTORY_SEPARATOR.$fileName;
        if (!is_file($mappingFilePath)) {
            throw new InvalidException(sprintf('Mapping file "%s" not found.', $mappingFilePath));
        }

        $mapping = require $mappingFilePath;
        if (1 === $mapping) {
            // File seems to be empty
            return null;
        }

        $analyzerFilePath = $this->configurationDirectory.\DIRECTORY_SEPARATOR.'analyzers.php';
        if ($mapping && is_file($analyzerFilePath)) {
            $analyzer = require $analyzerFilePath;
            $mapping['settings']['analysis'] = array_merge_recursive($mapping['settings']['analysis'] ?? [], $analyzer);
        }

        return $mapping;
    }
}
