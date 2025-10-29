<?php

declare(strict_types=1);

namespace RRZE\FAUbox;

defined('ABSPATH') || exit;

/**
 * Class API
 *
 * Dummy implementation that returns structured file data for a given folder.
 * Replace internals later with real FAUbox / PowerFolder HTTP calls.
 */
class API
{
    /**
     * Fetch files from FAUbox API.
     *
     * @param string $folderId   Base64-encoded FAUbox folder ID.
     * @param string $token      API access token.
     * @param string|null $subdir Optional subfolder inside the folder.
     *
     * @return array|null
     */
    public static function fetchFiles(string $folderId, string $token, string $subdir = null): ?array
    {
        $baseUrl = 'https://faubox.fau.de'; // Anpassen, falls andere Instanz

        $path = $subdir ? '/' . trim($subdir, '/') : '';
        $url = sprintf('%s/api/files/%s%s?action=getAll', $baseUrl, $folderId, $path);

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data['ResultSet']['Result'] ?? null;
    }
}
