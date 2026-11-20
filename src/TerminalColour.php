<?php

declare(strict_types=1);

namespace Dopple;

enum TerminalColour: string
{
    case Red = '31';
    case Green = '32';
    case Yellow = '33';

    public function apply(string $text): string
    {
        return "\e[" . $this->value . 'm' . $text . "\e[0m";
    }
}
