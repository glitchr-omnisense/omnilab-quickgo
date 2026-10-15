# omnilab/quickgo

The Gene Ontology for [glitchr/omnilab](https://github.com/glitchr-studio/omnilab), through QuickGO,
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

## Terms of use

The Gene Ontology and its annotations are under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
([geneontology.org/docs/go-citation-policy](https://geneontology.org/docs/go-citation-policy/));
QuickGO is a service of the EMBL-EBI ([terms of use](https://www.ebi.ac.uk/about/terms-of-use)). Cite
The Gene Ontology Consortium, "The Gene Ontology knowledgebase in 2023", Genetics 2023,
[10.1093/genetics/iyad031](https://doi.org/10.1093/genetics/iyad031), and Binns D. et al.,
"QuickGO: a web-based tool for Gene Ontology searching", Bioinformatics 2009,
[10.1093/bioinformatics/btp536](https://doi.org/10.1093/bioinformatics/btp536). No rate is published.

License: MIT.
