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
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

final readonly class YamlProvider implements MappingProviderInterface
{
    private Parser $parser;

    public function __construct(
        private string $configurationDirectory,
        ?Parser $parser = null,
    ) {
        $this->parser = $parser ?? new Parser();
    }

    /**
     * @throws ParseException
     */
    public function provideMapping(string $indexName, array $context = []): ?array
    {
        $fileName = $context['filename'] ?? ($indexName . '_mapping.yaml');
        $configurationDirectory = $this->resolveConfigurationDirectory($fileName);
        $mappingFilePath = $configurationDirectory . \DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($mappingFilePath)) {
            throw new InvalidException(\sprintf('Mapping file "%s" not found. Please check your configuration.', $mappingFilePath));
        }

        $mapping = $this->parser->parseFile($mappingFilePath);

        $analyzerFilePath = $configurationDirectory . '/analyzers.yaml';
        if ($mapping && is_file($analyzerFilePath)) {
            $analyzer = $this->parser->parseFile($analyzerFilePath);
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
