# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


## [Unreleased]

### Fixed

### Added

* Add support for Symfony 6.0
* Upgrade to jane 7.0

### Deprecated

## [1.4.0] - 2021-11-10

### Fixed

- Better support for prefixed names

### Added

- Switch to Github Action instead of travis
- Drop support for Symfony < 4.4
- Drop support for PHP < 7.4
- Add PHPStan
- Move all the "factory" logic from the `JoliCode\Elastically\Client` to the
  `JoliCode\Elastically\Factory`
- Inject dependencies where possible
- Introduce `JoliCode\Elastically\Serializer\ContextBuilderInterface` and
  concrete implementation: `JoliCode\Elastically\Serializer\StaticContextBuilder`
- Extract code to manage index name from `JoliCode\Elastically\Client` to
  `JoliCode\Elastically\IndexNameMapper`
- Introduce `JoliCode\Elastically\Mapping\MappingProviderInterface` and concrete
  implementations: `JoliCode\Elastically\Mapping\YamlProvider` and
  `JoliCode\Elastically\Mapping\PhpProvider`

### Deprecated

- Deprecate following methods on `JoliCode\Elastically\Client`:
    - `getPrefixedIndex()`: Use `IndexNameMapper` instead
    - `getIndexNameFromClass()`: Use `IndexNameMapper` instead
    - `getClassFromIndexName()`: Use `IndexNameMapper` instead
    - `getPureIndexName()`: Use `IndexNameMapper` instead
    - `getIndexBuilder()`: Inject the `IndexBuilder` instead where you need it
    - `getIndexer()`: Inject the `Indexer` instead where you need it
    - `getBuilder()`: Inject the `ResultSetBuilder` instead where you need it
    - `getSerializer()`: Inject the `Serializer` instead where you need it
    - `getDenormalizer()`: Inject the `Denormalizer` instead where you need it
    - `getSerializerContext()`: Inject the `SerializerContext` instead where you need it;

## [1.3.0] - 2021-07-02

### Added

- Adds elastica raw result in serializer context while denormalizing results

### Deprecated

- Deprecates `Index::getBuilder()` in favor of `Client::getBuilder()`

## [1.2.0] - 2021-02-16

### Added

- Add support for PHP 8 and Elastica 7.10 which [includes some BC](https://github.com/ruflin/Elastica/releases/tag/7.1.0).

## [1.1.1] - 2021-02-09

### Fixed

- Improve Travis-ci tests robustness.
- Fix `getIndexNameFromClass` when index prefix is configured.

## [1.1.0] - 2020-12-28

### Fixed

- Fix a bug when using prefixed indices and the purge method.

### Added

- New `setBulkRequestParams` on the Indexer allowing all the Bulk query params.
- Ability to specify the filename for Index mapping #50.
- This changelog file.

## [1.0.2] - 2020-07-31

### Fixed

- Fix a missing dependency when using the built-in Serializer.

## [1.0.1] - 2020-07-16

### Fixed

- Fix Symfony HttpClient issue when Elastic throw a 4xx or 5xx response code.

## [1.0.0] - 2020-06-24

### Added

- Add a method to migrate an Index (when the mapping change).

### Changed

- Switch to Elastica 7.0.

### Removed

- Remove the "WIP" status in the documentation.

## [0.1-beta.2] - 2020-06-23

### Added

- Add TravisCI tests.
- Symfony Messenger Handler support.
- Symfony HttpClient compatible transport.
- New CONFIG_INDEX_PREFIX configuration option.
- Support for Symfony 5.

### Changed

- Better documentation.

### Fixed

- Fixed the JanePHP support.
- Lots of fixes.

## [0.1-beta.1] - 2019-06-04

### Added

- Allow to set Serializer Context for input/output.

[Unreleased]: https://github.com/jolicode/elastically/compare/v1.4.0...HEAD
[1.4.0]: https://github.com/jolicode/elastically/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/jolicode/elastically/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/jolicode/elastically/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/jolicode/elastically/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/jolicode/elastically/compare/v1.0.2...v1.1.0
[1.0.2]: https://github.com/jolicode/elastically/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/jolicode/elastically/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/jolicode/elastically/compare/v0.1-beta.2...v1.0.0
[0.1-beta.2]: https://github.com/jolicode/elastically/compare/v0.1-beta.1...v0.1-beta.2
[0.1-beta.1]: https://github.com/jolicode/elastically/releases/tag/v0.1-beta.1
