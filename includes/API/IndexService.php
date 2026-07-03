<?php

declare(strict_types=1);

namespace RRZE\FAUbox\API;

defined('ABSPATH') || exit;

/**
 * Builds and manages a cached folder index for FAUbox.
 *
 * The index is a flat array of folders under the configured
 * root folder. It is stored as a WordPress transient and refreshed manually or via cron.
 */
final class IndexService
{
    private const TRANSIENT_KEY = 'rrze_faubox_folder_index';
    private const STATUS_KEY = 'rrze_faubox_index_status';
    private const COOLDOWN_KEY = 'rrze_faubox_index_cooldown';
    private const COOLDOWN_TTL = 60;
    private const LAST_BUILT_KEY = 'rrze_faubox_index_last_built';
    private const API_PAGE_SIZE = 1000;


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

        $index = $this->collectFoldersFromApi($rootFolder);
        if ($index === null) {
            $index = $this->collectFolders($rootFolder);
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
     * Recursively collect all subfolders under a given path.
     *
     * Returns a flat array. Each entry includes 'name', 'path', and
     * 'hasChildren'.
     *
     * @param string $path WebDAV folder path to traverse.
     * @return array Flat list of folder entries.
     */
    private function collectFolders(string $path, int $depth = 0, int $maxDepth = 7): ?array
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
            $children = $this->collectFolders($folder['path'], $depth + 1, $maxDepth);
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
     * Build the folder index from the PowerFolder REST API.
     *
     * @return array|null Flat list of folder entries, or null if the API path failed.
     */
    private function collectFoldersFromApi(string $rootFolder): ?array
    {
        $rootFolder = trim($rootFolder, '/');
        if ($rootFolder === '') {
            return $this->collectAllRootFoldersFromApi();
        }

        $rootParts = explode('/', $rootFolder);
        $topLevelRoot = (string)array_shift($rootParts);
        $relativeRoot = trim(implode('/', $rootParts), '/');
        $folderId = $this->findApiFolderId($topLevelRoot);

        if ($folderId === null) {
            return null;
        }

        $entries = $this->fetchAllApiFileEntries($folderId);
        if ($entries === null) {
            return null;
        }

        $folderPaths = $this->folderPathsFromApiEntries($entries, $rootFolder, $relativeRoot);

        return $this->buildIndexFromFolderPaths($folderPaths);
    }


    /**
     * Build an index for all top-level folders when the configured root is "/".
     *
     * @return array|null
     */
    private function collectAllRootFoldersFromApi(): ?array
    {
        $folders = $this->fetchAllApiFolders();
        if ($folders === null) {
            return null;
        }

        $folderPaths = [];
        foreach ($folders as $folder) {
            $folderName = $this->getApiFolderName($folder);
            $folderId = (string)($folder['folderID'] ?? $folder['ID'] ?? '');

            if ($folderName === '' || $folderId === '') {
                continue;
            }

            $folderPaths[] = $folderName;

            $entries = $this->fetchAllApiFileEntries($folderId);
            if ($entries === null) {
                continue;
            }

            $folderPaths = array_merge(
                $folderPaths,
                $this->folderPathsFromApiEntries($entries, $folderName)
            );
        }

        return $this->buildIndexFromFolderPaths($folderPaths);
    }


    /**
     * Convert API file entries with type dir into clean plugin folder paths.
     *
     * @return array<int, string>
     */
    private function folderPathsFromApiEntries(array $entries, string $rootFolder, string $relativeRoot = ''): array
    {
        $folderPaths = [];
        foreach ($entries as $entry) {
            $type = strtolower((string)($entry['type'] ?? ''));
            if (!in_array($type, ['dir', 'directory', 'folder'], true)) {
                continue;
            }

            $relativeName = trim((string)($entry['relativeName'] ?? ''), '/');
            if ($relativeName === '') {
                continue;
            }

            if ($relativeRoot !== '') {
                if ($relativeName !== $relativeRoot && !str_starts_with($relativeName, $relativeRoot . '/')) {
                    continue;
                }
                if ($relativeName === $relativeRoot) {
                    continue;
                }
                $relativeName = substr($relativeName, strlen($relativeRoot) + 1);
            }

            $folderPaths[] = trim($rootFolder . '/' . $relativeName, '/');
        }

        return $folderPaths;
    }


    /**
     * Build the cached index format from clean folder paths.
     *
     * @param array<int, string> $folderPaths
     * @return array<int, array{name: string, path: string, hasChildren: bool}>
     */
    private function buildIndexFromFolderPaths(array $folderPaths): array
    {
        $folderPaths = array_values(array_unique($folderPaths));
        sort($folderPaths, SORT_NATURAL | SORT_FLAG_CASE);

        $parentPaths = [];
        foreach ($folderPaths as $path) {
            $parent = dirname($path);
            while ($parent !== '.' && $parent !== '') {
                $parentPaths[$parent] = true;
                $next = dirname($parent);
                if ($next === $parent) {
                    break;
                }
                $parent = $next;
            }
        }

        return array_map(static fn(string $path): array => [
            'name' => basename($path),
            'path' => $path,
            'hasChildren' => isset($parentPaths[$path]),
        ], $folderPaths);
    }


    /**
     * Find the PowerFolder folder ID for the configured top-level root name.
     */
    private function findApiFolderId(string $rootName): ?string
    {
        $folders = $this->fetchAllApiFolders();
        if ($folders === null) {
            return null;
        }

        foreach ($folders as $folder) {
            if (!$this->apiFolderMatchesRoot($folder, $rootName)) {
                continue;
            }

            $folderId = (string)($folder['folderID'] ?? $folder['ID'] ?? '');
            return $folderId !== '' ? $folderId : null;
        }

        return null;
    }


    /**
     * Fetch all PowerFolder folder metadata pages.
     *
     * @return array|null
     */
    private function fetchAllApiFolders(): ?array
    {
        $folders = [];
        $page = 1;

        do {
            $response = $this->fileService->getApiFolders($page, self::API_PAGE_SIZE);
            if ($response === null) {
                return null;
            }

            $folders = array_merge($folders, $this->extractResultSet($response));
            $maxPages = $this->extractMaxPages($response);
            $page++;
        } while ($page <= $maxPages);

        return $folders;
    }


    /**
     * Fetch all recursive file API entries for a PowerFolder folder ID.
     *
     * @return array|null
     */
    private function fetchAllApiFileEntries(string $folderId): ?array
    {
        $entries = [];
        $page = 1;

        do {
            $response = $this->fileService->getApiFiles($folderId, true, $page, self::API_PAGE_SIZE);
            if ($response === null) {
                return null;
            }

            $entries = array_merge($entries, $this->extractResultSet($response));
            $maxPages = $this->extractMaxPages($response);
            $page++;
        } while ($page <= $maxPages);

        return $entries;
    }


    /**
     * Check if an API folder object represents the configured root folder.
     */
    private function apiFolderMatchesRoot(array $folder, string $rootName): bool
    {
        $rootName = trim($rootName, '/');
        $candidates = [
            $folder['name'] ?? '',
            $folder['localizedName'] ?? '',
            $folder['internalName'] ?? '',
        ];

        foreach ($candidates as $candidate) {
            if (strcasecmp(trim((string)$candidate, '/'), $rootName) === 0) {
                return true;
            }
        }

        return false;
    }


    /**
     * Return the API folder name most likely to match WebDAV paths.
     */
    private function getApiFolderName(array $folder): string
    {
        foreach (['localizedName', 'internalName', 'name'] as $key) {
            $name = trim((string)($folder[$key] ?? ''), '/');
            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }


    /**
     * Extract API ResultSet.Result in a tolerant way.
     *
     * @return array<int, array>
     */
    private function extractResultSet(array $response): array
    {
        $result = $response['ResultSet']['Result']
            ?? $response['resultSet']['result']
            ?? $response['Result']
            ?? [];

        return is_array($result) ? array_values(array_filter($result, 'is_array')) : [];
    }


    /**
     * Extract pagination max pages from an API response.
     */
    private function extractMaxPages(array $response): int
    {
        $pageable = $response['ResultSet']['Pageable']
            ?? $response['resultSet']['pageable']
            ?? $response['Pageable']
            ?? [];

        $maxPages = is_array($pageable) ? (int)($pageable['maxPages'] ?? $pageable['maxpages'] ?? 1) : 1;
        return max(1, $maxPages);
    }


    /**
     * Schedule an asynchronous index rebuild via a one-time cron event.
     */
    public function scheduleBuild(): void
    {
        if (!wp_next_scheduled('rrze_faubox_rebuild_index')) {
            wp_schedule_single_event(time(), 'rrze_faubox_rebuild_index');
        }
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
     * Force a full index rebuild, bypassing the transient cache check.
     */
    public function forceRebuild(): void
    {
        delete_transient(self::TRANSIENT_KEY);
        $this->buildIndex();
    }


}
