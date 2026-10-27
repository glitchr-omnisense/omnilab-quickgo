# omnilab/quickgo

The Gene Ontology through QuickGO (EMBL-EBI: `https://www.ebi.ac.uk/QuickGO/services/`) for
[omnisense/omnilab](https://github.com/glitchr-omnisense/omnilab): GO terms by id - name,
definition, aspect, synonyms, direct parents by relation, children -, and a UniProt protein's GO
terms by aspect with their evidence codes, as entities; a search of terms; outside the contract,
the annotations themselves. No key.

## Installation

```sh
composer require omnilab/quickgo
```

```php
use Omnilab\QuickGo\QuickGoSourceFactory;

$quickgo = (new QuickGoSourceFactory($httpClient))->create([
    'mailto' => 'support@glitchr.io',  // optional
    'annotation_pages' => 5,           // optional: pages of 200 annotations a protein's reading takes at most
]);
```

## Credentials

None: QuickGO's REST services take no key and no account.

## Options

The factory's options, with the defaults it applies (`QuickGoSourceFactory`):

| Option | Type | Default | Required | Effect |
|---|---|---|---|---|
| `annotation_pages` | `int` | `5` | no | how many pages of 200 annotations a protein's reading (`entity()`, `annotations()`) takes at most: 1,000 annotations by default |
| `mailto` | `?string` | null | no | a contact address, sent in the User-Agent |
| `user_agent` | `?string` | null | no | the whole User-Agent, in place of the one made from `mailto` |
| `throttle` | `float` | `0` | no | seconds left between two calls: QuickGO publishes no rate |
| `base_uri` | `string` | `https://www.ebi.ac.uk/QuickGO/services/` | no | the API the source calls |

## Capabilities

`capabilities()` answers `entity`, `search`; an operation it does not answer throws `NotSupportedException` before any call.

| Capability | Supported | |
|---|---|---|
| `entity` | yes | a GO term (`GO:0005829`, `GO_0005829`, its page): the term and its paths to its aspect's root, two calls; a UniProt accession: the protein's GO terms by aspect with their evidence, a page of 200 annotations a call; a secondary id answers the term it was merged into; another base refused before any call |
| `search` | yes | GO terms whose name or synonyms hold the words, in QuickGO's order, 100 a page at most, with a `total`; a domain other than biology answers an empty page |
| `series` | no |  |
| `products` | no |  |
| `occurrences` | no |  |
| `datasets` | no |  |
| `jobs` | no |  |
| `open` | no |  |
| `structure` | no |  |
| `cross_references` | no | none of the same thing: a term's parents and children, a protein's terms, are `related` |
| `forecast` | no |  |
| `historical` | no |  |
| `area` | no |  |
| `station` | no |  |
| `cloud_cover` | no |  |

Outside the contract, on `Omnilab\QuickGo\QuickGoSource`: `annotations(Identifier|string $product,
?string $aspect = null): list<Annotation>` - a UniProt protein's GO annotations, one aspect
(`molecular_function`, `biological_process`, `cellular_component`) or all
([below](#outside-the-contract-annotations)).

## The scheme

A GO term is named in the core's `go` scheme by its seven digits (`go:0005829`; `display()` gives
`GO:0005829`), read from `GO:0005829`, OBO's short form `GO_0005829` (Open Targets', STRING's
enrichment) and its QuickGO page (`https://www.ebi.ac.uk/QuickGO/term/GO:0005829`). A protein is
named by its UniProt accession (`P68871`, `uniprot:P68871`).

## Which API

`https://www.ebi.ac.uk/QuickGO/services/` (its OpenAPI at `api-docs`):

| Method | Calls |
|---|---|
| `entity()`, a term | `ontology/go/terms/GO:0005829` (the term, its children with it), then `ontology/go/terms/GO:0005829/paths/GO:0005575` - the paths to its aspect's root, whose first steps are its direct parents and how it is tied to each (no second call for a root) |
| `entity()`, a protein; `annotations()` | `annotation/search?geneProductId=UniProtKB:P68871&limit=200&page=1&includeFields=goName,name` (`aspect=` for one aspect), page after page up to `annotation_pages` |
| `search()` | `ontology/go/search?query=...&limit=...&page=...` |

A secondary id answers the term it was merged into (`GO:0000004` is `GO:0008150`, biological
process): the entity's id is that term's, the id asked kept among its identifiers. An unknown term
answers no hit, a protein without annotation none: `null`. An identifier of a base QuickGO does not
read (ChEBI, InterPro, Mondo...) is refused before any call.

The term's full record (`/complete`: its history, cross-ontology relations such as ChEBI's, its
subsets) is not asked: 198 kB for the cytosol, its history most of it.

