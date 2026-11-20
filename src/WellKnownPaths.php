<?php

declare(strict_types=1);

namespace Dopple;

use Countable;
use Generator;
use IteratorAggregate;

final class WellKnownPaths implements Countable, IteratorAggregate
{
    private const array PATHS = [
        '/robots.txt',
        '/humans.txt',
        '/sitemap.xml',
        '/sitemap_index.xml',
        '/ads.txt',
        '/app-ads.txt',
        '/llms.txt',
        '/security.txt',
        '/.well-known/security.txt',
        '/favicon.ico',
        '/manifest.json',
        '/site.webmanifest',
        '/browserconfig.xml',
        '/opensearch.xml',
        '/apple-touch-icon.png',
        '/apple-touch-icon-precomposed.png',
        '/favicon.svg',
        '/favicon.png',
        '/sitemap-index.xml',
        '/sitemap.xml.gz',
        '/wp-sitemap.xml',
        '/feed',
        '/rss.xml',
        '/atom.xml',
        '/feed.xml',
    ];

    public function count(): int
    {
        return count(self::PATHS);
    }

    /** @return Generator<int, string> */
    public function getIterator(): Generator
    {
        yield from self::PATHS;
    }
}
