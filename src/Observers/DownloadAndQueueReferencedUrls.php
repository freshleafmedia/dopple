<?php

declare(strict_types=1);

namespace Dopple\Observers;

use Dopple\CrawlerResponses;
use Dopple\CrawledResourceFactory;
use Dopple\CrawlerResponses\NotFound;
use Dopple\CrawlerResponses\Redirect;
use Dopple\FileStore;
use Dopple\UrlExtractor;
use GuzzleHttp\Exception\RequestException;
use Spatie\Crawler\CrawlObservers\CrawlObserver;
use Spatie\Crawler\CrawlProgress;
use Spatie\Crawler\CrawlResponse;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlUrl;
use Spatie\Crawler\Enums\ResourceType;
use Spatie\Crawler\TransferStatistics;
use Uri\Rfc3986\Uri;

final class DownloadAndQueueReferencedUrls extends CrawlObserver
{
    public function __construct(
        private readonly Crawler $crawler,
        private readonly FileStore $store,
        private readonly CrawlerResponses $crawledResources,
        private readonly Uri $notFoundProbeUrl,
    ) {}

    public function crawlFailed(
        string $url,
        RequestException $requestException,
        CrawlProgress $progress,
        ?string $foundOnUrl = null,
        ?string $linkText = null,
        ?ResourceType $resourceType = null,
        ?TransferStatistics $transferStats = null,
    ): void {
        $response = $requestException->getResponse();

        if ($url !== $this->notFoundProbeUrl->toRawString() || $response?->getStatusCode() !== 404) {
            return;
        }

        $body = (string) $response->getBody();
        $resource = new NotFound(new Uri($url));

        $this->store->write(FileStore::makeFilePathForStorage($resource), $body);
        $this->crawledResources->add($resource);

        foreach (UrlExtractor::fromResource($resource, $body, new Uri($url)) as $referencedUrl) {
            $this->crawler->addToCrawlQueue(new CrawlUrl($referencedUrl->toRawString(), $url));
        }
    }

    public function crawled(string $url, CrawlResponse $response, CrawlProgress $progress): void
    {
        $history = $response->redirectHistory();
        $finalUrl = $history === [] ? $url : end($history);

        foreach (CrawledResourceFactory::fromResponse($url, $response) as $resource) {
            $this->crawledResources->add($resource);

            if ($resource instanceof Redirect) {
                continue;
            }

            $this
                ->store
                ->write(
                    FileStore::makeFilePathForStorage($resource),
                    $response->body(),
                );


            $referencedUrls = UrlExtractor::fromResource($resource, $response->body(), new Uri($finalUrl));

            foreach ($referencedUrls as $referencedUrl) {
                $this->crawler->addToCrawlQueue(new CrawlUrl($referencedUrl->toRawString(), $finalUrl));
            }
        }
    }
}
