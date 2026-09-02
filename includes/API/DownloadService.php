<?php
declare(strict_types=1);

namespace RRZE\FAUbox\API;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Proxy service for streaming FAUbox files to the browser.
 *
 * Responsibilities:
 * - Validate the requested file path
 * - Verify the HMAC signature
 * - Restrict access to the configured root folder
 * - Stream the file via Client and send appropriate headers
 */
class DownloadService
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Handle a download request.
     *
     * Validates path, root folder scope and HMAC signature before streaming.
     * Terminates execution after delivery.
     *
     * @param WP_REST_Request $request Requires: file (WebDAV path), sig (HMAC
    signature).
     */
    public function handle(WP_REST_Request $request): void
    {
        $filePath = rawurldecode((string)$request->get_param('file'));

        if (!str_starts_with($filePath, '/webdav/')) {
            wp_die(esc_html__('Invalid file path.', 'rrze-faubox'), 400);
        }
        if (str_contains($filePath, '..') || str_contains($filePath, './')) {
            wp_die(esc_html__('Invalid file path.', 'rrze-faubox'), 400);
        }

        $rootFolder = (string)get_option('rrze_faubox_folder', '');
        if ($rootFolder === '') {
            wp_die(esc_html__('No folder configured.', 'rrze-faubox'), 403);
        }
        $rootFolder = trim($rootFolder, '/');
        $normalizedRoot = '/webdav/' . ($rootFolder !== '' ? $rootFolder . '/' : '');
        if (!str_starts_with($filePath, $normalizedRoot)) {
            wp_die(esc_html__('Access denied.', 'rrze-faubox'), 403);
        }

        $sig = (string)$request->get_param('sig');
        $expected = hash_hmac('sha256', $filePath, wp_salt('auth'));
        if (!hash_equals($expected, $sig)) {
            wp_die(esc_html__('Invalid request.', 'rrze-faubox'), 403);
        }

        $result = $this->client->streamFileToDisk($filePath);
        if (!$result) {
            wp_die(esc_html__('File not found.', 'rrze-faubox'), 404);
        }

        $fileName = basename($filePath);
        $fileName = str_replace(['"', "'", "\r", "\n", '\\', ';'], '', $fileName);
        $contentType = preg_replace('/[^a-zA-Z0-9\/\-\+\.]/', '',
            $result['content_type'] ?: 'application/octet-stream');

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($result['tmpfile']));
        header('X-Content-Type-Options: nosniff');

        try {
            readfile($result['tmpfile']);
        } finally {
            @unlink($result['tmpfile']);
        }

        exit;
    }
}
