<?php

declare(strict_types=1);

namespace RRZE\FAUbox\API;

defined('ABSPATH') || exit;

/**
 * Provides file-related business logic for FAUbox WebDAV data.
 *
 * Responsibilities:
 * - Retrieve raw folder entries via Client
 * - Extract file entries (ignore folders/collections)
 * - Filter files by extension
 * - Derive MIME type from file extension
 * - Transform WebDAV data into render-ready structure
 * - Apply sorting
 *
 * This class contains no presentation logic.
 */
final class FileService
{
    private const MIME_TYPES = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'zip' => 'application/zip',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'mp4' => 'video/mp4',
        'mp3' => 'audio/mpeg',
    ];


    /**
     * Low-level WebDAV client.
     *
     * @var Client
     */
    private Client $client;

    /**
     * Constructor.
     *
     * @param Client $client WebDAV client for retrieving raw folder entries.
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Retrieve, filter, transform and sort files from a FAUbox WebDAV folder path.
     *
     * Processing steps:
     * 1. Fetch raw entries from WebDAV (files + subfolders)
     * 2. Extract file entries only (skip collections)
     * 3. Apply extension filtering
     * 4. Transform into render-ready structure
     * 5. Apply sorting
     *
     * @param string $path WebDAV folder path (e.g. 'My Folder/Subfolder/').
     * @param array $allowedExtensions List of allowed file extensions (empty = all).
     * @param string $sort Sort direction ('asc' or 'desc').
     * @param string $orderby Sort field ('name', 'size', 'type', 'modified').
     *
     * @return array Prepared file data ready for rendering.
     */

    public function getPreparedFilesFromFolder(string $path, array $allowedExtensions = [], string $sort = 'asc', string $orderby = 'name'): array
    {
        $rawEntries = $this->client->fetchEntriesFromPath($path);

        if (!is_array($rawEntries)) {
            return [];
        }

        $files = $this->extractFileEntries($rawEntries);
        $files = $this->filterFilesByExtension($files, $allowedExtensions);
        $files = $this->transformEntriesToRenderableFormat($files);
        $files = $this->sortFiles($files, $sort, $orderby);

        return $files;
    }

    /**
     * Fetch top-level folders accessible to the authenticated user.
     *
     * @return array List of folder entries with 'name' and 'path'.
     */
    public function getAccessibleRootFolders(): array
    {
        $entries = $this->client->fetchRootFolders();

        if (!is_array($entries)) {
            return [];
        }

        return array_map(static fn(array $entry): array => [
            'name' => $entry['displayname'],
            'path' => $entry['path'],
        ], $entries);
    }


    /**
     * Extract only entries that represent actual files (not collections/folders).
     *
     * @param array $rawEntries Raw WebDAV entries.
     * @return array File entries only.
     */
    private function extractFileEntries(array $rawEntries): array
    {
        return array_values(array_filter(
            $rawEntries,
            static fn(array $entry): bool => !$entry['is_collection'] &&
                $entry['displayname'] !== ''
        ));
    }


    /**
     * Filter files by allowed file extensions.
     *
     * If no extensions are provided, the original file list is returned unchanged.
     *
     * @param array $files File entries.
     * @param array $allowedExtensions Allowed file extensions (case-insensitive).
     *
     * @return array Filtered file list.
     */
    private function filterFilesByExtension(array $files, array $allowedExtensions): array
    {
        if (empty($allowedExtensions)) {
            return $files;
        }

        $allowedExtensions = array_map(
            'strtolower',
            array_map('trim', $allowedExtensions)
        );

        return array_values(array_filter(
            $files,
            static function (array $file) use ($allowedExtensions): bool {

                $extension = strtolower(
                    pathinfo(
                        (string)($file['displayname'] ?? ''),
                        PATHINFO_EXTENSION
                    )
                );

                return in_array($extension, $allowedExtensions, true);
            }
        ));
    }

    /**
     * Derive MIME type from a file extension.
     *
     * Falls back to 'application/octet-stream' for unknown extensions.
     *
     * @param string $filename File name.
     * @return string MIME type string.
     */
    private function deriveMimeType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return self::MIME_TYPES[$extension] ?? 'application/octet-stream';
    }


    /**
     * Transform raw WebDAV entries into a structure suitable for rendering.
     *
     * Adds both formatted values (for display) and raw values (for sorting).
     * The download URL points to the WordPress proxy endpoint.
     *
     * @param array $files Raw WebDAV file entries.
     * @return array Transformed file structure.
     */

    private function transformEntriesToRenderableFormat(array $files): array
    {
        return array_map(
            function (array $file): array {
                $name = (string)($file['displayname'] ?? '');
                $path = (string)($file['path'] ?? '');
                $size = $file['contentlength'] ?? null;

                $downloadUrl = add_query_arg(
                    'file',
                    rawurlencode($path),
                    rest_url('rrze-faubox/v1/download')
                );

                return [
                    'name' => $name,
                    'url' => $downloadUrl,
                    'size' => $size !== null ? size_format($size) : '—',
                    'type' => $this->deriveMimeType($name),
                    'modified' => (string)($file['lastmodified'] ?? ''),

                    // raw values for sorting
                    'name_raw' => strtolower($name),
                    'size_raw' => $size ?? 0,
                    'type_raw' => strtolower($this->deriveMimeType($name)),
                    'modified_ts' => strtotime((string)($file['lastmodified'] ?? '')) ?: 0,
                ];
            },
            $files
        );
    }


    /**
     * Sort files by a given field and direction.
     *
     * Invalid sort directions default to ascending order.
     *
     * @param array  $files   File list.
     * @param string $sort    Sort direction ('asc' or 'desc').
     * @param string $orderby Sort field ('name','size', 'type', 'modified').
     *
     * @return array Sorted file list.
     */

    private function sortFiles(array $files, string $sort, string $orderby): array
    {
        $sort = strtolower($sort) === 'desc' ? 'desc' : 'asc';

        usort(
            $files,
            static function (array $a, array $b) use ($sort, $orderby): int {

                $valueA = $a[$orderby . '_raw']
                    ?? $a[$orderby]
                    ?? '';

                $valueB = $b[$orderby . '_raw']
                    ?? $b[$orderby]
                    ?? '';

                if ($valueA === $valueB) {
                    return 0;
                }

                $result = $valueA <=> $valueB;

                return $sort === 'desc'
                    ? -$result
                    : $result;
            }
        );

        return $files;
    }

}