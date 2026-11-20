<?php

declare(strict_types=1);

use Dopple\CrawlerResponses\Binary;
use Dopple\CrawlerResponses\Css;
use Dopple\CrawlerResponses\Html;
use Dopple\CrawlerResponses\NotFound;
use Dopple\CrawlerResponses\Xml;
use Dopple\FileStore;
use Uri\Rfc3986\Uri;

test('stores a html page without an extension as .html', function (): void {
    $resource = new Html(new Uri('https://example.com/about'));

    expect(FileStore::makeFilePathForStorage($resource))
        ->toBe('about.html');
});

test('does not double the extension of a html page already ending in .html', function (): void {
    $resource = new Html(new Uri('https://example.com/about.html'));

    expect(FileStore::makeFilePathForStorage($resource))
        ->toBe('about.html');
});

test('stores nested html pages', function (): void {
    $resource = new Html(new Uri('https://example.com/a/b'));

    expect(FileStore::makeFilePathForStorage($resource))
        ->toBe('a/b.html');
});

test('stores a trailing slash url as index.html in a directory', function (): void {
    $resource = new Html(new Uri('https://example.com/blog/'));

    expect(FileStore::makeFilePathForStorage($resource))
        ->toBe('blog/index.html');
});

test('stores the site root as index.html', function (): void {
    expect(FileStore::makeFilePathForStorage(new Html(new Uri('https://example.com/'))))
        ->toBe('index.html');

    expect(FileStore::makeFilePathForStorage(new Html(new Uri('https://example.com'))))
        ->toBe('index.html');
});

test('keeps the path of non html resources unchanged', function (): void {
    expect(FileStore::makeFilePathForStorage(new Css(new Uri('https://example.com/css/app.css'))))
        ->toBe('css/app.css');

    expect(FileStore::makeFilePathForStorage(new Binary(new Uri('https://example.com/img/a.png'))))
        ->toBe('img/a.png');
});

test('stores xml without adding an .html extension', function (): void {
    expect(FileStore::makeFilePathForStorage(new Xml(new Uri('https://example.com/sitemap.xml'))))
        ->toBe('sitemap.xml');
});

test('stores not found responses at the not found path', function (): void {
    $resource = new NotFound(new Uri('https://example.com/dopple-404-abc'));

    expect(FileStore::makeFilePathForStorage($resource))
        ->toBe(NotFound::FILE_PATH);
});

test('adds a hash of the query string before the extension', function (): void {
    $resource = new Css(new Uri('https://example.com/css/app.css?v=1'));

    expect(FileStore::makeFilePathForStorage($resource))
        ->toBe('css/app@query=' . substr(hash('sha256', 'v=1'), 0, 16) . '.css');
});

test('adds a query hash to root level html pages without a leading directory', function (): void {
    $resource = new Html(new Uri('https://example.com/blog?page=2'));

    expect(FileStore::makeFilePathForStorage($resource))
        ->toBe('blog@query=' . substr(hash('sha256', 'page=2'), 0, 16) . '.html');
});

test('adds a query hash to nested html pages', function (): void {
    $resource = new Html(new Uri('https://example.com/blog/posts?page=2'));

    expect(FileStore::makeFilePathForStorage($resource))
        ->toBe('blog/posts@query=' . substr(hash('sha256', 'page=2'), 0, 16) . '.html');
});

test('adds a query hash to a trailing slash url', function (): void {
    $resource = new Html(new Uri('https://example.com/blog/?page=2'));

    expect(FileStore::makeFilePathForStorage($resource))
        ->toBe('blog/index@query=' . substr(hash('sha256', 'page=2'), 0, 16) . '.html');
});

test('generates different paths for different queries', function (): void {
    $first = FileStore::makeFilePathForStorage(new Css(new Uri('https://example.com/a/app.css?v=1')));
    $second = FileStore::makeFilePathForStorage(new Css(new Uri('https://example.com/a/app.css?v=2')));

    expect($first)
        ->not->toBe($second);
});

test('removes dot segments so paths cannot escape the root', function (): void {
    $resource = new Html(new Uri('https://example.com/a/../../etc/passwd'));

    expect(FileStore::makeFilePathForStorage($resource))
        ->toBe('etc/passwd.html');
});

test('decodes percent encoded characters', function (): void {
    $resource = new Binary(new Uri('https://example.com/my%20file.png'));

    expect(FileStore::makeFilePathForStorage($resource))
        ->toBe('my file.png');
});
