<?php

declare(strict_types=1);

namespace RRZE\FAUbox\API;

defined('ABSPATH') || exit;

/**
 * Builds the folder index from the PowerFolder REST API.
 *
 * The builder keeps REST pagination, API response normalization and path
 * conversion separate from IndexService's cache and scheduling concerns.
 */
final class PowerFolderIndexBuilder
{
    private const API_PAGE_SIZE = 1000;

    private FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * Build a flat folder index for the configured root folder.
     *
     * @return array<int, array{name: string, path: string, hasChildren: bool}>|null
     */
    public function build(string $rootFolder): ?array
    {
        $rootFolder = trim($rootFolder, '/');
        if ($rootFolder === '') {
            return $this->buildAllRootFolders();
        }

        $rootParts = explode('/', $rootFolder);
        $topLevelRoot = (string)array_shift($rootParts);
        $relativeRoot = trim(implode('/', $rootParts), '/');
        $folderId = $this->findFolderId($topLevelRoot);

        if ($folderId === null) {
            return null;
        }

        $entries = $this->fetchAllFileEntries($folderId);
        if ($entries === null) {
            return null;
        }

        return $this->buildIndexFromFolderPaths(
            $this->folderPathsFromEntries($entries, $rootFolder, $relativeRoot)
        );
    }


    /**
     * Build an index for all top-level folders when the configured root is "/".
     *
     * Folders whose contents cannot be listed, for example because of missing
     * read permission, are still included as top-level entries.
     *
     * @return array<int, array{name: string, path: string, hasChildren: bool}>|null
     */
    private function buildAllRootFolders(): ?array
    {
        $folders = $this->fetchAllFolders();
        if ($folders === null) {
            return null;
        }

        $folderPaths = [];
        foreach ($folders as $folder) {
            $folderName = $this->getFolderName($folder);
            $folderId = (string)($folder['folderID'] ?? $folder['ID'] ?? '');

            if ($folderName === '' || $folderId === '') {
                continue;
            }

            $folderPaths[] = $folderName;

            $entries = $this->fetchAllFileEntries($folderId);
            if ($entries === null) {
                continue;
            }

            $folderPaths = array_merge(
                $folderPaths,
                $this->folderPathsFromEntries($entries, $folderName)
            );
        }

        return $this->buildIndexFromFolderPaths($folderPaths);
    }


    /**
     * Convert API entries with type "dir" into plugin folder paths.
     *
     * @param array<int, array> $entries
     * @return array<int, string>
     */
    private function folderPathsFromEntries(array $entries, string $rootFolder, string $relativeRoot = ''): array
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
     * Convert clean folder paths into the cached index structure.
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
     * Find the PowerFolder folder ID for a top-level root name.
     */
    private function findFolderId(string $rootName): ?string
    {
        $folders = $this->fetchAllFolders();
        if ($folders === null) {
            return null;
        }

        foreach ($folders as $folder) {
            if (!$this->folderMatchesRoot($folder, $rootName)) {
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
     * @return array<int, array>|null
     */
    private function fetchAllFolders(): ?array
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
     * @return array<int, array>|null
     */
    private function fetchAllFileEntries(string $folderId): ?array
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
    private function folderMatchesRoot(array $folder, string $rootName): bool
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
    private function getFolderName(array $folder): string
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
}
