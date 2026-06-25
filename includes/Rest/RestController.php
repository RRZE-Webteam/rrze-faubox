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
                'file' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'sig' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
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
    public function getFolders(WP_REST_Request $request): array|\WP_Error
    {
        $path = (string)$request->get_param('path');

        if ($path === '') {
            // No path → return cached index (direct children of root only)
            $mainFolder = get_option('rrze_faubox_folder', '');
            if (empty($mainFolder)) {
                return new \WP_Error(
                    'no_folder_configured',
                    __('No main folder configured. Please set it in the FAUbox settings.',
                        'rrze-faubox'),
                    ['status' => 412]
                );
            }

            $index = $this->indexService->getIndex();
            if (empty($index)) {
                return new \WP_Error(
                    'index_not_built',
                    __('The folder index has not been built yet. Please save the FAUbox 
  settings or refresh the index manually.', 'rrze-faubox'),
                    ['status' => 503]
                );
            }

            // Only return direct children of the root folder
            $minDepth = min(array_map(
                fn(array $entry): int => substr_count($entry['path'], '/'),
                $index
            ));

            $filtered = array_values(array_filter(
                $index,
                fn(array $entry): bool => substr_count($entry['path'], '/') === $minDepth
            ));

            usort($filtered, fn(array $a, array $b): int => strcasecmp($a['name'],
                $b['name']));

            return $filtered;
        }

        // Path given → live subfolders, enriched with hasChildren from index
        $subFolders = $this->fileService->getSubFolders($path);

        if ($subFolders === null) {
            return new \WP_Error(
                'webdav_error',
                __('Could not retrieve folders. Please check your FAUbox credentials.',
                    'rrze-faubox'),
                ['status' => 502]
            );
        }

        $index = $this->indexService->getIndex();
        $indexByPath = array_column($index, null, 'path');

        return array_map(function (array $folder) use ($indexByPath): array {
            $folder['hasChildren'] = isset($indexByPath[$folder['path']])
                ? (bool)$indexByPath[$folder['path']]['hasChildren']
                : false;
            return $folder;
        }, $subFolders);
    }


    /**
     * POST /index/refresh
     */
    public function refreshIndex(WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if ($this->indexService->isOnCooldown()) {
            return new \WP_Error (
                'cooldown',
                __('Please wait before refreshing again.', 'rrze-faubox'),
                ['status' => 429]
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

        // c) Normalisieren — ZUERST, vor allen Prüfungen
        $filePath = rawurldecode($filePath);

        // Grundlegende Pfad-Prüfungen (nach Dekodierung)
        if (!str_starts_with($filePath, '/webdav/')) {
            wp_die(esc_html__('Invalid file path.', 'rrze-faubox'), 400);
        }
        if (str_contains($filePath, '..') || str_contains($filePath, './')) {
            wp_die(esc_html__('Invalid file path.', 'rrze-faubox'), 400);
        }

        // a) Auf konfigurierten Hauptordner begrenzen
        $rootFolder = get_option('rrze_faubox_folder', '');
        if (empty($rootFolder)) {
            wp_die(esc_html__('No folder configured.', 'rrze-faubox'), 403);
        }
        $normalizedRoot = '/webdav/' . trim($rootFolder, '/') . '/';
        if (!str_starts_with($filePath, $normalizedRoot)) {
            wp_die(esc_html__('Access denied.', 'rrze-faubox'), 403);
        }

        // b) HMAC-Signatur prüfen
        $sig = (string)$request->get_param('sig');
        $expected = hash_hmac('sha256', $filePath, wp_salt('auth'));
        if (!hash_equals($expected, $sig)) {
            wp_die(esc_html__('Invalid request.', 'rrze-faubox'), 403);
        }

        // Datei von FAUbox streamen
        $result = $this->client->streamFileToDisk($filePath);
        if (!$result) {
            wp_die(esc_html__('File not found.', 'rrze-faubox'), 404);
        }

        $fileName = basename($filePath);
        $fileName = str_replace(['"', "'", "\r", "\n", '\\', ';'], '', $fileName);
        $contentType = preg_replace('/[^a-zA-Z0-9\/\-\+\.]/', '', $result['content_type'] ?:
            'application/octet-stream');

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($result['tmpfile'])); // ← Bug-Fix
        header('X-Content-Type-Options: nosniff');

        try {
            readfile($result['tmpfile']);
        } finally {
            @unlink($result['tmpfile']);
        }

        exit;
    }
}

