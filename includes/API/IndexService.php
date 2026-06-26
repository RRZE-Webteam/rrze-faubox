<?php

declare(strict_types=1);

namespace RRZE\FAUbox\API;

defined('ABSPATH') || exit;

/**
 * Builds and manages a cached folder index for FAUbox WebDAV.
 *
 * The index is a flat array of all folders (recursive) under the configured
 * root folder. It is stored as a WordPress transient and refreshed manually or via cron.
 */
final class IndexService
{
    private const TRANSIENT_KEY = 'rrze_faubox_folder_index';
    private const STATUS_KEY     = 'rrze_faubox_index_status';
    private const COOLDOWN_KEY = 'rrze_faubox_index_cooldown';
    private const COOLDOWN_TTL = 60;


    /** Prevents multiple builds within the same request. */
    private static bool $buildScheduled = false;
    private FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * Build the folder index and store it as a transient.
     *
     * Skips if already called in this request (static flag).
     * Clears the index if no root folder is configured.
     */
    public function buildIndex(): void
    {
        if (self::$buildScheduled) {
            return;
        }
        self::$buildScheduled = true;

        @set_time_limit(120);

        $rootFolder = get_option('rrze_faubox_folder', '');
        if (empty($rootFolder)) {
            delete_transient(self::TRANSIENT_KEY);
            return;
        }

        $index = $this->collectFolders($rootFolder);
        $ttl   = (int)get_option('rrze_faubox_index_ttl', 12) * HOUR_IN_SECONDS;

        if ($index === null) {
            set_transient(self::STATUS_KEY, 'error', $ttl);
        } elseif (empty($index)) {
            delete_transient(self::TRANSIENT_KEY);
            set_transient(self::STATUS_KEY, 'empty', $ttl);
        } else {
            set_transient(self::TRANSIENT_KEY, $index, $ttl);
            set_transient(self::STATUS_KEY, 'ok', $ttl);
        }

        set_transient(self::COOLDOWN_KEY, true, self::COOLDOWN_TTL);
    }


    /**
     * Return the cached folder index.
     *
     * @return array Flat array of folder entries, or empty array if not built yet.
     */
    public function getIndex(): array
    {
        $cached = get_transient(self::TRANSIENT_KEY);
        return is_array($cached) ? $cached : [];
    }

    /**
     * Check whether a refresh cooldown is active.
     */
    public function isOnCooldown(): bool
    {
        return (bool)get_transient(self::COOLDOWN_KEY);
    }

    /**
     * Recursively collect all subfolders under a given path.
     *
     * Returns a flat array. Each entry includes 'name', 'path', and
     * 'hasChildren'.
     *
     * @param string $path WebDAV folder path to traverse.
     * @return array Flat list of folder entries.
     */
    private function collectFolders(string $path, int $depth = 0, int $maxDepth = 3): ?array
    {
        if ($depth >= $maxDepth) {
            return [];
        }

        $subFolders = $this->fileService->getSubFolders($path);

        if ($subFolders === null) {
            return null; // WebDAV-Fehler
        }
        if (empty($subFolders)) {
            return []; // Kein Fehler, aber keine Unterordner
        }

        $result = [];
        foreach ($subFolders as $folder) {
            $children = $this->collectFolders($folder['path'], $depth + 1, $maxDepth);
            if ($children === null) {
                return null;
            }
            $result[] = [
                'name'        => $folder['name'],
                'path'        => $folder['path'],
                'hasChildren' => !empty($children),
            ];
            $result = array_merge($result, $children);
        }

        return $result;
    }
}