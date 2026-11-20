<?php

declare(strict_types=1);

use Uri\Rfc3986\Uri;

/**
 * @param list<Uri> $urls
 * @return list<string>
 */
function urlStrings(array $urls): array
{
    return $urls
        |> array_map(fn (Uri $url): string => $url->toRawString(), ?);
}
