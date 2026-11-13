# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable release: it installs with Composer's default minimum-stability (stable).

### Added

- Omnilab QuickGO: a Gene Ontology term (GO:0005829) - its name, definition, aspect, synonyms,
  comment, obsolescence, direct parents by relation and children - a UniProt protein with the GO
  terms it is annotated to by aspect and evidence, its annotations with their evidence, reference
  and curator, and a search of terms, through the EBI's QuickGO services, no key needed.

### Release

- Requires `omnisense/omnilab` `^1.0`, stable versions, where it required `@dev` ones:
  `composer require omnilab/quickgo` installs in a project at Composer's default minimum-stability
  (stable). `extra.branch-alias` names the branch (`dev-1.x`: `1.x-dev`).
- CI: `.github/workflows/ci.yml` (glitchr-studio/ci's php-package.yml, pinned: the package
  installed alone, PHPUnit, PHPStan against its baseline, a consumer install) and `release.yml`
  (that CI on the tag, then glitchr-studio/ci's php-release.yml); `phpunit.xml.dist` runs the
  package's own tests.
- README: the install line and a first call.
