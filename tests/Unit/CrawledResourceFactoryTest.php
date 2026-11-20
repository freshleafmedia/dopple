<?php

declare(strict_types=1);

use Dopple\CrawledResourceFactory;
use Dopple\CrawlerResponses\Binary;
use Dopple\CrawlerResponses\Css;
use Dopple\CrawlerResponses\Html;
use Dopple\CrawlerResponses\Js;
use Dopple\CrawlerResponses\Redirect;
use Dopple\CrawlerResponses\Xml;
use GuzzleHttp\RedirectMiddleware;
use Spatie\Crawler\CrawlResponse;

test('treats an .xml path served as xhtml as xml', function (): void {
    $response = CrawlResponse::fake(headers: ['Content-Type' => 'application/xhtml+xml']);

    expect(CrawledResourceFactory::fromResponse('https://example.com/sitemap.xml', $response)[0])
        ->toBeInstanceOf(Xml::class);
});

test('treats an .xml path served as html as xml', function (): void {
    $response = CrawlResponse::fake(headers: ['Content-Type' => 'text/html; charset=utf-8']);

    expect(CrawledResourceFactory::fromResponse('https://example.com/sitemap.xml', $response)[0])
        ->toBeInstanceOf(Xml::class);
});

test('treats an uppercase .XML path as xml', function (): void {
    $response = CrawlResponse::fake(headers: ['Content-Type' => 'text/html']);

    expect(CrawledResourceFactory::fromResponse('https://example.com/SITEMAP.XML', $response)[0])
        ->toBeInstanceOf(Xml::class);
});

test('treats xml content types as xml regardless of path', function (string $contentType): void {
    $response = CrawlResponse::fake(headers: ['Content-Type' => $contentType]);

    expect(CrawledResourceFactory::fromResponse('https://example.com/feed', $response)[0])
        ->toBeInstanceOf(Xml::class);
})->with([
    'application/xml',
    'text/xml; charset=utf-8',
]);

test('treats xhtml pages without an .xml path as html', function (): void {
    $response = CrawlResponse::fake(headers: ['Content-Type' => 'application/xhtml+xml']);

    expect(CrawledResourceFactory::fromResponse('https://example.com/about', $response)[0])
        ->toBeInstanceOf(Html::class);
});

test('does not treat a path merely containing xml as xml', function (): void {
    $response = CrawlResponse::fake(headers: ['Content-Type' => 'text/html']);

    expect(CrawledResourceFactory::fromResponse('https://example.com/xml-guide', $response)[0])
        ->toBeInstanceOf(Html::class);
});

test('treats html content types as html', function (string $contentType): void {
    $response = CrawlResponse::fake(headers: ['Content-Type' => $contentType]);

    expect(CrawledResourceFactory::fromResponse('https://example.com/about', $response)[0])
        ->toBeInstanceOf(Html::class);
})->with([
    'text/html',
    'text/html; charset=UTF-8',
    'TEXT/HTML',
    'application/xhtml+xml',
]);

test('treats css content types as css', function (string $contentType): void {
    $response = CrawlResponse::fake(headers: ['Content-Type' => $contentType]);

    expect(CrawledResourceFactory::fromResponse('https://example.com/css/app.css', $response)[0])
        ->toBeInstanceOf(Css::class);
})->with([
    'text/css',
    'text/css; charset=utf-8',
]);

test('treats javascript content types as js', function (string $contentType): void {
    $response = CrawlResponse::fake(headers: ['Content-Type' => $contentType]);

    expect(CrawledResourceFactory::fromResponse('https://example.com/js/app.js', $response)[0])
        ->toBeInstanceOf(Js::class);
})->with([
    'application/javascript',
    'text/javascript; charset=utf-8',
    'application/x-javascript',
    'application/ecmascript',
    'text/ecmascript',
]);

test('treats other content types as binary', function (string $contentType): void {
    $response = CrawlResponse::fake(headers: ['Content-Type' => $contentType]);

    expect(CrawledResourceFactory::fromResponse('https://example.com/file', $response)[0])
        ->toBeInstanceOf(Binary::class);
})->with([
    'image/png',
    'application/pdf',
    'application/json',
    'font/woff2',
    'application/octet-stream',
]);

