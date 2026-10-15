<?php

namespace Omnilab\QuickGo;

use Omnilab\Config;
use Omnilab\Source\SourceFactory;
use Omnilab\Source\SourceInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * QuickGO: Gene Ontology terms and their parents, the GO annotations of a
 * UniProt protein, a search of terms - no key needed.
 *
 *   options:
 *     mailto: 'support@glitchr.io'     # in the User-Agent
 *     annotation_pages: 5              # pages of 200 annotations a protein's reading takes at most
 *     throttle: 0                      # seconds between two calls: QuickGO publishes no rate
 *     base_uri: 'https://www.ebi.ac.uk/QuickGO/services/'
 */
final class QuickGoSourceFactory extends SourceFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnilab.factory_name' => 'quickgo',
            'omnilab.required_options' => [],
            'annotation_pages' => 5,
            'throttle' => 0.0,
            'base_uri' => QuickGoSource::BASE_URI,
        ]);
    }

    protected function build(Config $c): SourceInterface
    {
        return new QuickGoSource(
            $this->http ?? HttpClient::create(),
            (int) $c->get('annotation_pages'),
            (string) $c->get('base_uri', QuickGoSource::BASE_URI),
            ['User-Agent' => self::userAgent($c)],
            (float) $c->get('throttle'),
        );
    }
}
