<?php

declare(strict_types=1);

namespace Dopple\CrawlerResponses;

use Uri\Rfc3986\Uri;

/**
 * XML documents such as sitemaps and feeds. Stored under their own path, without an `.html` extension.
 */
final class Xml implements CrawlerResponse
{
    public function __construct(
        public readonly Uri $originalUrl,
    ) {}

    public bool $canReferenceUrls {
        get => true;
    }
}
