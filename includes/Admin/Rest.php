<?php

declare(strict_types=1);

namespace RRZE\FAUbox\Admin;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;
use RRZE\FAUbox\API;

/**
 * REST endpoints for Gutenberg FAUbox block.
 *
 * Works with public-share FAUbox API (/wapi/filelink).
 * Provides:
 *  - /root-folders → list root-level folders
 *  - /folders      → list subfolders of one selected folder
 */
class Rest
{
    /**
     * Register REST routes on init.
     */
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    /**
     * Defines REST routes.
     */
    public static function registerRoutes(): void
    {
        register_rest_route('rrze-faubox/v1', '/folders', [
            'methods' => 'GET',
            'callback' => [self::class, 'getFolders'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // NEW: Files endpoint for SSR
        register_rest_route('rrze-faubox/v1', '/files', [
            'methods' => 'GET',
            'callback' => [self::class, 'getFiles'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }


    /**
     * Returns folders (root or inside selected folder)
     */
    public static function getFolders(WP_REST_Request $request): WP_REST_Response
    {
        $validated = self::validateShareRequest($request);
        if ($validated === false) {
            return new WP_REST_Response([], 200);
        }

        $folderName = trim((string)$request->get_param('folder'));

        $items = self::loadFolderItems(
            $validated['resourceId'],
            $validated['shareId'],
            $folderName
        );

        if (!is_array($items)) {
            return new WP_REST_Response([], 200);
        }

        $result = self::buildInitialResult($items);
        $result = self::buildFolderTree($result, $validated, $items);

        return new WP_REST_Response($result, 200);
    }


    /**
     * Validates the share link and resolves Share-ID + Resource-ID.
     * Returns false on invalid input or an array with both IDs.
     */
    private static function validateShareRequest(WP_REST_Request $request): array|false
    {
        $shareLink = trim((string)$request->get_param('sharelink'));

        if ($shareLink === '') {
            return false;
        }

        $shareId = API::resolveShareIdFromUrl($shareLink);
        if (!$shareId) {
            return false;
        }

        $resourceId = API::resolveResourceId($shareId);
        if (!$resourceId) {
            return false;
        }

        return [
            'shareId' => $shareId,
            'resourceId' => $resourceId
        ];
    }

    /**
     * Loads either root items or subfolder items depending on folderName.
     * Always returns an array (empty on failure).
     */
    private static function loadFolderItems(string $resourceId, string $shareId, string $folderName): array
    {
        return ($folderName === '')
            ? (API::fetchRoot($resourceId, $shareId) ?? [])
            : (API::fetchSubfolder($resourceId, $shareId, $folderName) ?? []);
    }

    /**
     * Creates the base result structure with root folders and root files.
     * Used as the starting point for building the folder tree.
     */
    private static function buildInitialResult(array $items): array
    {
        return [
            'folders' => [],
            'files' => [],
            'rootFolders' => API::filterFolders($items),
            'rootFiles' => API::filterFiles($items)
        ];
    }

    /**
     * Adds file entries to the result array with label/value formatting.
     * Used for both root files and files inside subfolders.
     */
    private static function appendFiles(array &$result, array $fileItems, string $folderLabel = '', string $folderValue = ''): void
    {
        foreach ($fileItems as $file) {
            $fileName = $file['fileName'] ?? '';
            if ($fileName === '') {
                continue;
            }

            $result['files'][] = [
                'value' => wp_json_encode([
                    'folder' => $folderValue,
                    'name' => $fileName,
                ]),
                'label' => $folderLabel !== '' ? $folderLabel . ' / ' . $fileName : $fileName,
            ];
        }
    }

    /**
     * Iterates through all folders and builds a recursive folder tree.
     * Adds folders and their contained files to the result structure.
     */
    private static function buildFolderTree(array $result, array $validated, array $items): array
    {
        $resourceId = $validated['resourceId'];
        $shareId = $validated['shareId'];

        self::appendFiles($result, API::filterFiles($items));

        $queue = array_map(static fn($folder) => [
            'path' => self::relativePath($folder, $resourceId),
            'label' => $folder['fileName'],
        ], API::filterFolders($items));

        while ($queue) {
            $current = array_shift($queue);

            $result['folders'][] = [
                'value' => $current['path'],
                'label' => $current['label'],
            ];

            $subItems = API::fetchSubfolder($resourceId, $shareId, $current['path']);
            if (!is_array($subItems)) {
                continue;
            }

            self::appendFiles(
                $result,
                API::filterFiles($subItems),
                $current['label'],
                $current['path']
            );

            foreach (API::filterFolders($subItems) as $child) {
                $queue[] = [
                    'path' => self::relativePath($child, $resourceId),
                    'label' => $child['fileName'],
                ];
            }
        }

        return $result;
    }


    /**
     * Extracts the folder path from resourceURL relative to the resource ID.
     * Falls back to URL-encoded fileName if no path is found.
     */
    private static function relativePath(array $folder, string $resourceId): string
    {
        $resourceUrl = (string)($folder['resourceURL'] ?? '');
        if ($resourceUrl !== '') {
            $needle = '/' . $resourceId . '/';
            $pos = strpos($resourceUrl, $needle);
            if ($pos !== false) {
                $path = substr($resourceUrl, $pos + strlen($needle));
                return trim($path, '/');
            }
        }

        $fileName = (string)($folder['fileName'] ?? '');
        return $fileName !== '' ? rawurlencode($fileName) : '';
    }


    /**
     * REST endpoint: returns only the files of a share or a subfolder.
     * Used by the server-side renderer.
     */
    public static function getFiles(WP_REST_Request $request): WP_REST_Response
    {
        $shareLink = trim((string)$request->get_param('sharelink'));
        $folderName = trim((string)$request->get_param('folder'));

        if ($shareLink === '') {
            return new WP_REST_Response([], 200);
        }

        $shareId = API::resolveShareIdFromUrl($shareLink);
        if (!$shareId) {
            return new WP_REST_Response([], 200);
        }

        $resourceId = API::resolveResourceId($shareId);
        if (!$resourceId) {
            return new WP_REST_Response([], 200);
        }

        // SAME LOGIC AS folders – BUT return FILES only
        $items = ($folderName === '')
            ? API::fetchRoot($resourceId, $shareId)
            : API::fetchSubfolder($resourceId, $shareId, $folderName);

        if (!is_array($items)) {
            return new WP_REST_Response([], 200);
        }

        $files = API::filterFiles($items);

        return new WP_REST_Response($files, 200);
    }
}
