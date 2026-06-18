<?php

declare(strict_types=1);

namespace RRZE\FAUbox\Rest;

use WP_REST_Request;
use WP_REST_Response;
use RRZE\FAUbox\API\FileService;
use RRZE\FAUbox\API\Client;
use RRZE\FAUbox\API\IndexService;

defined('ABSPATH') || exit;

/**
 * Registers and handles REST API endpoints for the FAUbox block.
 */
final class RestController
{
    private FileService $fileService;
    private Client $client;
    private IndexService $indexService;

    public function __construct(FileService $fileService, Client $client, IndexService $indexService)
    {
        $this->fileService = $fileService;
        $this->client = $client;
        $this->indexService = $indexService;

        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('rrze-faubox/v1', '/files', [
            'methods' => 'GET',
            'callback' => [$this, 'getFiles'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'path' => ['required' => true,
                    'sanitize_callback' => 'sanitize_text_field'],
                'extensions' => ['default' => [],
                    'sanitize_callback' => fn($val) => is_array($val) ?
                        array_map('sanitize_text_field', $val) : []],
                'sort' => ['default' => 'asc',
                    'sanitize_callback' => 'sanitize_text_field'],
                'orderby' => ['default' => 'name',
                    'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route('rrze-faubox/v1', '/folders', [
            'methods' => 'GET',
            'callback' => [$this, 'getFolders'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'path' => ['required' => false, 'default' => '',
                    'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route('rrze-faubox/v1', '/index/refresh', [
            'methods' => 'POST',
            'callback' => [$this, 'refreshIndex'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('rrze-faubox/v1', '/download', [
            'methods' => 'GET',
            'callback' => [$this, 'downloadFile'],
            'permission_callback' => '__return_true',
            'args' => [
                'file' => ['required' => true, 'sanitize_callback'
                => 'sanitize_text_field'],
            ],
        ]);
    }

    /**
     * GET /files
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
     * Without path: returns the cached index.
     * With path: returns live subfolders for in-editor navigation.
     */
    public function getFolders(WP_REST_Request $request):
    array|\WP_Error
    {
        $path = (string)$request->get_param('path');

        if ($path !== '') {
            $subFolders = $this->fileService->getSubFolders($path);
            $index      = $this->indexService->getIndex();

            // Enrich live results with hasChildren from the index
            $indexByPath = array_column($index, null, 'path');
            return array_map(function (array $folder) use ($indexByPath):
            array {
                $folder['hasChildren'] = isset($indexByPath[$folder['path']])
                    ? (bool) $indexByPath[$folder['path']]['hasChildren']
                    : false;
                return $folder;
            }, $subFolders);
        }

        $mainFolder = get_option('rrze_faubox_folder', '');
        if (empty($mainFolder)) {
            return new \WP_Error(
                'no_folder_configured',
                __('No main folder configured. Please set it in the FAUbox settings.', 'rrze-faubox'),
                ['status' => 412]
            );
        }

        $index = $this->indexService->getIndex();
        if (empty($index)) {
            return new \WP_Error(
                'index_not_built',
                __('The folder index has not been built yet. Please save the FAUbox settings or refresh the index manually.', 'rrze-faubox'),
                ['status' => 503]
            );
        }

        // Only return direct children of the root folder
        $rootDepth = substr_count(rtrim($mainFolder, '/'), '/') + 1;
        return array_values(array_filter($index, function (array
                                                           $entry) use ($rootDepth): bool {
            return substr_count($entry['path'], '/') === $rootDepth;
        }));
    }

    /**
     * POST /index/refresh
     */
    public function refreshIndex(WP_REST_Request $request):
    WP_REST_Response
    {
        if ($this->indexService->isOnCooldown()) {
            return new WP_REST_Response(
                ['success' => false, 'message' => __('Please wait before refreshing again.', 'rrze-faubox')],
                429
            );
        }

        $this->indexService->buildIndex();

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * GET /download
     */
    public function downloadFile(WP_REST_Request $request): void
    {
        $filePath = (string)$request->get_param('file');

        if (!str_starts_with($filePath, '/webdav/')) {
            wp_die(esc_html__('Invalid file path.', 'rrze-faubox'),
                400);
        }

        if (str_contains($filePath, '..') || str_contains($filePath,
                './')) {
            wp_die(esc_html__('Invalid file path.', 'rrze-faubox'),
                400);
        }

        $result = $this->client->fetchFileContent($filePath);

        if (!$result) {
            wp_die(esc_html__('File not found.', 'rrze-faubox'),
                404);
        }

        $fileName = basename(urldecode($filePath));
        $fileName = str_replace(['"', "'", "\r", "\n", '\\'], '',
            $fileName);
        $contentType = preg_replace('/[^a-zA-Z0-9\/\-\+\.]/', '',
            $result['content_type'] ?: 'application/octet-stream');

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' .
            $fileName . '"');
        header('Content-Length: ' . mb_strlen($result['body'],
                '8bit'));
        header('X-Content-Type-Options: nosniff');

// phpcs:ignore WordPress . Security . EscapeOutput . OutputNotEscaped
        echo $result['body'];
        exit;
    }
}

