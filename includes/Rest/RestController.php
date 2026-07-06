<?php

declare(strict_types=1);

namespace RRZE\FAUbox\Rest;

use WP_REST_Request;
use WP_REST_Response;
use RRZE\FAUbox\API\FileService;
use RRZE\FAUbox\API\IndexService;
use RRZE\FAUbox\API\DownloadService;

defined('ABSPATH') || exit;

/**
 * Registers and handles REST API endpoints for the FAUbox block.
 */
final class RestController
{
    private FileService $fileService;
    private IndexService $indexService;
    private DownloadService $downloadService;

    /**
     * Constructor.
     */
    public function __construct(FileService $fileService, IndexService $indexService, DownloadService $downloadService)
    {
        $this->fileService = $fileService;
        $this->indexService = $indexService;
        $this->downloadService = $downloadService;

        add_action('rest_api_init', [$this, 'registerRoutes']);
    }


    /**
     * Registers all REST API routes for the FAUbox plugin.
     */
    public function registerRoutes(): void
    {
        register_rest_route('rrze-faubox/v1', '/files', [
            'methods' => 'GET',
            'callback' => [$this, 'getFiles'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'path' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'extensions' => ['default' => [], 'sanitize_callback' => fn($val) => is_array($val)
                    ? array_map('sanitize_text_field', $val) : []],
                'sort' => ['default' => 'asc', 'sanitize_callback' => 'sanitize_text_field'],
                'orderby' => ['default' => 'name', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route('rrze-faubox/v1', '/folders', [
            'methods' => 'GET',
            'callback' => [$this, 'getFolders'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => ['path' => ['required' => false, 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route('rrze-faubox/v1', '/index/refresh', [
            'methods' => 'POST',
            'callback' => [$this, 'refreshIndex'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('rrze-faubox/v1', '/index/status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getIndexStatus'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('rrze-faubox/v1', '/download', [
            'methods' => 'GET',
            'callback' => [$this, 'downloadFile'],
            'permission_callback' => '__return_true',
            'args' => [
                'file' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'sig' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }


    /**
     * Returns a prepared list of files from a given WebDAV folder path.
     *
     * @param WP_REST_Request $request Requires: path, extensions, sort, orderby.
     * @return array Render-ready file list.
     */
    public function getFiles(WP_REST_Request $request): array
    {
        return $this->fileService->getPreparedFilesFromFolder(
            (string)$request->get_param('path'),
            (array)$request->get_param('extensions'),
            (string)$request->get_param('sort'),
            (string)$request->get_param('orderby')
        );
    }


    /**
     * GET /folders
     *
     * Without path: returns direct children from the cached index.
     * With path: returns live subfolders enriched with hasChildren from the index.
     */
    public function getFolders(WP_REST_Request $request): array|\WP_Error
    {
        $path = (string)$request->get_param('path');

        if ($path === '') {
            if (empty(get_option('rrze_faubox_folder', ''))) {
                return new \WP_Error(
                    'no_folder_configured',
                    __('No main folder configured. Please set it in the FAUbox settings.', 'rrze-faubox'),
                    ['status' => 412]
                );
            }

            $children = $this->indexService->getDirectChildren();
            if (empty($children)) {
                return new \WP_Error(
                    'index_not_built',
                    __('The folder index has not been built yet. Please save the FAUbox settings or refresh the index manually.', 'rrze-faubox'),
                    ['status' => 503]
                );
            }

            return $children;
        }

        if (!empty($this->indexService->getIndex())) {
            return $this->indexService->getChildrenOfPath($path);
        }

        $subFolders = $this->fileService->getSubFolders($path);
        if ($subFolders === null) {
            return new \WP_Error(
                'webdav_error',
                __('Could not retrieve folders. Please check your FAUbox credentials.',
                    'rrze-faubox'),
                ['status' => 502]
            );
        }

        return $this->indexService->enrichWithHasChildren($subFolders);
    }


    /**
     * Triggers a rebuild of the folder index.
     *
     * Returns 429 if a cooldown is active, 200 on success.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|\WP_Error
     */
    public function refreshIndex(WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if ($this->indexService->isOnCooldown()) {
            return new \WP_Error(
                'cooldown',
                __('Please wait 5 minutes before refreshing again.', 'rrze-faubox'),
                ['status' => 429]
            );
        }

        $this->indexService->buildIndex();
        $this->indexService->setCooldown();

        return new \WP_REST_Response(['success' => true], 200);
    }


    /**
     * Delegates the file download request to DownloadService.
     *
     * @param WP_REST_Request $request Requires: file (WebDAV path), sig (HMAC signature).
     */
    public function downloadFile(WP_REST_Request $request): void
    {
        $this->downloadService->handle($request);
    }


    /**
     * Return current index build metadata.
     *
     * @return \WP_REST_Response
     */
    public function getIndexStatus(): \WP_REST_Response
    {
        $info = $this->indexService->getLastBuiltInfo();
        return new \WP_REST_Response([
            'built' => $info !== null,
            'time'  => $info['time'] ?? null,
            'count' => $info['count'] ?? 0,
        ], 200);
    }

}
