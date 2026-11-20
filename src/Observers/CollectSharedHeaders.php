<?php

declare(strict_types=1);

namespace Dopple\Observers;

use Spatie\Crawler\CrawlObservers\CrawlObserver;
use Spatie\Crawler\CrawlProgress;
use Spatie\Crawler\CrawlResponse;

/**
 * Collects the response headers present with the same value on every successful response.
 */
final class CollectSharedHeaders extends CrawlObserver
{
    /** Per-response or transport headers which should not be copied to the mirror. */
    private const array IGNORED = [
        'age',
        'accept-ranges',
        'cache-control',
        'connection',
        'content-encoding',
        'content-length',
        'content-type',
        'date',
        'etag',
        'expires',
        'keep-alive',
        'last-modified',
        'location',
        'server',
        'set-cookie',
        'transfer-encoding',
        'vary',
    ];

    /** @var array<string, array{name: string, value: string}>|null */
    private ?array $shared = null;

    /** @return array<string, string> Header name => value */
    public function headers(): array
    {
        $headers = [];

        foreach ($this->shared ?? [] as $header) {
            $headers[$header['name']] = $header['value'];
        }

        return $headers;
    }

    public function crawled(string $url, CrawlResponse $response, CrawlProgress $progress): void
    {
        if (! $response->isSuccessful()) {
            return;
        }

        $current = [];

        foreach ($response->headers() as $name => $values) {
            $key = strtolower((string) $name);

            if (in_array($key, self::IGNORED, true)) {
                continue;
            }

            $current[$key] = [
                'name' => (string) $name,
                'value' => implode(', ', $values),
            ];
        }

        if ($this->shared === null) {
            $this->shared = $current;
        } else {
            $this->shared = array_filter(
                $this->shared,
                static fn(array $header, string $key): bool => ($current[$key]['value'] ?? null) === $header['value'],
                ARRAY_FILTER_USE_BOTH,
            );
        }
    }
}
