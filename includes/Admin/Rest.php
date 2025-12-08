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
        $shareLink = trim((string)$request->get_param('sharelink'));
        $folderName = trim((string)$request->get_param('folder'));

        // 1. Validate share link
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


        // 2. Fetch either root or subfolder
        $items = ($folderName === '')
            ? API::fetchRoot($resourceId, $shareId)
            : API::fetchSubfolder($resourceId, $shareId, $folderName);


        if (!is_array($items)) {
            return new WP_REST_Response([], 200);
        }


        $folders = API::filterFolders($items);
        $files = API::filterFiles($items);


        $result = [
            'folders' => [],
            'files' => []
        ];

        $appendFiles = static function (array $fileItems, string $folderLabel = '', string $folderValue = '') use (&$result): void {
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
        };

        // root data
        $appendFiles($files);

        $queue = array_map(static fn($folder) => [
            'path' => self::relativePath($folder, $resourceId),
            'label' => $folder['fileName'],
        ], $folders);

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

            $appendFiles(API::filterFiles($subItems), $current['label'], $current['path']);

            foreach (API::filterFolders($subItems) as $child) {
                $queue[] = [
                    'path' => self::relativePath($child, $resourceId),
                    'label' => $child['fileName'],
                ];
            }
        }


        return new WP_REST_Response($result, 200);


    }

    /**
     * Helper for extracting URL part from resource URL
     * @param array $folder
     * @param string $resourceId
     * @return string
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
     * Returns FILES ONLY for SSR rendering
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
