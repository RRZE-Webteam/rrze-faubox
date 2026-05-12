<?php

declare(strict_types=1);

namespace RRZE\FAUbox\API;

defined('ABSPATH') || exit;

/**
 * Handles low-level communication with the FAUbox WebDAV server.
 *
 * Responsibilities:
 * - HTTP Basic Authentication (username + token)
 * - PROPFIND requests to the WebDAV endpoint
 * - XML response parsing
 */
final class Client
{
    //TODO: URL auf faubox ändern!!!
    private const BASE_URL = 'https://fauboxtest.rrze.uni-erlangen.de/webdav/';

    /**
     * Get stored WEBDAV username.
     */
    private function getUsername(): ?string
    {
        $value = get_option('rrze_faubox_username', '');
        return $value !== '' ? $value : null;
    }

    /**
     * Get stored WEBDAV token.
     */
    private function getToken(): ?string
    {
        $value = get_option('rrze_faubox_token', '');
        return $value !== '' ? $value : null;
    }

    /**
     * Build the HTTP Basic Auth header value.
     *
     * @return string|null Authorization header value or null if credentials are missing.
     */
    private function buildAuthHeader(): ?string
    {
        $username = $this->getUsername();
        $token = $this->getToken();

        if (!$username || !$token) {
            return null;
        }

        return 'Basic ' . base64_encode($username . ':' . $token);
    }


    /**
     * Send a PROPFIND request to a WebDAV path.
     *
     * @param string $path Folder path relative to the WebDAV root (e.g. 'My Folder/Subfolder/').
     * @return \SimpleXMLElement|null Parsed XML response, or null on failure.
     */

    private function sendPropfindRequest(string $path): ?\SimpleXMLElement
    {
        $authHeader = $this->buildAuthHeader();

        if (!$authHeader) {
            return null;
        }

        $url = self::BASE_URL . ltrim(implode('/', array_map('rawurlencode', explode('/', trim($path, '/')))), '/');

        $body = '<?xml version="1.0" encoding="utf-8"?>'
            . '<D:propfind xmlns:D="DAV:">'
            . '<D:prop>'
            . '<D:displayname/>'
            . '<D:getcontentlength/>'
            . '<D:getlastmodified/>'
            . '<D:getetag/>'
            . '<D:resourcetype/>'
            . '</D:prop>'
            . '</D:propfind>';

        $response = wp_remote_request($url, [
            'method' => 'PROPFIND',
            'timeout' => 15,
            'headers' => [
                'Authorization' => $authHeader,
                'Depth' => '1',
                'Content-Type' => 'application/xml; charset=utf-8',
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $responseBody = wp_remote_retrieve_body($response);

        if (!$responseBody) {
            return null;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($responseBody);
        libxml_clear_errors();

        return $xml instanceof \SimpleXMLElement ? $xml : null;
    }

    /**
     * Parse a WebDAV multistatus XML response into a flat array of
     * entries.
     *
     * The first response element (the requested folder itself) is always
     * skipped.
     * Each entry contains:
     * - displayname:   string
     * - contentlength: int|null  (null for folders)
     * - lastmodified:  string
     * - etag:          string
     * - is_collection: bool      (true = folder, false = file)
     * - path:          string    (full href as returned by the server)
     *
     * @param \SimpleXMLElement $xml Parsed XML response.
     * @return array Parsed entries.
     */
    private function parseMultistatusResponse(\SimpleXMLElement $xml):
    array
    {
        $entries = [];

        $xml->registerXPathNamespace('D', 'DAV:');
        $responses = $xml->xpath('//D:response');

        if (!$responses) {
            return [];
        }

        foreach ($responses as $index => $response) {
            // First entry is always the requested folder itself — skip it.
            if ($index === 0) {
                continue;
            }

            $response->registerXPathNamespace('D', 'DAV:');

            $href = (string)($response->xpath('D:href')[0] ??
                '');
            $displayName =
                (string)($response->xpath('D:propstat/D:prop/D:displayname')[0] ?? '');
            $contentLength =
                (string)($response->xpath('D:propstat/D:prop/D:getcontentlength')[0] ??
                    '');
            $lastModified =
                (string)($response->xpath('D:propstat/D:prop/D:getlastmodified')[0] ??
                    '');
            $etag =
                (string)($response->xpath('D:propstat/D:prop/D:getetag')[0] ?? '');
            $isCollection =
                !empty($response->xpath('D:propstat/D:prop/D:resourcetype/D:collection'));

            $entries[] = [
                'displayname' => $displayName,
                'contentlength' => $contentLength !== '' ?
                    (int)$contentLength : null,
                'lastmodified' => $lastModified,
                'etag' => $etag,
                'is_collection' => $isCollection,
                'path' => $href,
            ];
        }

        return $entries;
    }

    /**
     * Fetch all entries (files and subfolders) from a given WebDAV folder
     * path.
     *
     * @param string $path Folder path relative to the WebDAV root.
     * @return array|null Array of entries, or null if the request failed.
     */
    public function fetchEntriesFromPath(string $path): ?array
    {
        $xml = $this->sendPropfindRequest($path);

        if (!$xml) {
            return null;
        }

        return $this->parseMultistatusResponse($xml);
    }

    /**
     * Fetch all top-level folders accessible to the authenticated user.
     *
     * Sends a PROPFIND to the WebDAV root with Depth: 1
     * and returns only collection entries.
     *
     * @return array|null Array of folder entries, or null if the request
     * failed.
     */
    public function fetchRootFolders(): ?array
    {
        $xml = $this->sendPropfindRequest('');

        if (!$xml) {
            return null;
        }

        $entries = $this->parseMultistatusResponse($xml);

        return array_values(array_filter(
            $entries,
            static fn(array $entry): bool => $entry['is_collection']
        ));
    }

    /**
     * Fetch the raw content of a file from the FAUbox
     * WebDAV server.
     *
     * Used by the download proxy endpoint.
     *
     * @param string $path Full WebDAV path (e.g.
     * '/webdav/My Folder/file.pdf').
     * @return array{body: string, content_type:
     * string}|null File content and type, or null on failure.
     */
    public function fetchFileContent(string $path): ?array
    {
        $authHeader = $this->buildAuthHeader();

        if (!$authHeader) {
            return null;
        }

        $url = 'https://fauboxtest.rrze.uni-erlangen.de' .
            $path;

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => $authHeader,
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $statusCode =
            wp_remote_retrieve_response_code($response);

        if ($statusCode !== 200) {
            return null;
        }

        return [
            'body' =>
                wp_remote_retrieve_body($response),
            'content_type' =>
                wp_remote_retrieve_header($response, 'content-type'),
        ];
    }
}

