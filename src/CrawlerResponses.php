<?php

declare(strict_types=1);

namespace Dopple;

use Countable;
use Dopple\CrawlerResponses\CrawlerResponse;
use Generator;
use IteratorAggregate;

/**
 * Every resource found by the crawl, in crawl order.
 *
 * @implements IteratorAggregate<int, CrawlerResponse>
 */
final class CrawlerResponses implements Countable, IteratorAggregate
{
    /** @var list<CrawlerResponse> */
    private array $resources = [];

    public function add(CrawlerResponse $resource): void
    {
        $this->resources[] = $resource;
    }

    public function count(): int
    {
        return count($this->resources);
    }

    /** @return Generator<int, CrawlerResponse> */
    public function getIterator(): Generator
    {
        yield from $this->resources;
    }
}
