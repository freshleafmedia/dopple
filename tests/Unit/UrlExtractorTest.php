<?php

declare(strict_types=1);

use Dopple\CrawlerResponses\Binary;
use Dopple\CrawlerResponses\Css;
use Dopple\CrawlerResponses\Html;
use Dopple\CrawlerResponses\Js;
use Dopple\CrawlerResponses\NotFound;
use Dopple\UrlExtractor;
use Uri\Rfc3986\Uri;

test('extracts href, src, poster, data-src and object data from html', function (): void {
    $html = <<<'HTML'
        <a href="/a">a</a>
        <img src="/b.png" data-src="/c.png">
        <video poster="/d.jpg"></video>
        <object data="/e.swf"></object>
        HTML;

    expect(urlStrings(UrlExtractor::fromHtml($html, new Uri('https://example.com/dir/page.html'))))
        ->toBe([
            'https://example.com/a',
            'https://example.com/b.png',
            'https://example.com/d.jpg',
            'https://example.com/c.png',
            'https://example.com/e.swf',
        ]);
});

test('resolves relative html references against the base url', function (): void {
    $urls = UrlExtractor::fromHtml('<a href="x.html"></a><a href="../y.html"></a>', new Uri('https://example.com/dir/page.html'));

    expect(urlStrings($urls))
        ->toBe([
            'https://example.com/dir/x.html',
            'https://example.com/y.html',
        ]);
});

test('honours the html base tag', function (): void {
    $html = '<base href="/other/"><a href="x.html"></a>';

    expect(urlStrings(UrlExtractor::fromHtml($html, new Uri('https://example.com/dir/page.html'))))
        ->toBe([
            'https://example.com/other/',
            'https://example.com/other/x.html',
        ]);
});

test('strips fragments and de-duplicates html references', function (): void {
    $html = '<a href="/a#one"></a><a href="/a#two"></a><a href="/a"></a>';

    expect(urlStrings(UrlExtractor::fromHtml($html, new Uri('https://example.com/dir/page.html'))))
        ->toBe(['https://example.com/a']);
});

test('trims whitespace around html references', function (): void {
    expect(urlStrings(UrlExtractor::fromHtml('<a href="  /a  "></a>', new Uri('https://example.com/dir/page.html'))))
        ->toBe(['https://example.com/a']);
});

test('keeps absolute external urls in html', function (): void {
    expect(urlStrings(UrlExtractor::fromHtml('<a href="https://other.com/x"></a>', new Uri('https://example.com/dir/page.html'))))
        ->toBe(['https://other.com/x']);
});

test('extracts srcset and data-srcset candidates', function (): void {
    $html = '<img srcset="/a.png 1x, /b.png 2x" data-srcset="/c.png 480w,/d.png 800w">';

    expect(urlStrings(UrlExtractor::fromHtml($html, new Uri('https://example.com/dir/page.html'))))
        ->toBe([
            'https://example.com/a.png',
            'https://example.com/b.png',
            'https://example.com/c.png',
            'https://example.com/d.png',
        ]);
});

test('extracts meta tag urls by property and name', function (): void {
    $html = <<<'HTML'
        <meta property="og:image" content="/og.png">
        <meta name="twitter:image" content="/tw.png">
        <meta name="msapplication-TileImage" content="/tile.png">
        <meta name="description" content="/not-a-url">
        HTML;

    $urls = urlStrings(UrlExtractor::fromHtml($html, new Uri('https://example.com/dir/page.html')));

    expect($urls)
        ->toContain(
            'https://example.com/og.png',
            'https://example.com/tw.png',
            'https://example.com/tile.png',
        )
        ->not->toContain('https://example.com/not-a-url');
});

test('extracts urls from style tags and style attributes', function (): void {
    $html = <<<'HTML'
        <style>body { background: url(/bg.png); }</style>
        <div style="background-image: url('/inline.png')"></div>
        HTML;

    expect(urlStrings(UrlExtractor::fromHtml($html, new Uri('https://example.com/dir/page.html'))))
        ->toBe([
            'https://example.com/bg.png',
            'https://example.com/inline.png',
        ]);
});

test('extracts urls from iframe srcdoc', function (): void {
    $html = '<iframe srcdoc="&lt;img src=&quot;/inner.png&quot;&gt;"></iframe>';

    expect(urlStrings(UrlExtractor::fromHtml($html, new Uri('https://example.com/dir/page.html'))))
        ->toContain('https://example.com/inner.png');
});

test('returns nothing for html without references', function (): void {
    expect(UrlExtractor::fromHtml('<p>hello</p>', new Uri('https://example.com/dir/page.html')))
        ->toBe([]);
});

test('returns Uri instances from html', function (): void {
    $urls = UrlExtractor::fromHtml('<a href="/a"></a>', new Uri('https://example.com/dir/page.html'));

    expect($urls[0])
        ->toBeInstanceOf(Uri::class);
});

test('extracts css url() with and without quotes', function (): void {
    $css = 'a { background: url(/a.png); } b { background: url("/b.png"); } c { background: url( \'/c.png\' ); }';

    expect(urlStrings(UrlExtractor::fromCss($css, new Uri('https://example.com/dir/page.html'))))
        ->toBe([
            'https://example.com/a.png',
            'https://example.com/b.png',
            'https://example.com/c.png',
        ]);
});

