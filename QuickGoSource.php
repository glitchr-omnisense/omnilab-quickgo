<?php

namespace Omnilab\QuickGo;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Model\Domain;
use Omnilab\Model\Entity;
use Omnilab\Model\Identifier;
use Omnilab\Model\Identifiers;
use Omnilab\Model\Property;
use Omnilab\Model\Scheme;
use Omnilab\Source\Capability;
use Omnilab\Source\HttpSource;
use Omnilab\Source\Page;
use Omnilab\Source\Query;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Gene Ontology through QuickGO, the EBI's browser of GO terms and
 * annotations: a GO term ("GO:0005829", cytosol) - its name, definition,
 * aspect, synonyms, comment, whether it is obsolete, and its direct
 * parents by relation (is_a, part_of, regulates...) -; a UniProt protein
 * ("P68871") with the terms it is annotated to, by aspect, as properties
 * (with their evidence codes) and related things; the annotations
 * themselves (annotations(), outside the contract); a search of terms by
 * text.
 *
 * The term's children come with it (related 'child').
 *
 * No key, no account, no published rate: no throttle by default. Two calls
 * a term (the term, its paths to its aspect's root, which give its direct
 * parents), one a protein (more past 200 annotations), one a search page.
 */
final class QuickGoSource extends HttpSource
{
    public const BASE_URI = 'https://www.ebi.ac.uk/QuickGO/services/';

    /** The most terms a search page holds. */
    public const MAX_LIMIT = 100;

    /** The roots of the three aspects. */
    public const ROOTS = ['molecular_function' => 'GO:0003674', 'biological_process' => 'GO:0008150', 'cellular_component' => 'GO:0005575'];

    /** The aspects as a protein's properties and relations name them. */
    private const ASPECTS = [
        'molecular_function' => ['molecular_function', 'Molecular function', 'function'],
        'biological_process' => ['biological_process', 'Biological process', 'process'],
        'cellular_component' => ['cellular_component', 'Cellular component', 'component'],
    ];

    /** @var array<string, array{0: string, 1: ?string, 2: int|string|null}|null> a product's name, symbol and taxon, as its annotations give them */
    private array $names = [];

    /**
     * @param int $pages how many pages of 200 annotations a protein's reading takes at most
     */
    public function __construct(
        HttpClientInterface $http,
        private readonly int $pages = 5,
        string $baseUri = self::BASE_URI,
        array $headers = [],
        float $throttle = 0.0,
    ) {
        parent::__construct($http, $baseUri, $headers, $throttle);
    }

    public function getName(): string
    {
        return 'quickgo';
    }

    public function capabilities(): array
    {
        return [Capability::ENTITY, Capability::SEARCH];
    }

    /**
     * A GO term ("GO:0005829", "GO_0005829", its QuickGO page; a secondary
     * id gives the term it was merged into) or a UniProt protein ("P68871",
     * "uniprot:P68871") with its GO annotations; another base's identifier
     * is refused before any call; null when QuickGO has no such term, or no
     * annotation for that protein.
     */
    public function entity(Identifier|string $id): ?Entity
    {
        $identifier = self::identify($id, Scheme::GO, Scheme::UNIPROT) ?? throw NotSupportedException::identifier('quickgo', (string) $id);
        if (Scheme::UNIPROT === $identifier->scheme) {
            return $this->protein($identifier, $this->annotations($identifier));
        }
        $term = $this->getJson('ontology/go/terms/'.$identifier->display())['results'][0] ?? null;
        if (!\is_array($term) || null === ($termId = Identifier::tryOf(Scheme::GO, $term['id'] ?? null))) {
            return null;
        }
        $root = self::ROOTS[$term['aspect'] ?? ''] ?? null;
        $paths = null === $root || $root === $termId->display() ? [] : ($this->getJson('ontology/go/terms/'.$termId->display().'/paths/'.$root)['results'] ?? []);

        return $this->term($term, $paths, $termId->equals($identifier) ? null : $identifier);
    }

