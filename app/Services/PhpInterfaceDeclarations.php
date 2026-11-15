<?php

declare(strict_types=1);

namespace App\Services;

use ParseError;
use PhpToken;

final class PhpInterfaceDeclarations
{
    public static function isOnlyInterfaces(string $path): bool
    {
        $source = @file_get_contents($path);
        if ($source === false) {
            return false;
        }

        try {
            $tokens = PhpToken::tokenize($source, TOKEN_PARSE);
        } catch (ParseError) {
            return false;
        }

        $foundInterface = false;
        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if ($token->isIgnorable() || $token->is([T_CLOSE_TAG, ';', '}'])) {
                continue;
            }

            // TOKEN_PARSE validates the declarations. A block-form declare can
            // contain code, so only accept a semicolon-terminated directive.
            if ($token->is([T_DECLARE, T_NAMESPACE, T_USE])) {
                $index = self::skipHeader($tokens, $index + 1, $token->id);
            } elseif ($token->is(T_ATTRIBUTE)) {
                $index = self::skipBalanced($tokens, $index + 1, '[', ']');
            } elseif ($token->is(T_INTERFACE)) {
                $index = self::skipHeader($tokens, $index + 1, T_INTERFACE);
                $index = $index === null ? null : self::skipBalanced($tokens, $index + 1, '{', '}');
                $foundInterface = true;
            } else {
                return false;
            }

            if ($index === null) {
                return false;
            }
        }

        return $foundInterface;
    }

    /** @param list<PhpToken> $tokens */
    private static function skipHeader(array $tokens, int $start, int $kind): ?int
    {
        for ($index = $start; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if ($token->is(';') || ($token->is('{') && in_array($kind, [T_NAMESPACE, T_INTERFACE], true))) {
                return $index;
            }

            if ($kind === T_DECLARE && $token->is(['{', ':'])) {
                return null;
            }
        }

        return null;
    }

    /** @param list<PhpToken> $tokens */
    private static function skipBalanced(array $tokens, int $start, string $open, string $close): ?int
    {
        $depth = 1;
        for ($index = $start; $index < count($tokens); $index++) {
            if ($tokens[$index]->is($open)) {
                $depth++;
            } elseif ($tokens[$index]->is($close) && --$depth === 0) {
                return $index;
            }
        }

        return null;
    }
}
