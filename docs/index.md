# omnilab/quickgo

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

## Verified, and not

Against the real service on 2026-10-15: cytosol (two direct parents, part of the cytoplasm and a
cellular anatomical structure; 58 children), oxygen binding by its OBO short form (a small molecule
binding), GO:0000004 (merged into biological process, a root: no paths asked), an unknown term (no
hit), hemoglobin beta's 93 annotations (28 terms: 8 functions, 11 processes, 9 components), an
accession with none, the search for "apoptosis" (352 terms, two pages), the Collector's walk above.
The fixtures in `Tests/Fixtures/` are its answers. Each call took 1 to 3.7 s.

Not verified: a protein with more than 200 annotations (the paging is the API's documented one,
not met here); a negative annotation (hemoglobin beta has none); a rate limit (none published, none
met).