## What an entity holds

A term: `id`, `label` (its name), `kind` and `aspect` (`molecular function`, `biological process`,
`cellular component`), `description` (its definition), `synonyms`, `url` (its QuickGO page), and
`obsolete`, `comment`, `usage`, `synonym` (one each, labelled with its type: `Synonym (narrow)`),
`parent` (one each: `part_of GO:0005737`). `related`: `parent` (every direct parent), one relation
per tie (`is_a`, `part_of`, `regulates`, `positively_regulates`, `occurs_in`...), `child` (the
terms below it, each once).

A protein: `id` (`uniprot:P68871`), `label` (UniProt's name, as QuickGO gives it), `kind`
`protein`, `url` (its annotations in QuickGO), and `gene` (`HBB`); one property per term and aspect,
its evidence codes after it in the order met - `molecular_function`, `biological_process`,
`cellular_component`: `oxygen transport (GO:0015671; IBA, IEA, IDA, NAS, TAS, IMP)` -;
`negative_annotation` for each NOT (`NOT|enables ...`), kept out of `related`. `related`:
`function`, `process`, `component` (the terms), `organism`.

## Outside the contract: annotations()

An annotation is a statement about two things with its evidence: `annotations()` gives
`Annotation` objects - `term`, `name`, `aspect`, `qualifier` (`enables`, `involved_in`,
`located_in`, `part_of`, `contributes_to`...; `NOT|` before it, `isNegative()`), `evidence` (GO's
code: `IDA`), `eco` (the ECO term: `ECO:0000314`), `reference` (`PMID:19740759`,
`GO_REF:0000107`), `assignedBy` (`BHF-UCL`, `UniProt`...), `date`.

```php
foreach ($quickgo->annotations('P68871', 'cellular_component') as $a) {
    echo $a->qualifier, ' ', $a->name, ' ', $a->evidence, ' ', $a->reference, "\n";
}
```

## The Collector: hemoglobin beta from UniProt

```php
$collector->collect('uniprot', 'P68871', 1, ['quickgo']);
```

Against the real services on 2026-10-15: two calls (UniProt's entry, QuickGO's annotations) in
5.1 s, `sources` = uniprot, quickgo; the 8 functions, 11 processes and 9 components related.

## What the API does not give here

The terms' history and cross-ontology relations (`/complete`), the slims (`/slim`), the other
ontologies QuickGO serves (ECO: the evidence codes are kept as codes), annotations by term
(every product annotated to a term: `annotation/search?goId=`), the annotation extensions, the
download formats (GAF, GPAD), and gene products other than UniProt's (RNAcentral, Complex Portal).

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnilab\QuickGo\QuickGoSourceFactory;
use Omnilab\Source\Query;
use Symfony\Component\HttpClient\HttpClient;

$quickgo = (new QuickGoSourceFactory(HttpClient::create()))->create(['mailto' => 'you@example.org']);

$cytosol = $quickgo->entity('GO:0005829');
echo $cytosol->id->display(), ' ', $cytosol->label, ' (', $cytosol->kind, '): parents ', implode(', ', array_map(static fn ($p) => (string) $p, $cytosol->properties('parent'))), '; ', count($cytosol->related('child')), " children\n";

$hbb = $quickgo->entity('uniprot:P68871');
printf("%s %s: %d functions, %d processes, %d components\n", $hbb->value('gene'), $hbb->label, count($hbb->related('function')), count($hbb->related('process')), count($hbb->related('component')));
foreach (array_slice($quickgo->annotations('P68871', 'molecular_function'), 0, 2) as $a) {
    echo '  ', $a->qualifier, ' ', $a->name, ' (', $a->term, ') ', $a->evidence, ' ', $a->reference, ' by ', $a->assignedBy, "\n";
}

$page = $quickgo->search(new Query('apoptosis', limit: 2));
printf("%d terms match \"apoptosis\"; the first: %s %s\n", $page->total, $page->first()->id->display(), $page->first()->label);
```

```
GO:0005829 cytosol (cellular component): parents part_of GO:0005737, is_a GO:0110165; 58 children
HBB Hemoglobin subunit beta: 8 functions, 11 processes, 9 components
  contributes_to peroxidase activity (go:0004601) IDA PMID:19740759 by BHF-UCL
  contributes_to haptoglobin binding (go:0031720) IDA PMID:19740759 by BHF-UCL
352 terms match "apoptosis"; the first: GO:0097194 execution phase of apoptosis
```

(as answered on 2026-10-27: five calls)

```yaml
# config/packages/omnilab.yaml
omnilab:
    sources:
        quickgo: { factory: quickgo, options: { mailto: '%env(OMNILAB_MAILTO)%', annotation_pages: 5 } }
```

`annotations()` is a method of `QuickGoSource`, not of the interface the bundle injects: check the
instance before calling it.

## Errors

Every call goes through `HttpSource`: a 404 or a 410 (and a 204) is null - an empty page for a list -; a 429 is a `RateLimitedException` (`retryAfter` from `Retry-After`); a 5xx, a time-out or a host that does not answer an `UnavailableException`; any other 4xx a `ProviderException` with its `status` and what the service said; an answer that is not JSON a `ProviderException`, a web page answered for JSON an `UnavailableException`.

Before any call: an identifier of a base QuickGO does not read (ChEBI, InterPro, Mondo...)
(`NotSupportedException`), and an aspect `annotations()` does not know (`\InvalidArgumentException`).
An unknown term answers no hit, a protein without annotation none: null.

## Verified, and not

Against the real service on 2026-10-15: cytosol (two direct parents, part of the cytoplasm and a
cellular anatomical structure; 58 children), oxygen binding by its OBO short form (a small molecule
binding), GO:0000004 (merged into biological process, a root: no paths asked), an unknown term (no
hit), hemoglobin beta's 93 annotations (28 terms: 8 functions, 11 processes, 9 components), an
accession with none, the search for "apoptosis" (352 terms, two pages), the Collector's walk above.
The fixtures in `Tests/Fixtures/` are its answers. Each call took 1 to 3.7 s.

Again on 2026-10-27: cytosol (its two parents, 58 children), hemoglobin beta's 8 functions, 11
processes and 9 components, its function annotations, the search for "apoptosis" (352 terms; the
example above).

Not verified: a protein with more than 200 annotations (the paging is the API's documented one,
not met here); a negative annotation (hemoglobin beta has none); a rate limit (none published, none
met).

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by the EMBL-EBI or the Gene Ontology Consortium. The Gene Ontology and its annotations
are under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/), QuickGO under the EMBL-EBI's
[terms of use](https://www.ebi.ac.uk/about/terms-of-use), both to be cited as the README's
[Terms of use](https://github.com/glitchr-omnisense/omnilab-quickgo#terms-of-use) says. Whoever
runs the package is responsible for complying with them and for any use made of what it returns:
see the README's [Responsibility](https://github.com/glitchr-omnisense/omnilab-quickgo#responsibility)
section.
