<?php

declare(strict_types=1);

namespace RRZE\FAUbox\Admin;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;
use RRZE\FAUbox\API;

/**
 * REST endpoints for FAUbox folder handling.
 *
 * Provides:
 *  - /root-folders  → lists all user folders (FAUbox accounts API)
 *  - /folders       → lists subfolders of a selected folder (extracted from relativeName)
 */
class Rest
{
    /**
     * Registers all REST routes.
     */
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    /**
     * Defines the routes.
     */
    public static function registerRoutes(): void
    {
        // Root folder list (folders accessible to user)
        register_rest_route('rrze-faubox/v1', '/root-folders', [
            'methods' => 'GET',
            'callback' => [self::class, 'getRootFolders'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        // Subfolders inside a selected root folder
        register_rest_route('rrze-faubox/v1', '/folders', [
            'methods' => 'GET',
            'callback' => [self::class, 'getSubfolders'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

    }

    /**
     * 1️⃣ Root folders for the block selector.
     */
    public static function getRootFolders(WP_REST_Request $request): WP_REST_Response
    {
        $folders = API::fetchUserFolders();

        if (!is_array($folders)) {
            return new WP_REST_Response([
                ['value' => '', 'label' => 'Unable to load FAUbox folders']
            ], 200);
        }

        $result = [];

        foreach ($folders as $folder) {
            if (!empty($folder['folderID']) && !empty($folder['folderName'])) {
                $result[] = [
                    'value' => $folder['folderID'],
                    'label' => $folder['folderName'],
                ];
            }
        }

        if (empty($result)) {
            $result[] = [
                'value' => '',
                'label' => 'No folders available'
            ];
        }

        return new WP_REST_Response($result, 200);
    }

    /**
     * 2️⃣ Subfolders of a selected root folder (via relativeName extraction).
     */
    public static function getSubfolders(WP_REST_Request $request): WP_REST_Response
    {
        $folderId = sanitize_text_field($request->get_param('folder'));

        if (empty($folderId)) {
            return new WP_REST_Response([
                ['value' => '', 'label' => 'No folder ID provided']
            ], 200);
        }

        $items = API::fetchAll($folderId);

        if (!is_array($items)) {
            return new WP_REST_Response([
                ['value' => '', 'label' => 'Unable to load subfolders']
            ], 200);
        }

        $subfolders = [];

        foreach ($items as $item) {
            if (!empty($item['relativeName'])) {
                $path = dirname($item['relativeName']);
                if ($path !== '.' && $path !== '/') {
                    $subfolders[$path] = $path;
                }
            }
        }

        $result = [];

        foreach ($subfolders as $value) {
            $result[] = [
                'value' => $value,
                'label' => $value,
            ];
        }

        if (empty($result)) {
            $result[] = [
                'value' => '',
                'label' => 'No subfolders found'
            ];
        }

        return new WP_REST_Response($result, 200);
    }


}
