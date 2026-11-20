<?php

declare(strict_types=1);

namespace Dopple;

use Dom\HTMLDocument;
use Dopple\CrawlerResponses\Css;
use Dopple\CrawlerResponses\CrawlerResponse;
use Dopple\CrawlerResponses\Html;
use Dopple\CrawlerResponses\Js;
use Dopple\CrawlerResponses\NotFound;
use Uri\InvalidUriException;
use Uri\Rfc3986\Uri;

final class UrlExtractor
{
    public static function fromResource(CrawlerResponse $resource, string $body, Uri $baseUrl): array
    {
        if ($resource instanceof Html || $resource instanceof NotFound) {
            return self::fromHtml($body, $baseUrl);
        }

        if ($resource instanceof Css) {
            return self::fromCss($body, $baseUrl);
        }

        if ($resource instanceof Js) {
            return self::fromJs($body, $baseUrl);
        }

        return [];
    }

    public static function fromHtml(string $html, Uri $baseUrl): array
    {
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        $baseHref = $document->querySelector('base[href]')?->getAttribute('href');

        if ($baseHref !== null) {
            $baseUrl = $baseUrl->resolve($baseHref);
        }

        return self::resolve(self::referencesFromDocument($document, $baseUrl), $baseUrl);
    }

    /**
     * @return list<string>
     */
    private static function referencesFromDocument(HTMLDocument $document, Uri $baseUrl): array
    {
        return [
            ...self::urlsFromAttributes($document),
            ...self::urlsFromSrcsets($document),
            ...self::urlsFromMetaTags($document),
            ...self::urlsFromInlineStyles($document, $baseUrl),
            ...self::urlsFromSrcdocs($document, $baseUrl),
        ];
    }

    public static function fromCss(string $css, Uri $baseUrl): array
    {
        preg_match_all('/url\(\s*([\'"]?)(.*?)\1\s*\)/i', $css, $urls);
        preg_match_all('/@import\s+([\'"])(.*?)\1/i', $css, $imports);
        preg_match_all('/image-set\(((?:[^()]|\([^)]*\))*)\)/i', $css, $imageSets);
        preg_match_all('/([\'"])(.*?)\1/', implode(' ', $imageSets[1]), $imageSetUrls);

        return self::resolve(
            [
                ...$urls[2],
                ...$imports[2],
                ...$imageSetUrls[2],
            ],
            $baseUrl,
        );
    }

    /**
     * Path-like string literals and module specifiers. Literals need a known file extension,
     * otherwise ordinary text would be picked up. Module specifiers do not.
     */
    public static function fromJs(string $js, Uri $baseUrl): array
    {
        $extensions = 'js|mjs|css|json|wasm|html?|svg|png|jpe?g|gif|webp|avif|ico|woff2?|ttf|otf|eot|mp4|webm|mp3|ogg|txt|xml';

        preg_match_all(
            '#([\'"`])((?:/(?!/)|\.{1,2}/|(?:https?:)?//)[^\'"`\s\\\\$]*?\.(?:' . $extensions . ')(?:[?\#][^\'"`\s\\\\$]*)?)\1#i',
            $js,
            $literals,
        );
        preg_match_all(
            '#(?:\bfrom\s*|\bimport\s*\(?\s*)([\'"`])((?:\.{1,2})?/[^\'"`\s\\\\$]*)\1#',
            $js,
            $specifiers,
        );

        return self::resolve(
            [
                ...$literals[2],
                ...$specifiers[2],
            ],
            $baseUrl,
        );
    }

    private static function resolve(array $references, Uri $baseUrl): array
    {
        return $references
            |> array_map(trim(...), ?)
            |> array_map(
                function (string $reference) use ($baseUrl): ?string {
                    try {
                        $url = $baseUrl
                            ->resolve($reference)
                            ->withFragment(null);

                        if (strtolower((string) $url->getScheme()) === 'data') {
                            return null;
                        }

                        return $url->toRawString();
                    } catch (InvalidUriException) {
                        return null;
                    }
                },
                ?
            )
            |> array_filter(?, fn (?string $url): bool => $url !== null)
            |> array_unique(?)
            |> array_map(fn (string $url): Uri => new Uri($url), ?)
            |> array_values(?);
    }

    private static function urlsFromAttributes(HTMLDocument $document): array
    {
        $references = [];

        foreach (['href', 'src', 'poster', 'data-src'] as $attribute) {
            foreach ($document->querySelectorAll('[' . $attribute . ']') as $element) {
                $references[] = $element->getAttribute($attribute);
            }
        }

        foreach ($document->querySelectorAll('object[data]') as $element) {
            $references[] = $element->getAttribute('data');
        }

        return $references;
    }

    private static function urlsFromSrcsets(HTMLDocument $document): array
    {
        $references = [];

        foreach (['srcset', 'data-srcset'] as $attribute) {
            foreach ($document->querySelectorAll('[' . $attribute . ']') as $element) {
                foreach (explode(',', $element->getAttribute($attribute)) as $candidate) {
                    $url = preg_split('/\s+/', trim($candidate))[0] ?? '';

                    if ($url !== '') {
                        $references[] = $url;
                    }
                }
            }
        }

        return $references;
    }

    private static function urlsFromMetaTags(HTMLDocument $document): array
    {
        $names = [
            'og:image',
            'og:image:url',
            'og:image:secure_url',
            'og:video',
            'og:video:url',
            'og:video:secure_url',
            'og:audio',
            'og:audio:url',
            'og:audio:secure_url',
            'twitter:image',
            'twitter:image:src',
            'twitter:player:stream',
            'msapplication-TileImage',
            'msapplication-config',
        ];

        $selectors = [];

        foreach ($names as $name) {
            $selectors[] = 'meta[property="' . $name . '"]';
            $selectors[] = 'meta[name="' . $name . '"]';
        }

        $references = [];

        foreach ($document->querySelectorAll(implode(', ', $selectors)) as $element) {
            $references[] = $element->getAttribute('content') ?? '';
        }

        return $references;
    }

    private static function urlsFromSrcdocs(HTMLDocument $document, Uri $baseUrl): array
    {
        $references = [];

        foreach ($document->querySelectorAll('iframe[srcdoc]') as $element) {
            $srcdoc = HTMLDocument::createFromString($element->getAttribute('srcdoc'), LIBXML_NOERROR);

            $references = [...$references, ...self::referencesFromDocument($srcdoc, $baseUrl)];
        }

        return $references;
    }

    private static function urlsFromInlineStyles(HTMLDocument $document, Uri $baseUrl): array
    {
        $styles = [];

        foreach ($document->querySelectorAll('style') as $element) {
            $styles[] = $element->textContent;
        }

        foreach ($document->querySelectorAll('[style]') as $element) {
            $styles[] = $element->getAttribute('style');
        }

        return $styles
            |> array_map(fn (string $style): array => self::fromCss($style, $baseUrl), ?)
            |> (fn (array $urlsPerStyle): array => array_merge(...$urlsPerStyle))
            |> array_map(fn (Uri $url): string => $url->toRawString(), ?);
    }
}