test('treats a missing content type as binary', function (): void {
    $resources = CrawledResourceFactory::fromResponse('https://example.com/file', CrawlResponse::fake());

    expect($resources)->toHaveCount(1)
        ->and($resources[0])->toBeInstanceOf(Binary::class);
});

test('keeps the requested url on the resource', function (): void {
    $response = CrawlResponse::fake(headers: ['Content-Type' => 'text/html']);

    expect(CrawledResourceFactory::fromResponse('https://example.com/about?x=1', $response)[0]->originalUrl->toRawString())
        ->toBe('https://example.com/about?x=1');
});

test('returns only the content when there was no redirect', function (): void {
    $resources = CrawledResourceFactory::fromResponse(
        'https://example.com/about',
        CrawlResponse::fake(headers: ['Content-Type' => 'text/html']),
    );

    expect($resources)
        ->toHaveCount(1)
        ->and($resources[0])->toBeInstanceOf(Html::class);
});

test('returns a redirect then the final content when redirected', function (): void {
    $resources = CrawledResourceFactory::fromResponse(
        'https://example.com/old',
        CrawlResponse::fake(headers: [
            'Content-Type' => 'text/html',
            RedirectMiddleware::HISTORY_HEADER => ['https://example.com/new'],
        ]),
    );

    expect($resources)
        ->toHaveCount(2)
        ->and($resources[0])->toBeInstanceOf(Redirect::class)
        ->and($resources[0]->from->toRawString())->toBe('/old')
        ->and($resources[0]->to->toRawString())->toBe('/new')
        ->and($resources[1])->toBeInstanceOf(Html::class)
        ->and($resources[1]->originalUrl->toRawString())->toBe('https://example.com/new');
});

test('keeps the query string on redirect urls', function (): void {
    $resources = CrawledResourceFactory::fromResponse(
        'https://example.com/old?a=1',
        CrawlResponse::fake(headers: [
            'Content-Type' => 'text/html',
            RedirectMiddleware::HISTORY_HEADER => ['https://example.com/new?b=2'],
        ]),
    );

    expect($resources[0]->from->toRawString())
        ->toBe('/old?a=1')
        ->and($resources[0]->to->toRawString())->toBe('/new?b=2');
});

test('does not create a redirect when only the trailing slash differs', function (): void {
    $resources = CrawledResourceFactory::fromResponse(
        'https://example.com/about',
        CrawlResponse::fake(headers: [
            'Content-Type' => 'text/html',
            RedirectMiddleware::HISTORY_HEADER => ['https://example.com/about/'],
        ]),
    );

    expect($resources)
        ->toHaveCount(1)
        ->and($resources[0])->toBeInstanceOf(Html::class);
});

test('uses the last url in the redirect history as the final url', function (): void {
    $resources = CrawledResourceFactory::fromResponse(
        'https://example.com/a',
        CrawlResponse::fake(headers: [
            'Content-Type' => 'text/css',
            RedirectMiddleware::HISTORY_HEADER => ['https://example.com/b', 'https://example.com/c.css'],
        ]),
    );

    expect($resources[0]->to->toRawString())
        ->toBe('/c.css')
        ->and($resources[1])->toBeInstanceOf(Css::class);
});

test('keeps an off-origin redirect target as an absolute url and drops the content', function (): void {
    $resources = CrawledResourceFactory::fromResponse(
        'https://example.com/out',
        CrawlResponse::fake(headers: [
            'Content-Type' => 'text/html',
            RedirectMiddleware::HISTORY_HEADER => ['https://other.com/page'],
        ]),
    );

    expect($resources)
        ->toHaveCount(1)
        ->and($resources[0])->toBeInstanceOf(Redirect::class)
        ->and($resources[0]->from->toRawString())->toBe('/out')
        ->and($resources[0]->to->toRawString())->toBe('https://other.com/page');
});

test('classifies the redirect target, not the requested url, as xml', function (): void {
    $resources = CrawledResourceFactory::fromResponse(
        'https://example.com/sitemap',
        CrawlResponse::fake(headers: [
            'Content-Type' => 'text/html',
            RedirectMiddleware::HISTORY_HEADER => ['https://example.com/sitemap.xml'],
        ]),
    );

    expect($resources[1])
        ->toBeInstanceOf(Xml::class);
});
