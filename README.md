# Elastically

Opinionated Elastica implementation.

- DTO as first class citizen, you never manipulate Array;
- All indexes are versionned / aliased;
- Mappings are done in YAML;
- 100% compatibility with Elastica;

## TODO

- wrapper pour le Client
- wrapper pour l'Index
- DTO par défaut
- donner une structure
- intégration Symfony optionnelle, directement dans la lib
- des scripts tout prêt à la Curator :
  - réindexer si le mapping a bougé, avec toute la danse des alias