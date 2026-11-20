<?php

declare(strict_types=1);

namespace Dopple;

use Dopple\CrawlerResponses\Binary;
use Dopple\CrawlerResponses\CrawlerResponse;
use Dopple\CrawlerResponses\Css;
use Dopple\CrawlerResponses\Html;
use Dopple\CrawlerResponses\Js;
use Dopple\CrawlerResponses\Redirect;
use Dopple\CrawlerResponses\Xml;
use Spatie\Crawler\CrawlResponse;
use Uri\Rfc3986\Uri;

/**
 * Turns a crawl response into the resources it produced: a Redirect if the URL redirected, then the content.
 *
 * Relies on the crawler being `internalOnly()`: the requested URL is always on the origin being mirrored.
 */
final class CrawledResourceFactory
{
    /**
     * @return list<CrawlerResponse>
     */
    public static function fromResponse(string $requestedUrl, CrawlResponse $response): array
    {
        $history = $response->redirectHistory();
        $finalUrl = $history === [] ? $requestedUrl : end($history);

        $requested = new Uri($requestedUrl);
        $final = new Uri($finalUrl);

        $resources = [];

        if ($finalUrl !== $requestedUrl) {
            $from = self::relative($requested, $requested);
            $to = self::relative($final, $requested);

            // /about => /about/ is handled by Apache itself
            if (rtrim($from, '/') !== rtrim($to, '/')) {
                $resources[] = new Redirect(new Uri($from), new Uri($to));
            }
        }

        if (self::isSameOrigin($final, $requested)) {
            $resources[] = self::content($final, strtolower((string) $response->header('Content-Type')));
        }

        return $resources;
    }

    private static function content(Uri $uri, string $contentType): CrawlerResponse
    {
        if (
            str_ends_with(strtolower($uri->getPath()), '.xml') ||
            str_contains($contentType, 'application/xml') ||
            str_contains($contentType, 'text/xml')
        ) {
            return new Xml($uri);
        }

        if (str_contains($contentType, 'text/html') || str_contains($contentType, 'application/xhtml+xml')) {
            return new Html($uri);
        }

        if (str_contains($contentType, 'text/css')) {
            return new Css($uri);
        }

        if (str_contains($contentType, 'javascript') || str_contains($contentType, 'ecmascript')) {
            return new Js($uri);
        }

        return new Binary($uri);
    }

    private static function isSameOrigin(Uri $uri, Uri $reference): bool
    {
        return $uri->getHost() === $reference->getHost() && $uri->getPort() === $reference->getPort();
    }

    /**
     * Root-relative `/path?query` for URLs on the same origin as $reference, the URL untouched otherwise.
     */
    private static function relative(Uri $uri, Uri $reference): string
    {
        if (! self::isSameOrigin($uri, $reference)) {
            return $uri->toRawString();
        }

        $path = $uri->getPath() === '' ? '/' : $uri->getPath();

        return $path . ($uri->getQuery() === null || $uri->getQuery() === '' ? '' : '?' . $uri->getQuery());
    }
}
