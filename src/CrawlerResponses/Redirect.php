<?php

declare(strict_types=1);

namespace Dopple\CrawlerResponses;

use Uri\Rfc3986\Uri;

final class Redirect implements CrawlerResponse
{
    public function __construct(
        public readonly Uri $from,
        public readonly Uri $to,
    ) {}

    public Uri $originalUrl {
        get => $this->from;
    }

    public bool $canReferenceUrls {
        get => false;
    }
}
