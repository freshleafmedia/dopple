<?php

declare(strict_types=1);

namespace Dopple;

use Spatie\Crawler\UrlParsers\UrlParser;

final class NullUrlParser implements UrlParser
{
    public function extractUrls(string $html, string $baseUrl): array
    {
        return [];
    }
}
