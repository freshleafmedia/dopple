<?php

declare(strict_types=1);

use Dopple\UrlRewriter;
use Uri\Rfc3986\Uri;

test('strips the origin from absolute urls with a path', function (): void {
    $body = '<a href="https://example.com/about">';

    expect(UrlRewriter::rewriteAbsoluteUrlsToRootSlash($body, new Uri('https://example.com')))
        ->toBe('<a href="/about">');
});

test('handles http urls', function (): void {
    $body = 'http://example.com/about';

    expect(UrlRewriter::rewriteAbsoluteUrlsToRootSlash($body, new Uri('https://example.com')))
        ->toBe('/about');
});

test('handles protocol relative urls', function (): void {
    $body = '<img src="//example.com/a.png">';

    expect(UrlRewriter::rewriteAbsoluteUrlsToRootSlash($body, new Uri('https://example.com')))
        ->toBe('<img src="/a.png">');
});

test('turns a bare origin into a root slash', function (): void {
    $body = '<a href="https://example.com">';

    expect(UrlRewriter::rewriteAbsoluteUrlsToRootSlash($body, new Uri('https://example.com')))
        ->toBe('<a href="/">');
});

test('is case insensitive on the host', function (): void {
    $body = 'https://EXAMPLE.com/x';

    expect(UrlRewriter::rewriteAbsoluteUrlsToRootSlash($body, new Uri('https://example.com')))
        ->toBe('/x');
});

test('keeps query strings and fragments', function (): void {
    $body = 'https://example.com/a?b=1#c';

    expect(UrlRewriter::rewriteAbsoluteUrlsToRootSlash($body, new Uri('https://example.com')))
        ->toBe('/a?b=1#c');
});

test('rewrites a bare origin followed by a query or fragment', function (): void {
    $body = 'https://example.com?a=1';

    expect(UrlRewriter::rewriteAbsoluteUrlsToRootSlash($body, new Uri('https://example.com')))
        ->toBe('/?a=1');
});

test('rewrites every occurrence', function (): void {
    $body = 'https://example.com/a https://example.com/b';

    expect(UrlRewriter::rewriteAbsoluteUrlsToRootSlash($body, new Uri('https://example.com')))
        ->toBe('/a /b');
});

test('leaves other hosts alone', function (): void {
    $body = '<a href="https://other.com/a">';

    expect(UrlRewriter::rewriteAbsoluteUrlsToRootSlash($body, new Uri('https://example.com')))
        ->toBe($body);
});

test('leaves hosts that merely start with the origin host alone', function (): void {
    $body = 'https://example.com.evil.net/a';

    expect(UrlRewriter::rewriteAbsoluteUrlsToRootSlash($body, new Uri('https://example.com')))
        ->toBe($body);

    $body = 'https://example.community';

    expect(UrlRewriter::rewriteAbsoluteUrlsToRootSlash($body, new Uri('https://example.com')))
        ->toBe($body);

    $body = 'https://example.com-shop.net';

    expect(UrlRewriter::rewriteAbsoluteUrlsToRootSlash($body, new Uri('https://example.com')))
        ->toBe($body);
});

test('does not treat dots in the host as wildcards', function (): void {
    $body = 'https://exampleXcom/a';

    expect(UrlRewriter::rewriteAbsoluteUrlsToRootSlash($body, new Uri('https://example.com')))
        ->toBe($body);
});

test('respects the origin port', function (): void {
    $body = 'http://localhost:8080/a';

    expect(UrlRewriter::rewriteAbsoluteUrlsToRootSlash($body, new Uri('http://localhost:8080')))
        ->toBe('/a');

    $body = 'http://localhost:8080';

    expect(UrlRewriter::rewriteAbsoluteUrlsToRootSlash($body, new Uri('http://localhost:8080')))
        ->toBe('/');
});

test('does not rewrite a different port', function (): void {
    $body = 'http://localhost:9000/a';

    expect(UrlRewriter::rewriteAbsoluteUrlsToRootSlash($body, new Uri('http://localhost:8080')))
        ->toBe($body);
});

test('leaves a body without urls unchanged', function (): void {
    $body = 'hello world';

    expect(UrlRewriter::rewriteAbsoluteUrlsToRootSlash($body, new Uri('https://example.com')))
        ->toBe($body);
});