test('extracts css @import strings', function (): void {
    expect(urlStrings(UrlExtractor::fromCss('@import "/x.css"; @import \'y.css\';', new Uri('https://example.com/dir/page.html'))))
        ->toBe([
            'https://example.com/x.css',
            'https://example.com/dir/y.css',
        ]);
});

test('extracts css image-set urls', function (): void {
    $css = 'a { background: image-set("/a.png" 1x, "/b.png" 2x); }';

    expect(urlStrings(UrlExtractor::fromCss($css, new Uri('https://example.com/dir/page.html'))))
        ->toBe([
            'https://example.com/a.png',
            'https://example.com/b.png',
        ]);
});

test('extracts single quoted css image-set urls', function (): void {
    $css = 'a { background: image-set(\'/a.png\' 1x, \'/b.png\' 2x); }';

    expect(urlStrings(UrlExtractor::fromCss($css, new Uri('https://example.com/dir/page.html'))))
        ->toBe([
            'https://example.com/a.png',
            'https://example.com/b.png',
        ]);
});

test('ignores data urls', function (): void {
    $baseUrl = new Uri('https://example.com/dir/page.html');
    $html = '<img src="data:image/png;base64,AAAA"><a href="/ok"></a>';
    $css = 'a { background: url(data:image/png;base64,AAAA); } b { background: url(/ok.png); }';

    expect(urlStrings(UrlExtractor::fromHtml($html, $baseUrl)))
        ->toBe(['https://example.com/ok']);

    expect(urlStrings(UrlExtractor::fromCss($css, $baseUrl)))
        ->toBe(['https://example.com/ok.png']);
});

test('resolves css urls relative to the css file url', function (): void {
    $css = 'a { background: url(../img/a.png); }';

    expect(urlStrings(UrlExtractor::fromCss($css, new Uri('https://example.com/css/site.css'))))
        ->toBe(['https://example.com/img/a.png']);
});

test('de-duplicates css urls', function (): void {
    $css = 'a { background: url(/a.png); } b { background: url(/a.png); }';

    expect(UrlExtractor::fromCss($css, new Uri('https://example.com/dir/page.html')))
        ->toHaveCount(1);
});

test('extracts js path literals with a known extension', function (): void {
    $js = 'load("/assets/app.js"); load(\'./img/logo.svg\'); load(`../font.woff2`);';

    expect(urlStrings(UrlExtractor::fromJs($js, new Uri('https://example.com/js/main.js'))))
        ->toBe([
            'https://example.com/assets/app.js',
            'https://example.com/js/img/logo.svg',
            'https://example.com/font.woff2',
        ]);
});

test('extracts absolute and protocol relative js literals', function (): void {
    $js = 'a("https://cdn.example.com/x.css"); b("//cdn.example.com/y.js");';

    expect(urlStrings(UrlExtractor::fromJs($js, new Uri('https://example.com/dir/page.html'))))
        ->toBe([
            'https://cdn.example.com/x.css',
            'https://cdn.example.com/y.js',
        ]);
});

test('keeps query strings on js literals', function (): void {
    expect(urlStrings(UrlExtractor::fromJs('a("/x.js?v=3");', new Uri('https://example.com/dir/page.html'))))
        ->toBe(['https://example.com/x.js?v=3']);
});

test('ignores js literals without a known extension', function (): void {
    expect(UrlExtractor::fromJs('a("/just/a/path"); b("hello.js"); c("/file.unknown");', new Uri('https://example.com/dir/page.html')))
        ->toBe([]);
});

test('extracts js module specifiers without extensions', function (): void {
    $js = 'import x from "./mod"; import("/lazy/chunk"); export { y } from "../other";';

    expect(urlStrings(UrlExtractor::fromJs($js, new Uri('https://example.com/js/main.js'))))
        ->toBe([
            'https://example.com/js/mod',
            'https://example.com/lazy/chunk',
            'https://example.com/other',
        ]);
});

test('ignores bare js module specifiers', function (): void {
    expect(UrlExtractor::fromJs('import x from "lodash";', new Uri('https://example.com/dir/page.html')))
        ->toBe([]);
});

test('ignores js template literals with interpolation', function (): void {
    expect(UrlExtractor::fromJs('a(`/img/${name}.png`);', new Uri('https://example.com/dir/page.html')))
        ->toBe([]);
});

test('uses the html extractor for Html and NotFound resources', function (): void {
    $url = new Uri('https://example.com/');
    $body = '<a href="/a"></a>';

    expect(urlStrings(UrlExtractor::fromResource(new Html($url), $body, $url)))
        ->toBe(['https://example.com/a']);

    expect(urlStrings(UrlExtractor::fromResource(new NotFound($url), $body, $url)))
        ->toBe(['https://example.com/a']);
});

test('uses the css extractor for Css resources', function (): void {
    $url = new Uri('https://example.com/');
    $urls = UrlExtractor::fromResource(new Css($url), 'a { background: url(/a.png); }', $url);

    expect(urlStrings($urls))
        ->toBe(['https://example.com/a.png']);
});

test('uses the js extractor for Js resources', function (): void {
    $url = new Uri('https://example.com/');
    $urls = UrlExtractor::fromResource(new Js($url), 'a("/a.js");', $url);

    expect(urlStrings($urls))
        ->toBe(['https://example.com/a.js']);
});

test('returns nothing for other resources', function (): void {
    $url = new Uri('https://example.com/');

    expect(UrlExtractor::fromResource(new Binary($url), '<a href="/a"></a>', $url))
        ->toBe([]);
});
