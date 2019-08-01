<?php

namespace JoliCode\Elastically;

use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractMappingReader
{
    protected const BULK_SIZE = 100;

    /**
     * @var array
     */
    protected $options;

    public function __construct(array $options = [])
    {
        $resolver = (new OptionsResolver())

            // Allow extra option from Elastica\Client::$_config
            ->setDefined(array_keys($options));

        $this->configureOptions($resolver);

        $this->options = $resolver->resolve($options);
    }

    public function configureOptions(OptionsResolver $resolver): OptionsResolver
    {
        return $resolver->setDefined(['elastically_mappings_directory'])
            ->setDefined('elastically_index_class_mapping')
            ->setDefined('elastically_index_prefix')
            ->setDefined('elastically_serializer_context_per_class')
            ->setDefined('elastically_serializer')
            ->setDefined('elastically_bulk_size')
            ->setAllowedTypes('elastically_mappings_directory', 'string')
            ->setAllowedTypes('elastically_index_class_mapping', ['string', 'array'])
            ->setAllowedTypes('elastically_index_prefix', 'string')
            ->setAllowedTypes('elastically_serializer', 'string')
            ->setAllowedTypes('elastically_bulk_size', 'int')

            ->setDefault('elastically_bulk_size', self::BULK_SIZE);
    }

    /**
     * @return mixed
     */
    public function getOption(string $option)
    {
        return $this->options[$option] ?? null;
    }
}
