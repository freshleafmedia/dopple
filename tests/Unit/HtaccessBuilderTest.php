<?php

declare(strict_types=1);

use Dopple\CrawlerResponses;
use Dopple\CrawlerResponses\Css;
use Dopple\CrawlerResponses\Html;
use Dopple\CrawlerResponses\NotFound;
use Dopple\CrawlerResponses\Redirect;
use Dopple\FileStore;
use Dopple\HtaccessBuilder;
use Uri\Rfc3986\Uri;

test('always includes the base directives and html fallback rule', function (): void {
    $output = new HtaccessBuilder()->build(new CrawlerResponses());

    expect($output)
        ->toContain('DirectorySlash Off')
        ->toContain('RewriteEngine On')
        ->toContain('RewriteBase /')
        ->toContain('RewriteRule ^(.+?)/?$ $1.html [L]')
        ->not->toContain('ErrorDocument')
        ->not->toContain('Header always set')
        ->not->toContain('Query string variants')
        ->toEndWith("\n");
});

test('writes shared headers', function (): void {
    $output = new HtaccessBuilder()->build(new CrawlerResponses(), ['X-Frame-Options' => 'DENY']);

    expect($output)
        ->toContain('# Headers shared by every crawled page')
        ->toContain('Header always set X-Frame-Options "DENY"');
});

test('escapes quotes and backslashes in shared header values', function (): void {
    $output = new HtaccessBuilder()->build(new CrawlerResponses(), ['X-Test' => 'a "b" \\c']);

    expect($output)->toContain('Header always set X-Test "a \"b\" \\\\c"');
});

test('writes a path only redirect as RedirectMatch', function (): void {
    $responses = new CrawlerResponses();
    $responses->add(
        new Redirect(new Uri('https://example.com/old'), new Uri('https://example.com/new')),
    );

    expect(new HtaccessBuilder()->build($responses))
        ->toContain('RedirectMatch 301 ^/old$ https://example.com/new');
});

test('escapes regex characters and spaces in redirect paths', function (): void {
    $responses = new CrawlerResponses();
    $responses->add(
        new Redirect(
            new Uri('https://example.com/a.b%20c'),
            new Uri('https://example.com/new'),
        ),
    );

    expect(new HtaccessBuilder()->build($responses))
        ->toContain('RedirectMatch 301 ^/a\.b\x20c$ ');
});

test('encodes spaces in the redirect target', function (): void {
    $responses = new CrawlerResponses();
    $responses->add(
        new Redirect(
            new Uri('https://example.com/old'),
            new Uri('https://example.com/a%20b'),
        ),
    );

    expect(new HtaccessBuilder()->build($responses))
        ->toContain(' https://example.com/a%20b');
});

test('writes a redirect with a query string as a rewrite rule', function (): void {
    $responses = new CrawlerResponses();
    $responses->add(
        new Redirect(
            new Uri('https://example.com/old?page=2'),
            new Uri('https://example.com/new')
        ),
    );

    expect(new HtaccessBuilder()->build($responses))
        ->toContain('RewriteCond %{QUERY_STRING} ^page\=2$')
        ->toContain('RewriteRule ^old$ https://example.com/new [R=301,L,QSD]')
        ->not->toContain('RedirectMatch')
        ->not->toContain('Query string variants');
});

test('keeps the query string on a redirect target', function (): void {
    $responses = new CrawlerResponses();
    $responses->add(
        new Redirect(
            new Uri('https://example.com/old'),
            new Uri('https://example.com/new?page=2'),
        ),
    );

    expect(new HtaccessBuilder()->build($responses))
        ->toContain('RedirectMatch 301 ^/old$ https://example.com/new?page=2');
});

test('keeps the query string on a redirect target when the source has a query string', function (): void {
    $responses = new CrawlerResponses();
    $responses->add(
        new Redirect(
            new Uri('https://example.com/old?page=2'),
            new Uri('https://example.com/new?p=2'),
        ),
    );

    expect(new HtaccessBuilder()->build($responses))
        ->toContain('RewriteCond %{QUERY_STRING} ^page\=2$')
        ->toContain('RewriteRule ^old$ https://example.com/new?p=2 [R=301,L,QSD]');
});

test('rewrites query string variants to the hashed file', function (): void {
    $resource = new Html(new Uri('https://example.com/blog?page=2'));
    $file = FileStore::makeFilePathForStorage($resource);

    $responses = new CrawlerResponses();
    $responses->add($resource);

    expect(new HtaccessBuilder()->build($responses))
        ->toContain('# Query string variants')
        ->toContain('RewriteCond %{QUERY_STRING} ^page\=2$')
        ->toContain('RewriteRule ^blog$ ' . $file . ' [L,QSD]');
});

test('rewrites query string variants of non html resources', function (): void {
    $resource = new Css(new Uri('https://example.com/css/app.css?v=2'));
    $file = FileStore::makeFilePathForStorage($resource);

    $responses = new CrawlerResponses();
    $responses->add($resource);

    expect(new HtaccessBuilder()->build($responses))
        ->toContain('RewriteRule ^css/app\.css$ ' . $file . ' [L,QSD]');
});

test('ignores resources without a query string in the variants section', function (): void {
    $responses = new CrawlerResponses();
    $responses->add(new Html(new Uri('https://example.com/about')));

    expect(new HtaccessBuilder()->build($responses))
        ->not->toContain('Query string variants');
});

test('registers the 404 page as the error document', function (): void {
    $responses = new CrawlerResponses();
    $responses->add(new NotFound(new Uri('https://example.com/does-not-exist')));

    expect(new HtaccessBuilder()->build($responses))
        ->toContain('ErrorDocument 404 /404.html');
});

test('registers the error document only once', function (): void {
    $responses = new CrawlerResponses();
    $responses->add(new NotFound(new Uri('https://example.com/a')));
    $responses->add(new NotFound(new Uri('https://example.com/b')));

    expect(substr_count(new HtaccessBuilder()->build($responses), 'ErrorDocument'))->toBe(1);
});
