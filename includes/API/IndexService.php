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
    private const COOLDOWN_KEY  = 'rrze_faubox_index_cooldown';
    private const COOLDOWN_TTL  = 60;


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

        $rootFolder = get_option('rrze_faubox_folder', '');
        if (empty($rootFolder)) {
            delete_transient(self::TRANSIENT_KEY);
            return;
        }

        $index = $this->collectFolders($rootFolder);
        set_transient(self::TRANSIENT_KEY, $index, $this->getTtl());
        set_transient(self::COOLDOWN_KEY, true, self::COOLDOWN_TTL);
    }

    /**
     * Return the cached folder index.
     *
     * @return array Flat array of folder entries, or empty array if
     * not built yet.
     */
    public function getIndex(): array
    {
        $cached = get_transient(self::TRANSIENT_KEY);
        return is_array($cached) ? $cached : [];
    }

    /**
     * Delete the cached folder index.
     */
    public function clearIndex(): void
    {
        delete_transient(self::TRANSIENT_KEY);
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
    private function collectFolders(string $path): array
    {
        $subFolders = $this->fileService->getSubFolders($path);
        if (empty($subFolders)) {
            return [];
        }

        $result = [];
        foreach ($subFolders as $folder) {
            $children = $this->collectFolders($folder['path']);
            $result[] = [
                'name' => $folder['name'],
                'path' => $folder['path'],
                'hasChildren' => !empty($children),
            ];
            $result = array_merge($result, $children);
        }

        return $result;
    }

    /**
     * Retrieve the configured TTL in seconds.
     *
     * Reads 'rrze_faubox_index_ttl' (hours). Defaults to 12h.
     */
    private function getTtl(): int
    {
        $ttlHours = (int)get_option('rrze_faubox_index_ttl', 12);
        $allowed = [1, 6, 12, 24];
        if (!in_array($ttlHours, $allowed, true)) {
            $ttlHours = 12;
        }
        return $ttlHours * HOUR_IN_SECONDS;
    }
}
