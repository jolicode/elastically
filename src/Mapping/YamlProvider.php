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

final class YamlProvider implements MappingProviderInterface
{
    private string $configurationDirectory;
    private Parser $parser;

    public function __construct(string $configurationDirectory, ?Parser $parser = null)
    {
        $this->configurationDirectory = $configurationDirectory;
        $this->parser = $parser ?? new Parser();
    }

    /**
     * @throws ParseException
     */
    public function provideMapping(string $indexName, array $context = []): ?array
    {
        $fileName = $context['filename'] ?? ($indexName . '_mapping.yaml');
        $mappingFilePath = $this->configurationDirectory . \DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($mappingFilePath)) {
            throw new InvalidException(\sprintf('Mapping file "%s" not found. Please check your configuration.', $mappingFilePath));
        }

        $mapping = $this->parser->parseFile($mappingFilePath);

        $analyzerFilePath = $this->configurationDirectory . '/analyzers.yaml';
        if ($mapping && is_file($analyzerFilePath)) {
            $analyzer = $this->parser->parseFile($analyzerFilePath);
            $mapping['settings']['analysis'] = array_merge_recursive($mapping['settings']['analysis'] ?? [], $analyzer);
        }

        return $mapping;
    }
}
