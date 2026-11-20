<?php

declare(strict_types=1);

namespace Dopple\CrawlerResponses;

use Uri\Rfc3986\Uri;

interface CrawlerResponse
{
    public Uri $originalUrl { get; }

    /**
     * Whether the body can contain URLs, so same-origin ones need making root-relative.
     */
    public bool $canReferenceUrls { get; }
}
