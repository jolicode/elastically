<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Mapping;

use Elastica\Exception\InvalidException;

final readonly class PhpProvider implements MappingProviderInterface
{
    public function __construct(
        private string $configurationDirectory,
    ) {
    }

    public function provideMapping(string $indexName, array $context = []): ?array
    {
        $fileName = $context['filename'] ?? ($indexName . '_mapping.php');
        $configurationDirectory = $this->resolveConfigurationDirectory($fileName);
        $mappingFilePath = $configurationDirectory . \DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($mappingFilePath)) {
            throw new InvalidException(\sprintf('Mapping file "%s" not found.', $mappingFilePath));
        }

        $mapping = require $mappingFilePath;
        if (1 === $mapping) {
            // File seems to be empty
            return null;
        }

        $analyzerFilePath = $configurationDirectory . \DIRECTORY_SEPARATOR . 'analyzers.php';
        if ($mapping && is_file($analyzerFilePath)) {
            $analyzer = require $analyzerFilePath;
            $mapping['settings']['analysis'] = array_merge_recursive($mapping['settings']['analysis'] ?? [], $analyzer);
        }

        return $mapping;
    }

    /**
     * The configuration directory may be a glob pattern (with "*" wildcards):
     * in that case, the unique matching directory containing the file is used.
     */
    private function resolveConfigurationDirectory(string $fileName): string
    {
        if (is_dir($this->configurationDirectory) || !preg_match('/[*?\[]/', $this->configurationDirectory)) {
            return $this->configurationDirectory;
        }

        $directories = array_values(array_filter(
            glob($this->configurationDirectory) ?: [],
            static fn (string $directory): bool => is_file($directory . \DIRECTORY_SEPARATOR . $fileName),
        ));

        if (\count($directories) > 1) {
            throw new InvalidException(\sprintf('Mapping file "%s" found in several directories matching "%s": "%s".', $fileName, $this->configurationDirectory, implode('", "', $directories)));
        }

        return $directories[0] ?? $this->configurationDirectory;
    }
}
