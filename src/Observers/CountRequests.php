<?php

declare(strict_types=1);

namespace Dopple\Observers;

use GuzzleHttp\Exception\RequestException;
use Spatie\Crawler\CrawlObservers\CrawlObserver;
use Spatie\Crawler\CrawlProgress;
use Spatie\Crawler\CrawlResponse;
use Spatie\Crawler\Enums\ResourceType;
use Spatie\Crawler\TransferStatistics;

final class CountRequests extends CrawlObserver
{
    public private(set) int $requests = 0;

    public function crawled(string $url, CrawlResponse $response, CrawlProgress $progress): void
    {
        $this->requests++;
    }

    public function crawlFailed(
        string $url,
        RequestException $requestException,
        CrawlProgress $progress,
        ?string $foundOnUrl = null,
        ?string $linkText = null,
        ?ResourceType $resourceType = null,
        ?TransferStatistics $transferStats = null,
    ): void {
        $this->requests++;
    }
}
