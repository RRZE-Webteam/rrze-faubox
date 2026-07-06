<?php

declare(strict_types=1);

namespace RRZE\FAUbox\API;

defined('ABSPATH') || exit;

/**
 * Manages the cached folder index for FAUbox.
 *
 * IndexService owns cache state, scheduling and editor lookup helpers. Index
 * data is built through the PowerFolder REST API first and falls back to the
 * older WebDAV traversal if the REST API cannot provide an index.
 */
final class IndexService
{
    private const TRANSIENT_KEY = 'rrze_faubox_folder_index';
    private const STATUS_KEY = 'rrze_faubox_index_status';
    private const COOLDOWN_KEY = 'rrze_faubox_index_cooldown';
    private const COOLDOWN_TTL =  5 * MINUTE_IN_SECONDS;
    private const LAST_BUILT_KEY = 'rrze_faubox_index_last_built';


    /** Prevents multiple builds within the same request. */
    private static bool $buildScheduled = false;
    private FileService $fileService;
    private PowerFolderIndexBuilder $apiIndexBuilder;

    public function __construct(FileService $fileService, PowerFolderIndexBuilder $apiIndexBuilder)
    {
        $this->fileService = $fileService;
        $this->apiIndexBuilder = $apiIndexBuilder;
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

        // Skip rebuild if the index transient is still valid.
        if (get_transient(self::TRANSIENT_KEY) !== false) {
            return;
        }

        @set_time_limit(120);

        $rootFolder = get_option('rrze_faubox_folder', '');
        if (empty($rootFolder)) {
            delete_transient(self::TRANSIENT_KEY);
            return;
        }

        $index = $this->apiIndexBuilder->build($rootFolder);
        if ($index === null) {
            $index = $this->collectFoldersFromWebdav($rootFolder);
        }
        $ttl = (int)get_option('rrze_faubox_index_ttl', 12) * HOUR_IN_SECONDS;

        if ($index === null) {
            set_transient(self::STATUS_KEY, 'error', $ttl);
        } elseif (empty($index)) {
            delete_transient(self::TRANSIENT_KEY);
            set_transient(self::STATUS_KEY, 'empty', $ttl);
        } else {
            set_transient(self::TRANSIENT_KEY, $index, $ttl);
            set_transient(self::STATUS_KEY, 'ok', $ttl);
            update_option(self::LAST_BUILT_KEY, ['time' => time(), 'count' => count($index)]);
        }
    }

    /**
     * Activate the refresh cooldown to prevent rapid consecutive rebuilds.
     */
    public function setCooldown(): void
    {
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
     * Return only the direct children of the root folder from the index, sorted by name.
     *
     * @return array Filtered and sorted folder entries.
     */
    public function getDirectChildren(): array
    {
        $index = $this->getIndex();
        if (empty($index)) {
            return [];
        }

        $minDepth = min(array_map(
            fn(array $entry): int => substr_count($entry['path'], '/'),
            $index
        ));

        $filtered = array_values(array_filter(
            $index,
            fn(array $entry): bool => substr_count($entry['path'], '/') === $minDepth
        ));

        usort($filtered, fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $filtered;
    }


    /**
     * Return direct children of a given folder path from the cached index.
     *
     * @return array Filtered and sorted folder entries.
     */
    public function getChildrenOfPath(string $parentPath): array
    {
        $parentPath = trim($parentPath, '/');
        if ($parentPath === '') {
            return $this->getDirectChildren();
        }

        $parentDepth = substr_count($parentPath, '/');
        $filtered = array_values(array_filter(
            $this->getIndex(),
            static function (array $entry) use ($parentPath, $parentDepth): bool {
                $path = trim((string)($entry['path'] ?? ''), '/');
                return str_starts_with($path, $parentPath . '/')
                    && substr_count($path, '/') === $parentDepth + 1;
            }
        ));

        usort($filtered, fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $filtered;
    }

    /**
     * Enrich a list of live folders with 'hasChildren' data from the cached index.
     *
     * @param array $folders Live subfolder entries from FileService::getSubFolders().
     * @return array Same folders, each with an added 'hasChildren' key.
     */
    public function enrichWithHasChildren(array $folders): array
    {
        $indexByPath = array_column($this->getIndex(), null, 'path');

        return array_map(function (array $folder) use ($indexByPath): array {
            $folder['hasChildren'] = isset($indexByPath[$folder['path']])
                ? (bool)$indexByPath[$folder['path']]['hasChildren']
                : false;
            return $folder;
        }, $folders);
    }


    /**
     * Recursively collect all subfolders under a WebDAV path.
     *
     * Fallback for installations where the PowerFolder REST API cannot provide
     * a usable recursive directory listing.
     *
     * @param string $path WebDAV folder path to traverse.
     * @return array Flat list of folder entries.
     */
    private function collectFoldersFromWebdav(string $path, int $depth = 0, int $maxDepth = 7): ?array
    {
        if ($depth >= $maxDepth) {
            return [];
        }

        $subFolders = $this->fileService->getSubFolders($path);

        if ($subFolders === null) {
            return null;
        }
        if (empty($subFolders)) {
            return [];
        }

        $result = [];
        foreach ($subFolders as $folder) {
            $children = $this->collectFoldersFromWebdav($folder['path'], $depth + 1, $maxDepth);
            if ($children === null) {
                return null;
            }
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
     * Schedule an asynchronous index rebuild via a one-time cron event.
     */
    public function scheduleBuild(): void
    {
        if (get_transient(self::TRANSIENT_KEY) !== false) {
            return;
        }

        set_transient(self::STATUS_KEY, 'scheduled', self::COOLDOWN_TTL);

        if (!wp_next_scheduled('rrze_faubox_rebuild_index_once')) {
            wp_schedule_single_event(time(), 'rrze_faubox_rebuild_index_once');
        }
    }

    /**
     * Check whether an index build has been scheduled but not completed yet.
     */
    public function isBuildScheduled(): bool
    {
        return get_transient(self::STATUS_KEY) === 'scheduled';
    }

    /**
     * Return metadata about the last successful index build.
     *
     * @return array{time: int, count: int}|null
     */
    public function getLastBuiltInfo(): ?array
    {
        $info = get_option(self::LAST_BUILT_KEY, null);
        return is_array($info) ? $info : null;
    }


    /**
     * Delete the cached index and schedule an async rebuild.
     */
    public function scheduleForceRebuild(): void
    {
        delete_transient(self::TRANSIENT_KEY);
        $this->scheduleBuild();
    }


}
