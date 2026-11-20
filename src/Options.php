<?php

declare(strict_types=1);

namespace Dopple;

use Uri\InvalidUriException;
use Uri\Rfc3986\Uri;

final readonly class Options
{
    private const USAGE = 'Usage: dopple <url> <output-path> [--concurrency=N] [--timeout=N] [--max-pages=N]';

    public function __construct(
        public Uri $startUrl,
        public string $outputPath,
        public int $concurrency = 10,
        public int $timeout = 30,
        public ?int $maxPages = 10_000,
    ) {}

    public static function parse(array $arguments): self
    {
        $values = [];
        $positional = 0;

        foreach ($arguments as $argument) {
            if ($argument === '-h' || $argument === '--help') {
                self::help();
            }

            if (preg_match('/^--concurrency=(?<concurrency>\d+)$/', $argument, $matches)) {
                $values['concurrency'] = max(1, (int) $matches['concurrency']);

                continue;
            }

            if (preg_match('/^--timeout=(?<timeout>\d+)$/', $argument, $matches)) {
                $values['timeout'] = max(1, (int) $matches['timeout']);

                continue;
            }

            if (preg_match('/^--max-pages=(?<maxPages>\d+)$/', $argument, $matches)) {
                $values['maxPages'] = max(1, (int) $matches['maxPages']);

                continue;
            }

            if (str_starts_with($argument, '-')) {
                self::error('Unknown option: ' . $argument);
            }

            match ($positional++) {
                0 => $values['startUrl'] = $argument,
                1 => $values['outputPath'] = rtrim($argument, '/'),
                default => self::error('Unexpected argument: ' . $argument),
            };
        }

        if (! isset($values['startUrl'])) {
            self::error('Missing argument: <url>');
        }

        if (! isset($values['outputPath'])) {
            self::error('Missing argument: <output-path>');
        }

        if (! str_contains($values['startUrl'], '://')) {
            $values['startUrl'] = 'https://' . $values['startUrl'];
        }

        if (filter_var($values['startUrl'], FILTER_VALIDATE_URL) === false) {
            self::error('Invalid URL: ' . $values['startUrl']);
        }

        try {
            $values['startUrl'] = new Uri($values['startUrl']);
        } catch (InvalidUriException) {
            self::error('Invalid URL: ' . $values['startUrl']);
        }

        return new self(...$values);
    }

    public static function help(): never
    {
        fwrite(STDOUT, self::USAGE . PHP_EOL);

        exit(0);
    }

    public static function error(string $message): never
    {
        fwrite(STDERR, $message . PHP_EOL);

        exit(2);
    }
}
