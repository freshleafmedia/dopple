<?php

declare(strict_types=1);

namespace Dopple;

use Uri\Rfc3986\Uri;

final class UrlRewriter
{
    public static function rewriteAbsoluteUrlsToRootSlash(string $body, Uri $origin): string
    {
        $port = $origin->getPort() === null ? '' : ':' . $origin->getPort();
        $originPattern = '(?:https?:)?//' . preg_quote((string) $origin->getHost(), '#') . $port;

        return $body
            |> preg_replace('#' . $originPattern . '(?=/)#i', '', ?)
            |> preg_replace('#' . $originPattern . '(?![\w.:\-])#i', '/', ?);
    }
}
