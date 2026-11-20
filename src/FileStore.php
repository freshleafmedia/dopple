<?php

declare(strict_types=1);

namespace Dopple;

use Dopple\CrawlerResponses\CrawlerResponse;
use Dopple\CrawlerResponses\Html;
use Dopple\CrawlerResponses\NotFound;
use FilesystemIterator;
use Generator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class FileStore
{
    public function __construct(
        private readonly string $root,
    ) {
        if (! $this->isEmpty()) {
            fwrite(STDERR, 'Output path is not empty [' . $this->root . ']' . PHP_EOL);
            exit(1);
        }
    }

    public function isEmpty(): bool
    {
        return ! is_dir($this->root) || count(scandir($this->root)) === 2;
    }

    public function write(string $file, string $content): void
    {
        $target = $this->root . '/' . $file;
        $directory = dirname($target);

        if (! is_dir($directory) && ! @mkdir($directory, recursive: true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create directory [' . $directory . ']');
        }

        if (@file_put_contents($target, $content) === false) {
            throw new RuntimeException('Cannot write to [' . $target . ']');
        }
    }

    /**
     * Every file under the root.
     *
     * @return Generator<string, array{size: int}> keyed by path relative to the root
     */
    public function files(): Generator
    {
        if (! is_dir($this->root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            yield substr($file->getPathname(), strlen($this->root) + 1) => [
                'size' => $file->getSize(),
            ];
        }
    }

    public function read(string $file): string
    {
        $content = @file_get_contents($this->root . '/' . $file);

        if ($content === false) {
            throw new RuntimeException('Cannot read from [' . $file . ']');
        }

        return $content;
    }

    /**
     * Maps a URL to the file path that stores it, relative to the storage root.
     *
     * /about => /about.html
     * /blog/ => /blog/index.html
     * /blog?page=2 => /blog@query=<hash>.html
     * /css/app.css?v=1 => /css/app@query=<hash>.css
     */
    public static function makeFilePathForStorage(CrawlerResponse $resource): string
    {
        if ($resource instanceof NotFound) {
            return NotFound::FILE_PATH;
        }

        $uriPath = rawurldecode('/' . ltrim($resource->originalUrl->getPath(), '/'));
        $query = $resource->originalUrl->getQuery() === '' ? null : $resource->originalUrl->getQuery();

        $segments = str_replace("\0", '', $uriPath)
            |> explode('/', ?)
            |> array_diff(?, ['', '.', '..'])
            |> array_values(...);

        if (str_ends_with($uriPath, '/')) {
            $segments[] = 'index.html';
        } elseif ($resource instanceof Html && $segments !== []) {
            $lastSegment = array_pop($segments);

            if (!str_ends_with($lastSegment, '.html')) {
                $lastSegment .= '.html';
            }

            $segments[] = $lastSegment;
        }

        $filePath = implode('/', $segments);

        if ($query === null) {
            return $filePath;
        }

        $directory = pathinfo($filePath, PATHINFO_DIRNAME)
            |> (fn (string $directory): string => $directory === '.' ? '' : $directory . '/');
        $fileName = pathinfo($filePath, PATHINFO_FILENAME);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $extension = $extension === '' ? '' : '.' . $extension;
        $hash = substr(hash('sha256', $query), 0, 16);

        return $directory . $fileName . '@query=' . $hash . $extension;
    }
}