    /**
     * GO terms whose name or synonyms hold the words, in QuickGO's order; a
     * domain other than biology answers nothing. An entity of a page holds
     * the term's name, definition and aspect: entity() adds its parents.
     */
    public function search(Query $query): Page
    {
        if (null !== $query->domain && Domain::BIOLOGY !== $query->domain) {
            return new Page();
        }
        $text = trim((string) preg_replace('~\s+~u', ' ', $query->text));
        if ('' === $text) {
            return new Page();
        }
        $page = max(1, (int) ($query->cursor ?? 1));
        $data = $this->getJson('ontology/go/search', ['query' => $text, 'limit' => max(1, min(self::MAX_LIMIT, $query->limit)), 'page' => $page]) ?? [];
        $items = [];
        foreach ($data['results'] ?? [] as $term) {
            if (\is_array($term) && null !== Identifier::tryOf(Scheme::GO, $term['id'] ?? null)) {
                $items[] = $this->term($term, [], null);
            }
        }
        $pages = (int) ($data['pageInfo']['total'] ?? 0);

        return new Page($items, $page < $pages ? (string) ($page + 1) : null, isset($data['numberOfHits']) ? (int) $data['numberOfHits'] : null);
    }

    /**
     * The GO annotations of a gene product - a UniProt accession ("P68871")
     * -, every aspect or one ("molecular_function", "biological_process",
     * "cellular_component"), negative ones included (Annotation::isNegative()),
     * in QuickGO's order; at most the configured pages of 200. Outside the
     * contract: an annotation is a statement about two things, with its
     * evidence.
     *
     * @return list<Annotation>
     */
    public function annotations(Identifier|string $product, ?string $aspect = null): array
    {
        $identifier = self::identify($product, Scheme::UNIPROT) ?? throw NotSupportedException::identifier('quickgo', (string) $product);
        if (null !== $aspect && !isset(self::ROOTS[$aspect])) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not an aspect: molecular_function, biological_process or cellular_component.', $aspect));
        }
        $annotations = [];
        for ($page = 1; $page <= max(1, $this->pages); ++$page) {
            $data = $this->getJson('annotation/search', ['geneProductId' => 'UniProtKB:'.$identifier->value, 'aspect' => $aspect, 'limit' => 200, 'page' => $page, 'includeFields' => 'goName,name']) ?? [];
            foreach ($data['results'] ?? [] as $row) {
                if (\is_array($row) && null !== ($annotation = Annotation::fromRow($row))) {
                    $annotations[] = $annotation;
                }
                $this->names[$identifier->value] ??= \is_array($row) && \is_string($row['name'] ?? null) ? [$row['name'], $row['symbol'] ?? null, $row['taxonId'] ?? null] : null;
            }
            if ($page >= (int) ($data['pageInfo']['total'] ?? 0)) {
                break;
            }
        }

