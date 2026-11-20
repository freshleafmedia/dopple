<?php

declare(strict_types=1);

namespace Dopple\CrawlerResponses;

use Uri\Rfc3986\Uri;

final class Js implements CrawlerResponse
{
    public function __construct(
        public readonly Uri $originalUrl,
    ) {}

    public bool $canReferenceUrls {
        get => true;
    }
}
