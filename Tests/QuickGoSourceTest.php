<?php

namespace Omnilab\QuickGo\Tests;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Exception\ProviderException;
use Omnilab\Exception\UnavailableException;
use Omnilab\Model\Domain;
use Omnilab\Model\Identifier;
use Omnilab\Model\Scheme;
use Omnilab\QuickGo\QuickGoSource;
use Omnilab\QuickGo\QuickGoSourceFactory;
use Omnilab\Source\Capability;
use Omnilab\Source\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Fixtures recorded from www.ebi.ac.uk/QuickGO/services on 2026-10-15:
 * oxygen binding (GO:0019825) and cytosol (GO:0005829) with their paths to
 * their aspects' roots; GO:0000004, a secondary id of biological_process;
 * an unknown term (no hits); the GO annotations of hemoglobin beta
 * (UniProt P68871: 93) and of an accession with none; the search for
 * "apoptosis" in two pages of three (352 hits), and for a text nothing
 * matches.
 */
final class QuickGoSourceTest extends TestCase
{
    /** @var list<string> the paths asked, with their queries */
    private array $calls = [];

    private function source(?MockResponse $response = null, array $options = []): QuickGoSource
    {
        $http = new MockHttpClient(function (string $method, string $url) use ($response): MockResponse {
            $path = rawurldecode((string) parse_url($url, \PHP_URL_PATH));
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
            self::assertStringStartsWith('/QuickGO/services/', $path);
            $raw = (string) parse_url($url, \PHP_URL_QUERY);
            $this->calls[] = $call = substr($path, \strlen('/QuickGO/services/')).('' === $raw ? '' : '?'.rawurldecode($raw));
            if ($response) {
                return $response;
            }
            $fixture = match (true) {
                (bool) preg_match('~^ontology/go/terms/GO:(\d{7})$~', $call, $m) => is_file(__DIR__.'/Fixtures/term-GO_'.$m[1].'.json') ? 'term-GO_'.$m[1].'.json' : 'term-notfound.json',
                (bool) preg_match('~^ontology/go/terms/GO:(\d{7})/paths/GO:\d{7}$~', $call, $m) => 'paths-GO_'.$m[1].'.json',
                str_starts_with($call, 'annotation/search?') => 'UniProtKB:P68871' === $query['geneProductId'] ? 'annotations-P68871.json' : 'annotations-notfound.json',
                str_starts_with($call, 'ontology/go/search?') => 'apoptosis' === $query['query'] ? 'search-apoptosis-'.$query['page'].'.json' : 'search-notfound.json',
            };

            return new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.$fixture));
        });
        $source = (new QuickGoSourceFactory($http))->create($options);
        self::assertInstanceOf(QuickGoSource::class, $source);

        return $source;
    }

    public function testCytosolAndItsParents(): void
    {
        $term = $this->source()->entity('GO:0005829');

        self::assertSame('go:0005829', $term->id->key());
        self::assertSame('cytosol', $term->label);
        self::assertSame(Domain::BIOLOGY, $term->domain);
        self::assertSame('cellular component', $term->kind);
        self::assertSame('The part of the cytoplasm that does not contain organelles but which does contain other particulate matter, such as protein complexes.', $term->description);
        self::assertSame('https://www.ebi.ac.uk/QuickGO/term/GO:0005829', $term->url);
        self::assertSame('cellular component', $term->value('aspect'));
        self::assertFalse($term->value('obsolete'));
        self::assertSame(['go:0005737', 'go:0110165'], $term->related('parent')->keys(), 'the cytoplasm and cellular anatomical structure, its direct parents');
        self::assertSame(['go:0005737'], $term->related('part_of')->keys());
        self::assertSame(['go:0110165'], $term->related('is_a')->keys());
        self::assertSame(['part_of GO:0005737', 'is_a GO:0110165'], array_map('strval', $term->properties('parent')));
        self::assertCount(58, $term->related('child'), 'sixty children, two of them by two relations');
        self::assertSame(['ontology/go/terms/GO:0005829', 'ontology/go/terms/GO:0005829/paths/GO:0005575'], $this->calls, 'the paths to its aspect\'s root');
    }

    public function testOxygenBinding(): void
    {
        $term = $this->source()->entity(Identifier::of(Scheme::GO, '0019825'));

        self::assertSame('oxygen binding', $term->label);
        self::assertSame('molecular function', $term->kind);
        self::assertSame('Binding to oxygen (O2).', $term->description);
        self::assertSame(['cytochrome P450', 'cytochrome P450 activity'], $term->synonyms);
        self::assertSame('Synonym (narrow)', $term->property('synonym')->label);
        self::assertSame(['go:0036094'], $term->related('is_a')->keys(), 'small molecule binding');
        self::assertSame('ontology/go/terms/GO:0019825/paths/GO:0003674', $this->calls[1]);
    }

    public function testASecondaryIdAndARoot(): void
    {
        $term = $this->source()->entity('GO_0000004');

        self::assertSame('go:0008150', (string) $term->id, 'merged into biological_process');
        self::assertSame(['go:0008150', 'go:0000004'], $term->identifiers()->keys(), 'the id it was asked by kept');
        self::assertSame([], $term->related('parent')->keys(), 'a root');
        self::assertSame(['ontology/go/terms/GO:0000004'], $this->calls, 'no path from a root to itself');
    }

    public function testTheIdentifierInItsForms(): void
    {
        foreach (['GO:0005829', 'go:0005829', 'https://www.ebi.ac.uk/QuickGO/term/GO:0005829', 'GO_0005829'] as $id) {
            self::assertSame('go:0005829', (string) $this->source()->entity($id)?->id, $id);
        }
    }

    public function testHemoglobinBetaAndItsAnnotations(): void
    {
        $protein = $this->source()->entity('P68871');

        self::assertSame('uniprot:P68871', (string) $protein->id);
        self::assertSame('Hemoglobin subunit beta', $protein->label);
        self::assertSame('protein', $protein->kind);
        self::assertSame('HBB', $protein->value('gene'));
        self::assertSame(['ncbi.taxon:9606'], $protein->related('organism')->keys());
        self::assertCount(8, $protein->related('function'));
        self::assertCount(11, $protein->related('process'));
        self::assertCount(9, $protein->related('component'));
        self::assertSame('peroxidase activity (GO:0004601; IDA)', $protein->value('molecular_function'));
        self::assertSame('oxygen transport (GO:0015671; IBA, IEA, IDA, NAS, TAS, IMP)', $protein->properties('biological_process')[2]->value);
        self::assertSame('cytosol (GO:0005829; TAS)', $protein->properties('cellular_component')[1]->value);
        self::assertContains('go:0005829', $protein->related('component')->keys());
        self::assertNull($protein->property('negative_annotation'), 'none for this protein');
        self::assertSame(['annotation/search?geneProductId=UniProtKB:P68871&limit=200&page=1&includeFields=goName,name'], $this->calls);
    }

    public function testTheAnnotationsThemselves(): void
    {
        $source = $this->source();
        $annotations = $source->annotations('P68871');

        self::assertCount(93, $annotations);
        self::assertSame('go:0004601', (string) $annotations[0]->term);
        self::assertSame('peroxidase activity', $annotations[0]->name);
        self::assertSame('molecular_function', $annotations[0]->aspect);
        self::assertSame('contributes_to', $annotations[0]->qualifier);
        self::assertSame('IDA', $annotations[0]->evidence);
        self::assertSame('ECO:0000314', $annotations[0]->eco);
        self::assertSame('PMID:19740759', $annotations[0]->reference);
        self::assertSame('BHF-UCL', $annotations[0]->assignedBy);
        self::assertSame('2011-02-16', $annotations[0]->date?->format('Y-m-d'));
        self::assertFalse($annotations[0]->isNegative());

        $source->annotations(Identifier::of(Scheme::UNIPROT, 'P68871'), 'cellular_component');
        self::assertStringContainsString('aspect=cellular_component', $this->calls[1]);
        $this->expectException(\InvalidArgumentException::class);
        $source->annotations('P68871', 'function');
    }

    public function testUnknownIsNull(): void
    {
        self::assertNull($this->source()->entity('GO:9999999'));
        self::assertNull($this->source()->entity('uniprot:Q9ZZZ9'), 'no annotation');
        self::assertSame(['ontology/go/terms/GO:9999999', 'annotation/search?geneProductId=UniProtKB:Q9ZZZ9&limit=200&page=1&includeFields=goName,name'], $this->calls);
    }

    public function testAnotherBaseIsRefusedBeforeAnyCall(): void
    {
        foreach (['CHEBI:15422', 'IPR000971', 'cytosol', 'MONDO:0011382', Identifier::of(Scheme::NCBI_GENE, '3043')] as $id) {
            try {
                $this->source()->entity($id);
                self::fail(\sprintf('%s should be refused', $id));
            } catch (NotSupportedException) {
            }
        }
        self::assertSame([], $this->calls);
    }

    public function testASearchInPages(): void
    {
        $source = $this->source();
        $first = $source->search(new Query('apoptosis', limit: 3));

        self::assertSame(['execution phase of apoptosis', 'lymphocyte apoptotic process', 'hepatoblast apoptotic process'], array_map(static fn ($e) => $e->label, $first->items));
        self::assertSame('biological process', $first->first()->kind);
        self::assertSame(352, $first->total);
        self::assertSame('2', $first->next);
        $second = $source->search(new Query('apoptosis', limit: 3, cursor: $first->next));
        self::assertSame('oligodendrocyte apoptotic process', $second->first()?->label);

        $none = $source->search(new Query('zzqqxx'));
        self::assertCount(0, $none);
        self::assertNull($none->next);
        self::assertCount(0, $source->search(new Query('apoptosis', Domain::EARTH)));
        self::assertCount(3, $this->calls);
    }

    public function testErrors(): void
    {
        try {
            $this->source(new MockResponse('{"url":"http://www.ebi.ac.uk/QuickGO/services/ontology/go/terms/GO:1","messages":["Provided ID: \'GO:1\' at index 0 is invalid"]}', ['http_code' => 400]))->entity('GO:0005829');
            self::fail('a 400');
        } catch (ProviderException $e) {
            self::assertSame(400, $e->status);
            self::assertStringContainsString('is invalid', $e->getMessage());
        }
        $this->expectException(UnavailableException::class);
        $this->source(new MockResponse('', ['http_code' => 503]))->entity('GO:0005829');
    }

    public function testTheFactory(): void
    {
        $factory = new QuickGoSourceFactory(new MockHttpClient());

        self::assertSame('quickgo', $factory->getName());
        self::assertSame([Capability::ENTITY, Capability::SEARCH], $factory->create()->capabilities());
    }
}
