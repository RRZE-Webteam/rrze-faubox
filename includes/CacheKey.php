<?php

declare(strict_types=1);

namespace RRZE\FAUbox;

/**
 * Builds a stable cache key for FAUbox file lists.
 */
final class CacheKey
{
    /**
     * Builds a cache key from arguments.
     *
     * @param array $args
     * @return string
     */
    public static function forFiles(array $args): string
    {
        $parts = [
            'v1', // Versionsnummer, bei Änderungen hochsetzen
            'index=' . strtolower(trim((string) ($args['index'] ?? ''))),
            'filetype=' . strtolower(trim((string) ($args['filetype'] ?? ''))),
            'orderby=' . strtolower(trim((string) ($args['orderby'] ?? 'name'))),
            'sort=' . strtolower(trim((string) ($args['sort'] ?? 'asc'))),
        ];

        return 'rrze_faubox:' . implode(':', $parts);
    }
}
