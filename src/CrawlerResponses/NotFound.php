<?php

declare(strict_types=1);

namespace Dopple\CrawlerResponses;

use Uri\Rfc3986\Uri;

/**
 * The site's 404 page, captured by requesting a URL that does not exist. Stored as `/404.html`.
 */
final class NotFound implements CrawlerResponse
{
    public const string FILE_PATH = '404.html';

    public function __construct(
        public readonly Uri $originalUrl,
    ) {}

    public bool $canReferenceUrls {
        get => true;
    }
}
