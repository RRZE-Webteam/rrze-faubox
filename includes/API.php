<?php

declare(strict_types=1);

namespace RRZE\FAUbox;

defined('ABSPATH') || exit;

/**
 * Handles communication with the FAUbox public link API (/wapi/filelink).
 *
 * This version does NOT require authentication.
 * It uses the Share-ID provided by the user and resolves the internal Resource-ID.
 *
 * Workflow:
 * 1. User enters a public share link.
 * 2. We extract the Share-ID.
 * 3. We resolve the internal Resource-ID via getFileInfo.
 * 4. We list folders and files using the Resource-ID + Share-ID.
 */
class API
{
    /**
     * Base URL for the FAUbox WAPI interface.
     *
     * @var string
     */
    private const BASE_WAPI = 'https://faubox.rrze.uni-erlangen.de/wapi/filelink';


    /**
     * Extracts the share ID from a public FAUbox link.
     *
     * Example:
     *  https://faubox.rrze.uni-erlangen.de/getlink/fiW1p6e2svmkxDNCVTB/
     *  → fiW1p6e2svmkxDNCVTB
     *
     * @param string $url Public sharing link.
     * @return string|null Share-ID or null on failure.
     */
    public static function resolveShareIdFromUrl(string $url): ?string
    {
        // Normalize
        $clean = rtrim($url, '/');

        // Extract after /getlink/
        $pos = strpos($clean, '/getlink/');
        if ($pos === false) {
            return null;
        }

        // Keep only the path after /getlink/
        $after = substr($clean, $pos + strlen('/getlink/'));

        // Share-ID is always the first segment
        $parts = explode('/', $after);
        $shareId = $parts[0] ?? '';

        return $shareId !== '' ? $shareId : null;
    }


    /**
     * Resolves the FAUbox internal Resource-ID from the Share-ID.
     *
     * API Call:
     *  GET /wapi/filelink/?action=getFileInfo&ID={shareId}&json=1
     *
     * Response contains:
     *  "resourceURL": "https://faubox.../files/{resourceId}"
     *
     * @param string $shareId Public Share-ID.
     * @return string|null Resource-ID or null if not resolvable.
     */
    public static function resolveResourceId(string $shareId): ?string
    {
        //Cache key für ResourceID
        $cacheKey = 'faubox_resid_' . md5($shareId);

        // check if in cache
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }


        $url = self::BASE_WAPI .
            '?action=getFileInfo&ID=' . rawurlencode($shareId) . '&json=1';

        $response = wp_safe_remote_get($url);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);


        if (empty($body)) {
            return null;
        }

        $json = json_decode($body, true);

        if (!is_array($json) || empty($json['ResultSet']['Result'][0]['resourceURL'])) {
            return null;
        }

        // Extract the part after .../files/
        $resourceUrl = $json['ResultSet']['Result'][0]['resourceURL'];
        $pos = strrpos($resourceUrl, '/');

        if ($pos === false) {
            return null;
        }

        $resourceId = substr($resourceUrl, $pos + 1);

        if (!empty($resourceId)) {
            //Cache 1 hour
            set_transient($cacheKey, $resourceId, 60*60);
        }

        return !empty($resourceId) ? $resourceId : null;
    }


    /**
     * Fetch the root folder contents (folders + files).
     *
     * API Call:
     *  GET /wapi/filelink/{resourceId}?action=getFiles&ID={shareId}&json=1
     *
     * @param string $resourceId Internal Resource-ID.
     * @param string $shareId Public Share-ID.
     * @return array|null Array of items or null on failure.
     */
    public static function fetchRoot(string $resourceId, string $shareId): ?array
    {
        //Cache key
        $cacheKey = 'faubox_root_' . md5($resourceId . '_' . $shareId);

        // check Cache
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $url = self::BASE_WAPI . '/' . rawurlencode($resourceId) .
            '?action=getFiles&ID=' . rawurlencode($shareId) . '&json=1';

        $response = wp_safe_remote_get($url);

        if (is_wp_error($response)) {
            return null;
        }


        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return null;
        }

        $json = json_decode($body, true);

        if (!isset($json['ResultSet']['Result']) || !is_array($json['ResultSet']['Result'])) {
            return null;
        }


        $items = $json['ResultSet']['Result'];

        set_transient($cacheKey, $items, 60*60);

        return $items;

    }


    /**
     * Fetch items from a subfolder.
     *
     * API Call:
     *  GET /wapi/filelink/{resourceId}/{folderName}?action=getFiles&ID={shareId}&json=1
     *
     * Example:
     *  /wapi/filelink/MlhFQ2lqUzMxcHdIaUVpaXFLb2/Bilder
     *
     * @param string $resourceId Internal Resource-ID.
     * @param string $shareId Public Share-ID.
     * @param string $folderName The subfolder name (URL encoded automatically).
     * @return array|null Array of items or null.
     */


    public static function fetchSubfolder(string $resourceId, string $shareId, string $folderName): ?array
    {
        $cacheKey =
            'faubox_sub_' .
            md5($resourceId . '_' . $shareId . '_' . $folderName);

        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        // folderName is already encoded from resourceURL → do NOT re-encode
        $url = self::BASE_WAPI . '/' . rawurlencode($resourceId) . '/' . $folderName .
            '?action=getFiles&ID=' . rawurlencode($shareId) . '&json=1';

        $response = wp_safe_remote_get($url);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return null;
        }

        $json = json_decode($body, true);

        if (!isset($json['ResultSet']['Result']) || !is_array($json['ResultSet']['Result'])) {
            return null;
        }

        $items = $json['ResultSet']['Result'];

        set_transient($cacheKey, $items, 60*60);

        return $items;
    }

    /**
     * Filter an item array to only return files.
     *
     * FAUbox marks files as:
     *  "type": "file"
     *
     * @param array $items Mixed items from fetchRoot() or fetchSubfolder().
     * @return array Files only.
     */
    public static function filterFiles(array $items): array
    {
        $files = [];

        foreach ($items as $item) {
            $type = strtolower((string)($item['type'] ?? ''));
            if ($type === 'file') {
                $files[] = $item;
            }
        }

        return $files;
    }


    /**
     * Filter an item array to only return folders.
     *
     * FAUbox marks folders as:
     *  "type": "dir"
     *
     * @param array $items Mixed items from fetchRoot() or fetchSubfolder().
     * @return array Folders only.
     */
    public static function filterFolders(array $items): array
    {
        $folders = [];

        foreach ($items as $item) {
            $type = strtolower((string)($item['type'] ?? ''));
            if ($type === 'dir') {
                $folders[] = $item;
            }
        }

        return $folders;
    }
}
