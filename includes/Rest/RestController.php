<?php

declare(strict_types=1);

namespace RRZE\FAUbox\Rest;

use WP_REST_Request;
use RRZE\FAUbox\API\FileService;
use RRZE\FAUbox\API\Client;

defined('ABSPATH') || exit;

/**
 * Registers and handles REST API endpoints for the FAUbox block.
 *
 * This controller exposes endpoints used by the Gutenberg block
 * to fetch prepared file data from the FAUbox API.
 *
 * The controller depends on the FileService to retrieve
 * and transform file data.
 */
final class RestController
{
    /**
     * @var FileService
     */
    private FileService $fileService;

    /**
     * @var Client
     */
    private Client $client;

    /**
     * Constructor.
     *
     * @param FileService $fileService
     * @param Client $client
     */
    public function __construct(FileService $fileService, Client $client)
    {
        $this->fileService = $fileService;
        $this->client = $client;

        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Registers all REST routes.
     *
     * @return void
     */
    public function registerRoutes(): void
    {
        //ToDo:current_user_can checken

        register_rest_route('rrze-faubox/v1', '/files', [
            'methods' => 'GET',
            'callback' => [$this, 'getFiles'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'path' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'extensions' => [
                    'default' => [],
                    'sanitize_callback' => fn($val) => is_array($val)
                        ? array_map('sanitize_text_field', $val) : [],
                ],
                'sort' => [
                    'default' => 'asc',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'orderby' => [
                    'default' => 'name',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('rrze-faubox/v1', '/folders', [
            'methods' => 'GET',
            'callback' => [$this, 'getFolders'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'path' => [
                    'required' => false,
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('rrze-faubox/v1', '/download', [
            'methods' => 'GET',
            'callback' => [$this, 'downloadFile'],
            'permission_callback' => '__return_true',
            'args' => [
                'file' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }


    /**
     * GET /files
     *
     * Returns a prepared file list for the given WebDAV
    path.
     *
     * @param WP_REST_Request $request
     * @return array
     */
    public function getFiles(WP_REST_Request $request):
    array
    {
        return
            $this->fileService->getPreparedFilesFromFolder(
                (string)$request->get_param('path'),
                (array)$request->get_param('extensions'),
                (string)$request->get_param('sort'),
                (string)$request->get_param('orderby')
            );
    }

    /**
     * GET /folders
     *
     * Returns all root folders accessible to the
    authenticated user.
     *
     * @return array
     */
    public function getFolders(WP_REST_Request $request): array
    {
        $path = (string) $request->get_param('path');

        if ($path !== '') {
            return $this->fileService->getSubFolders($path);
        }

        return $this->fileService->getAccessibleRootFolders();

    }

    /**
     * GET /download
     *
     * Proxy endpoint: fetches a file from FAUbox using
    stored credentials
     * and streams it to the visitor. The token is never
    exposed to the client.
     *
     * @param WP_REST_Request $request
     * @return void
     */
    public function downloadFile(WP_REST_Request $request): void
    {
        $filePath = (string)$request->get_param('file');

        // Only allow paths starting with /webdav/
        if (!str_starts_with($filePath, '/webdav/')) {
            wp_die(esc_html__('Invalid file path.', 'rrze-faubox'), 400);
        }

        $result =
            $this->client->fetchFileContent($filePath);

        if (!$result) {
            wp_die(esc_html__('File not found.', 'rrze-faubox'), 404);
        }

        $fileName    = basename(urldecode($filePath));
        $contentType = $result['content_type'] ?: 'application/octet-stream';

        header('Content-Type: ' . $contentType);
        //open in new window: inline instead of attachment
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Content-Length: ' . strlen($result['body']));
        header('X-Content-Type-Options: nosniff');

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $result['body'];
        exit;
    }
}
