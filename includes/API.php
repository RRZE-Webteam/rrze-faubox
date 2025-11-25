<?php

declare(strict_types=1);

namespace RRZE\FAUbox;

defined('ABSPATH') || exit;

/**
 * Handles communication with the FAUbox API.
 *
 * This version uses a manually stored JSESSIONID token from plugin settings.
 * No automatic cookie extraction. The admin must copy the token into the settings.
 */
class API
{
    /**
     * Base API endpoint for the FAUbox installation.
     */
    private const BASE_API = 'https://fauboxtest.rrze.uni-erlangen.de/api';

    /**
     * Returns the JSESSIONID token stored in WP options.
     *
     * @return string|null The token or null if not configured.
     */
    private static function getToken(): ?string
    {
        $token = get_option('rrze_faubox_token', '');
        return !empty($token) ? $token : null;
    }

    /**
     * Build an FAUbox API URL including optional subdirectory.
     *
     * @param string $folderId Root folder ID.
     * @param string $subdir   Optional nested directory path.
     *
     * @return string Fully assembled URL to call with ?action=...
     */
    private static function buildUrl(string $folderId, string $subdir = ''): string
    {
        $path = rtrim(self::BASE_API . '/files/' . rawurlencode($folderId), '/');

        if (!empty($subdir)) {
            $clean = trim($subdir, '/');
            $path .= '/' . $clean;
        }

        return $path;
    }

    /**
     * Fetches ALL items (files + folders) from FAUbox.
     *
     * @param string $folderId Folder ID.
     * @param string $subdir   Optional nested directory.
     *
     * @return array|null Decoded JSON array or null on failure.
     */
    public static function fetchAll(string $folderId, string $subdir = ''): ?array
    {
        $token = self::getToken();
        if ($token === null) {
            return null;
        }

        $url = self::buildUrl($folderId, $subdir) . '?action=getAll';

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                // Token is sent exactly like the FAUbox UI expects it
                'Cookie' => 'JSESSIONID=' . $token,
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if (!$body) {
            return null;
        }

        // PowerFolder returns:
        // { "ResultSet": { "Result": [ ...files... ] } }
        if (isset($json['ResultSet']['Result']) && is_array($json['ResultSet']['Result'])) {
            return $json['ResultSet']['Result'];
        }

        return null;
    }

    /**
     * Fetch files only (FAUbox does not return folder objects)
     *
     * @param string $folderId FAUbox folder ID.
     * @param string $subdir   Optional subdirectory inside the folder.
     *
     * @return array Array of file entries.
     */
    public static function fetchFiles(string $folderId, string $subdir = ''): array
    {
        $allItems = self::fetchAll($folderId, $subdir);

        if (!is_array($allItems)) {
            return [];
        }

        $files = [];

        foreach ($allItems as $item) {
            // FAUbox files have "fileName"
            if (!empty($item['fileName'])) {
                $files[] = $item;
            }
        }

        return $files;
    }


    /**
     * Fetches all FAUbox folders available to the logged-in user.
     *
     * Uses /api/accounts?action=getFolders.
     * Returns folderName + folderID.
     *
     * @return array|null
     */
    public static function fetchUserFolders(): ?array
    {
        $token = self::getToken();
        if (empty($token)) {
            return null;
        }

        $url = self::BASE_API . '/accounts?action=getFolders';

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Cookie' => 'JSESSIONID=' . $token,
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if (!$body) {
            return null;
        }

        $json = json_decode($body, true);

        if (isset($json['ResultSet']['Result']) && is_array($json['ResultSet']['Result'])) {
            return $json['ResultSet']['Result'];
        }

        return null;
    }
}