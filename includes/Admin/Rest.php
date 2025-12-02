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
        register_rest_route('rrze-faubox/v1', '/root-folders', [
            'methods' => 'GET',
            'callback' => [self::class, 'getRootFolder'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('rrze-faubox/v1', '/folders', [
            'methods' => 'GET',
            'callback' => [self::class, 'getSubfolders'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }


    /**
     * Load root-level folders from the FAUbox public link.
     *
     * @return WP_REST_Response
     */
    public static function getRootFolder(WP_REST_Request $request): WP_REST_Response
    {
        // Load share link from settings
        $shareLink = get_option('rrze_faubox_sharelink', '');

        if (empty($shareLink)) {
            return new WP_REST_Response([[
                'value' => '',
                'label' => 'Share link missing'
            ]], 200);
        }

        // Extract Share-ID
        $shareId = API::resolveShareIdFromUrl($shareLink);
        if (!$shareId) {
            return new WP_REST_Response([[
                'value' => '',
                'label' => 'Invalid share link'
            ]], 200);
        }

        // Resolve Resource-ID
        $resourceId = API::resolveResourceId($shareId);
        if (!$resourceId) {
            return new WP_REST_Response([[
                'value' => '',
                'label' => 'Unable to resolve resource ID'
            ]], 200);
        }

        // Fetch the root folder contents
        $items = API::fetchRoot($resourceId, $shareId);
        if (!is_array($items)) {
            return new WP_REST_Response([[
                'value' => '',
                'label' => 'Unable to load folders'
            ]], 200);
        }

        // Filter folders only
        $folders = API::filterFolders($items);

        // Convert to select options
        $result = [];
        foreach ($folders as $folder) {
            $name = $folder['fileName'] ?? '';
            if ($name === '') {
                continue;
            }

            $result[] = [
                'value' => $name,  // WILL BE USED AS index (subdir) IN BLOCK
                'label' => $name,
            ];
        }

        if (empty($result)) {
            $result[] = [
                'value' => '',
                'label' => 'No folders found'
            ];
        }

        return new WP_REST_Response($result, 200);
    }


    /**
     * Load subfolders for a given root folder.
     *
     * Parameter: ?folder=XY
     *
     * @return WP_REST_Response
     */
    public static function getSubfolders(WP_REST_Request $request): WP_REST_Response
    {
        $folderName = sanitize_text_field($request->get_param('folder'));

        if (empty($folderName)) {
            return new WP_REST_Response([[
                'value' => '',
                'label' => 'No folder provided'
            ]], 200);
        }

        // Get share link
        $shareLink = get_option('rrze_faubox_sharelink', '');
        if (empty($shareLink)) {
            return new WP_REST_Response([[
                'value' => '',
                'label' => 'Share link missing'
            ]], 200);
        }
        error_log('FAUbox sharelink: ' . print_r($shareLink, true));
        // Extract Share-ID
        $shareId = API::resolveShareIdFromUrl($shareLink);
        if (!$shareId) {
            return new WP_REST_Response([[
                'value' => '',
                'label' => 'Invalid share link'
            ]], 200);
        }

        // Resolve Resource-ID
        $resourceId = API::resolveResourceId($shareId);
        if (!$resourceId) {
            return new WP_REST_Response([[
                'value' => '',
                'label' => 'Unable to resolve resource ID'
            ]], 200);
        }

        // Fetch subfolder content
        $items = API::fetchSubfolder($resourceId, $shareId, $folderName);

        if (!is_array($items)) {
            return new WP_REST_Response([[
                'value' => '',
                'label' => 'Unable to load subfolders'
            ]], 200);
        }

        // Filter folders only
        $folders = API::filterFolders($items);

        // Convert to options
        $result = [];
        foreach ($folders as $folder) {
            $name = $folder['fileName'] ?? '';
            if ($name === '') {
                continue;
            }

            $value = $folderName . '/' . $name;

            $result[] = [
                'value' => $value,
                'label' => $name,
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
