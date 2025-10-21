<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Tests\Symfony;

use JoliCode\Elastically\Client;
use JoliCode\Elastically\Factory;
use JoliCode\Elastically\IndexNameMapper;
use JoliCode\Elastically\Mapping\MappingProviderInterface;
use Psr\Http\Client\ClientInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\Yaml\Yaml;

class SymfonyTest extends KernelTestCase
{
    public function testBasicConfigurationSameAsFactory()
    {
        $container = self::getContainer();

        /** @var Client $clientFromSymfony */
        $clientFromSymfony = $container->get('elastically.default.client.test');

        $config = Yaml::parse(file_get_contents(__DIR__ . '/../Symfony/config.yaml'));
        $factory = new Factory(array_merge(
            $config['elastically']['connections']['default'],
            $config['elastically']['connections']['default']['client'],
        ));

        /** @var Client $clientFromFactory */
        $clientFromFactory = $factory->buildClient();

        $configFromFactory = $clientFromFactory->getConfig('');
        $configFromSymfony = $clientFromSymfony->getConfig('');

        $this->assertSame($configFromSymfony['hosts'], $configFromFactory['hosts']);
    }

    public function testMultipleClientOnSymfony()
    {
        $container = self::getContainer();

        /** @var Client $clientFromSymfony */
        $clientFromSymfony = $container->get('elastically.default.client.test');
        /** @var Client $specialClientFromSymfony */
        $specialClientFromSymfony = $container->get('elastically.special.client.test');

        $httpClient = $container->get(ClientInterface::class);
        $this->assertInstanceOf(Psr18Client::class, $httpClient);

        // Supposed to have the Symfony client on the special one.
        $this->assertSame($specialClientFromSymfony->getTransport()->getClient(), $httpClient);
        $this->assertNotSame($clientFromSymfony->getTransport()->getClient(), $httpClient);
    }

    public function testGettingTheMapping()
    {
        $container = self::getContainer();

        /** @var IndexNameMapper $indexNameMapper */
        $indexNameMapper = $container->get('elastically.default.index_name_mapper.test');
        $this->assertSame(['hop'], $indexNameMapper->getMappedIndices());

        /** @var MappingProviderInterface $mappingProvider */
        $mappingProvider = $container->get('elastically.default.mapping.provider.test');
        $this->assertArrayHasKey('mappings', $mappingProvider->provideMapping('beers'));
    }
}
