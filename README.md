# omnilab/quickgo

The Gene Ontology for [omnisense/omnilab](https://github.com/glitchr-omnisense/omnilab), through QuickGO,
the EBI's browser of GO terms and annotations: a GO term (`GO:0005829`, cytosol) with its name,
definition, aspect, synonyms, comment, whether it is obsolete, its direct parents by relation
(`is_a`, `part_of`, `regulates`...) and its children; a UniProt protein (`P68871`) with the terms
it is annotated to, by aspect, as properties (with their evidence codes) and related things; the
annotations themselves, with their evidence, reference and curator; a search of terms by text.
No key.

```php
$quickgo = (new QuickGoSourceFactory($http))->create(['mailto' => 'support@glitchr.io']);
$term = $quickgo->entity('GO:0005829');
$term->kind;                                   // cellular component
$term->related('part_of')->keys();             // go:0005737 (cytoplasm)
$term->related('is_a')->keys();                // go:0110165 (cellular anatomical structure)
$protein = $quickgo->entity('uniprot:P68871');
$protein->related('process')->keys();          // go:0006954, go:0015670, go:0015671... (11)
$protein->properties('biological_process');    // oxygen transport (GO:0015671; IBA, IEA, IDA, NAS, TAS, IMP)...
$quickgo->annotations('P68871');               // 93 Annotation objects (outside the contract)
$quickgo->search(new Query('apoptosis', limit: 3));   // 352 terms
```

```yaml
omnilab:
    sources:
        quickgo:
            factory: quickgo
            options:
                mailto: 'support@glitchr.io'   # in the User-Agent
                annotation_pages: 5            # pages of 200 annotations a protein's reading takes at most
```

A term is named in the core's `go` scheme (`go:0005829`, displayed `GO:0005829`). The `Collector`
reaches QuickGO from any source that gives a UniProt accession (`$collector->collect('uniprot',
'P68871')`): the protein's GO terms join it as related things.

## Install and a first call

```sh
composer require omnilab/quickgo
```

A real call, with no account and no key: the GO term cytosol in QuickGO. `mailto` is your address,
sent in the User-Agent so that the service can reach you before it blocks a caller; the source keeps
to the limits its [`policy.json`](policy.json) declares.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnilab\QuickGo\QuickGoSourceFactory;

$quickgo = (new QuickGoSourceFactory())->create(['mailto' => 'you@example.org']);   // your address, in the User-Agent
$term = $quickgo->entity('GO:0005829');
echo $term->id, ' ', $term->label, ' (', $term->kind, ')', "\n";
```

Its output on 2026-11-13:

```
go:0005829 cytosol (cellular component)
```

## Terms of use

The Gene Ontology and its annotations are under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
([geneontology.org/docs/go-citation-policy](https://geneontology.org/docs/go-citation-policy/));
QuickGO is a service of the EMBL-EBI ([terms of use](https://www.ebi.ac.uk/about/terms-of-use)). Cite
The Gene Ontology Consortium, "The Gene Ontology knowledgebase in 2023", Genetics 2023,
[10.1093/genetics/iyad031](https://doi.org/10.1093/genetics/iyad031), and Binns D. et al.,
"QuickGO: a web-based tool for Gene Ontology searching", Bioinformatics 2009,
[10.1093/bioinformatics/btp536](https://doi.org/10.1093/bioinformatics/btp536). No rate is published.

## Usage policy

Declared as data in [`policy.json`](policy.json) (read at https://www.ebi.ac.uk/QuickGO/api/index.html on 2026-11-10), and honoured on every request by the
core: a limit reached waits when its window ends within `max_wait` seconds, else the request is not
sent ([Usage policies](https://github.com/glitchr-omnisense/omnilab/blob/1.x/docs/policies.md)).

- Limits: not published (unknown).
- Licence: CC-BY-4.0; commercial use: allowed.
- Credit: required: Gene Ontology data (release date / DOI), CC BY 4.0; cite Ashburner et al. 2000 and The Gene Ontology Consortium, "The Gene Ontology knowledgebase in 2026", Nucleic Acids Res., doi:10.1093/nar/gkaf1292 (https://geneontology.org/docs/go-citation-policy/).
- Needs: nothing.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by EMBL-EBI (QuickGO) and the Gene Ontology Consortium. It reaches their service on behalf of whoever runs it, and it does not
redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence
covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with the terms above, for
obtaining whatever they require (an account, a key, a licence or subscription, an attribution or
a citation, a rate limit to respect), and for any use made of what it returns. The authors
publish it free of charge, make no commercial use of it, and accept no liability for how it is
used (see [LICENSE](LICENSE)).

License: MIT.
