<?php

declare(strict_types=1);

namespace Dopple\Observers;

use Dopple\TerminalColour;
use GuzzleHttp\Exception\RequestException;
use Spatie\Crawler\CrawlObservers\CrawlObserver;
use Spatie\Crawler\CrawlProgress;
use Spatie\Crawler\CrawlResponse;
use Spatie\Crawler\Enums\ResourceType;
use Spatie\Crawler\TransferStatistics;

final class PrintProgress extends CrawlObserver
{
    public function crawled(string $url, CrawlResponse $response, CrawlProgress $progress): void
    {
        fwrite(
            STDOUT,
            '[' . $progress->urlsProcessed . '/' . $progress->urlsFound . '] '
                . self::colourStatus($response->status()) . ' '
                . self::formatBytes(strlen($response->body())) . ' ' . $url . PHP_EOL,
        );
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
        $prefix = '[' . $progress->urlsProcessed . '/' . $progress->urlsFound . '] ';
        $response = $requestException->getResponse();

        if ($response !== null) {
            fwrite(
                STDOUT,
                $prefix . self::colourStatus($response->getStatusCode()) . ' '
                    . self::formatBytes((int) $response->getBody()->getSize()) . ' ' . $url . PHP_EOL,
            );

            return;
        }

        fwrite(
            STDOUT,
            $prefix . TerminalColour::Red->apply('FAILED') . ' ' . $url . ' [' . $requestException->getMessage() . ']' . PHP_EOL,
        );
    }

    private static function formatBytes(int $bytes): string
    {
        $size = match (true) {
            $bytes < 1024 => $bytes . ' B ',
            $bytes < 1024 * 1024 => number_format($bytes / 1024, 1) . ' KB',
            default => number_format($bytes / (1024 * 1024), 1) . ' MB',
        };

        return str_pad($size, 9, ' ', STR_PAD_LEFT);
    }

    private static function colourStatus(int $status): string
    {
        $colour = match (true) {
            $status >= 200 && $status < 300 => TerminalColour::Green,
            $status >= 300 && $status < 400 => TerminalColour::Yellow,
            default => TerminalColour::Red,
        };

        return $colour->apply((string) $status);
    }
}
