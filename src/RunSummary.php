<?php

declare(strict_types=1);

namespace Dopple;

use Dopple\CrawlerResponses\Redirect;

final class RunSummary
{
    public static function print(
        FileStore $store,
        CrawlerResponses $crawlerResponses,
        int $requestCount,
        int $startedAt,
    ): void {
        $wallSeconds = (hrtime(true) - $startedAt) / 1e9;
        $pathCount = 0;

        foreach ($crawlerResponses as $crawlerResponse) {
            if (! $crawlerResponse instanceof Redirect) {
                $pathCount++;
            }
        }

        $totalSize = 0;
        $largestPath = null;
        $largestSize = 0;

        foreach ($store->files() as $path => $file) {
            $totalSize += $file['size'];

            if ($file['size'] > $largestSize) {
                $largestSize = $file['size'];
                $largestPath = $path;
            }
        }

        $rows = [
            'Requests made' => (string) $requestCount,
            'Paths downloaded' => (string) $pathCount,
            'Total time' => number_format($wallSeconds, 1) . ' s',
            'Speed' => number_format($wallSeconds > 0 ? $requestCount / $wallSeconds : 0, 1) . ' rps',
            'Total size' => self::formatBytes($totalSize),
        ];

        if ($largestPath !== null) {
            $rows['Largest file'] = '/' . $largestPath . ' (' . self::formatBytes($largestSize) . ')';
        }

        $width = $rows
            |> array_keys(?)
            |> array_map(strlen(...), ?)
            |> max(...);

        fwrite(STDOUT, PHP_EOL);

        foreach ($rows as $label => $value) {
            fwrite(STDOUT, str_pad($label, $width) . '  ' . $value . PHP_EOL);
        }
    }

    private static function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes < 1024 => $bytes . ' B',
            $bytes < 1024 * 1024 => number_format($bytes / 1024, 1) . ' KB',
            default => number_format($bytes / (1024 * 1024), 1) . ' MB',
        };
    }
}