        return $annotations;
    }

    /**
     * @param array<string, mixed>       $term
     * @param list<list<array<string, string>>> $paths the paths to its aspect's root, each from the term up
     */
    private function term(array $term, array $paths, ?Identifier $asked): Entity
    {
        $id = Identifier::of(Scheme::GO, (string) $term['id']);
        $properties = [];
        $add = static function (string $key, string $label, mixed $value) use (&$properties): void {
            if (null !== $value && '' !== $value) {
                $properties[] = new Property($key, $label, $value, 'quickgo');
            }
        };
        $aspect = \is_string($term['aspect'] ?? null) ? str_replace('_', ' ', $term['aspect']) : null;
        $add('aspect', 'Aspect', $aspect);
        $add('obsolete', 'Obsolete', isset($term['isObsolete']) ? (bool) $term['isObsolete'] : null);
        $add('comment', 'Comment', self::text($term['comment'] ?? null));
        $add('usage', 'Usage', self::text($term['usage'] ?? null));
        $synonyms = [];
        foreach ((array) ($term['synonyms'] ?? []) as $synonym) {
            if (\is_array($synonym) && null !== ($name = self::text($synonym['name'] ?? null))) {
                $synonyms[] = $name;
                $add('synonym', 'Synonym ('.($synonym['type'] ?? 'related').')', $name);
            }
        }
        // The first step of each path: a direct parent and how the term is tied to it.
        $related = [];
        $parents = [];
        foreach ($paths as $path) {
            $step = \is_array($path) ? ($path[0] ?? null) : null;
            $parent = \is_array($step) && ($step['child'] ?? null) === $id->display() ? Identifier::tryOf(Scheme::GO, $step['parent'] ?? null) : null;
            if (null === $parent || isset($parents[$parent->key().' '.$step['relationship']])) {
                continue;
            }
            $parents[$parent->key().' '.$step['relationship']] = true;
            $relation = (string) ($step['relationship'] ?? 'is_a');
            $related['parent'][] = $parent;
            $related[$relation][] = $parent;
            $add('parent', 'Parent', $relation.' '.$parent->display());
        }
        // The term's children come with it.
        foreach ((array) ($term['children'] ?? []) as $child) {
            if (\is_array($child) && null !== ($childId = Identifier::tryOf(Scheme::GO, $child['id'] ?? null))) {
                $related['child'][] = $childId;
            }
        }

        return new Entity(
            id: $id,
            label: self::text($term['name'] ?? null) ?? $id->display(),
            domain: Domain::BIOLOGY,
            kind: $aspect,
            synonyms: array_values(array_unique($synonyms)),
            description: self::text($term['definition']['text'] ?? null),
            properties: $properties,
            identifiers: Identifiers::of($asked),
            url: $id->url(),
            source: 'quickgo',
            related: array_map(static fn (array $ids) => new Identifiers($ids), $related),
        );
    }

    /** @param list<Annotation> $annotations */
    private function protein(Identifier $id, array $annotations): ?Entity
    {
        if (!$annotations) {
            return null;
        }
        [$name, $symbol, $taxon] = $this->names[$id->value] ?? [null, null, null];
        $properties = [];
        $add = static function (string $key, string $label, mixed $value) use (&$properties): void {
            if (null !== $value && '' !== $value) {
                $properties[] = new Property($key, $label, $value, 'quickgo');
            }
        };
        $add('gene', 'Gene', \is_string($symbol) ? $symbol : null);
        // One property per term and aspect, its evidence codes after it, in the order of the aspects then of QuickGO.
        $terms = [];
        $related = [];
        $negative = [];
        foreach ($annotations as $annotation) {
            if ($annotation->isNegative()) {
                $negative[$annotation->term->key()] ??= $annotation->qualifier.' '.($annotation->name ?? $annotation->term->display()).' ('.$annotation->term->display().')';
                continue;
            }
            $terms[$annotation->aspect][$annotation->term->key()]['term'] = $annotation;
            $terms[$annotation->aspect][$annotation->term->key()]['evidence'][$annotation->evidence ?? '?'] = true;
        }
        foreach (self::ASPECTS as $aspect => [$key, $label, $relation]) {
            foreach ($terms[$aspect] ?? [] as ['term' => $annotation, 'evidence' => $evidence]) {
                $add($key, $label, ($annotation->name ?? $annotation->term->display()).' ('.$annotation->term->display().'; '.implode(', ', array_keys($evidence)).')');
                $related[$relation][] = $annotation->term;
            }
        }
        foreach ($negative as $text) {
            $add('negative_annotation', 'Negative annotation', $text);
        }
        $related = array_map(static fn (array $ids) => new Identifiers($ids), $related);
        $related['organism'] = Identifiers::of(Identifier::tryOf(Scheme::NCBI_TAXON, $taxon));

        return new Entity(
            id: $id,
            label: \is_string($name) && '' !== $name ? $name : $id->value,
            domain: Domain::BIOLOGY,
            kind: 'protein',
            properties: $properties,
            url: 'https://www.ebi.ac.uk/QuickGO/annotations?geneProductId='.rawurlencode($id->value),
            source: 'quickgo',
            related: array_filter($related, static fn (Identifiers $ids) => \count($ids) > 0),
        );
    }
}
