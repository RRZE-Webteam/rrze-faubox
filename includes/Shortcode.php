<?php

declare(strict_types=1);

namespace RRZE\FAUbox;

use RRZE\FAUbox\API;
use RRZE\FAUbox\Renderer;

defined('ABSPATH') || exit;

class Shortcode
{
    /**
     * Register the shortcode.
     */
    public static function register(): void
    {
        add_shortcode('faubox', [self::class, 'render']);
    }

    /**
     * Shortcode + Block renderer.
     */
    public static function render(array $atts = [], ?string $content = null): string
    {
        /**
         * --------------------------------------------------------------
         * 1) SHARE LINK
         * --------------------------------------------------------------
         */
        $shareLink = trim((string)($atts['sharelink'] ?? ''));

        if ($shareLink === '') {
            return '<p>FAUbox share link missing.</p>';
        }

        $shareId = API::resolveShareIdFromUrl($shareLink);
        if (!$shareId) {
            return '<p>Invalid FAUbox public link.</p>';
        }

        $resourceId = API::resolveResourceId($shareId);
        if (!$resourceId) {
            return '<p>Unable to resolve FAUbox resource ID.</p>';
        }

        /**
         * --------------------------------------------------------------
         * 2) DEFAULT ATTRIBUTES
         * --------------------------------------------------------------
         */
        $atts = shortcode_atts([
            'index'           => '',
            'view'            => 'list',
            'show'            => ['name'],
            'filetype'        => [],
            'sort'            => 'asc',
            'orderby'         => 'name',
            'show_title'      => false,
            'changeTitle'     => '',
            'selectedFolders' => [],
            'selectedFiles'   => [],
        ], $atts, 'faubox');

        $folderName = trim((string)$atts['index']);
        $selectedFolders = array_map('strval', (array)$atts['selectedFolders']);
        $selectedFiles = array_map('strval', (array)$atts['selectedFiles']);

        /**
         * --------------------------------------------------------------
         * 3) API LOAD (ROOT OR SUBFOLDER)
         * --------------------------------------------------------------
         */
        $allItems = [];

        // 1) Ausgewählte Ordner laden
        foreach ($selectedFolders as $folder) {
            $items = API::fetchSubfolder($resourceId, $shareId, $folder);
            if (is_array($items)) {
                $allItems = array_merge($allItems, $items);
            }
        }

        // 2) Einzelne Dateien (Root + Unterordner) laden
        if (!empty($selectedFiles)) {
            $fileSelections = [];

            foreach ($selectedFiles as $fileRef) {
                $fileRef = trim((string)$fileRef);
                if ($fileRef === '') {
                    continue;
                }

                $folderKey = '';
                $fileName = '';

                if ($fileRef !== '' && $fileRef[0] === '{') {
                    $decoded = json_decode($fileRef, true);
                    if (is_array($decoded)) {
                        $folderKey = (string)($decoded['folder'] ?? '');
                        $fileName = (string)($decoded['name'] ?? '');
                    }
                }

                if ($fileName === '') {
                    if (strpos($fileRef, '|') !== false) {
                        [$folderKey, $fileName] = explode('|', $fileRef, 2);
                    } else {
                        $fileName = $fileRef;
                    }
                }

                if ($fileName === '') {
                    continue;
                }

                $fileSelections[] = [
                    'folder' => $folderKey,
                    'name'   => $fileName,
                ];
            }

            $rootItems = null;
            $folderCache = [];

            foreach ($fileSelections as $selection) {
                $folderKey = $selection['folder'];
                $targetName = $selection['name'];

                if ($folderKey === '' || $folderKey === null) {
                    if ($rootItems === null) {
                        $rootItems = API::fetchRoot($resourceId, $shareId) ?? [];
                    }

                    foreach ($rootItems as $item) {
                        if (($item['type'] ?? '') === 'file' && ($item['fileName'] ?? '') === $targetName) {
                            $allItems[] = $item;
                            break;
                        }
                    }

                    continue;
                }

                if (!isset($folderCache[$folderKey])) {
                    $folderCache[$folderKey] = API::fetchSubfolder($resourceId, $shareId, $folderKey) ?? [];
                }

                foreach ($folderCache[$folderKey] as $item) {
                    if (($item['type'] ?? '') === 'file' && ($item['fileName'] ?? '') === $targetName) {
                        $allItems[] = $item;
                        break;
                    }
                }
            }
        }

        // 3) Wenn nichts ausgewählt: Root laden (Fallback)
        if (empty($selectedFolders) && empty($selectedFiles)) {
            $allItems = API::fetchRoot($resourceId, $shareId) ?? [];
        }

        /**
         * --------------------------------------------------------------
         * 4) SPLIT INTO FILES & FOLDERS
         * --------------------------------------------------------------
         */
        $files   = API::filterFiles($allItems);
        $folders = API::filterFolders($allItems);

        /**
         * --------------------------------------------------------------
         * 5) CONVERT FILES FOR RENDERER
         * --------------------------------------------------------------
         */
        $files = array_map(static function ($file) use ($shareId) {

            $name = $file['fileName'] ?? '';
            $resourceUrl = $file['resourceURL'] ?? '';

            // Extract actual file ID from URL
            $fileId = basename($resourceUrl);

            // Correct download URL
            $downloadUrl = sprintf(
                'https://faubox.rrze.uni-erlangen.de/download/%s',
                rawurlencode($fileId)
            );

            return [
                'name'     => $name,
                'url'      => $downloadUrl,
                'type'     => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
                'name_raw' => strtolower($name),
            ];
        }, $files);

        /**
         * --------------------------------------------------------------
         * 6) FILTER FILETYPES
         * --------------------------------------------------------------
         */
        $allowedTypes = array_map('strtolower', (array)$atts['filetype']);

        if (!empty($allowedTypes)) {
            $files = array_filter($files, static function ($file) use ($allowedTypes) {
                return in_array($file['type'], $allowedTypes, true);
            });
        }

        /**
         * --------------------------------------------------------------
         * 7) SORTING
         * --------------------------------------------------------------
         */
        $orderby = $atts['orderby'];
        $sort    = strtolower($atts['sort']) === 'desc' ? 'desc' : 'asc';

        usort($files, static function ($a, $b) use ($orderby, $sort): int {
            $valA = $a[$orderby . '_raw'] ?? $a[$orderby] ?? '';
            $valB = $b[$orderby . '_raw'] ?? $b[$orderby] ?? '';

            if ($valA === $valB) {
                return 0;
            }

            return ($sort === 'asc')
                ? ($valA < $valB ? -1 : 1)
                : ($valA < $valB ? 1 : -1);
        });

        /**
         * --------------------------------------------------------------
         * 8) RENDERING
         * --------------------------------------------------------------
         */
        $output = '';

        // Optional custom title
        if ($atts['show_title']) {
            $title = trim((string)$atts['changeTitle']);
            if ($title === '') {
                if (!empty($selectedFolders)) {
                    $title = rawurldecode((string)$selectedFolders[0]);
                } elseif ($folderName !== '') {
                    $title = $folderName;
                } else {
                    $title = '/';
                }
            }
            $output .= Renderer::renderTitle($title);
        }

        // Render folders
        if (!empty($folders)) {
            $mappedFolders = array_map(static function ($folder) use ($folderName): array {
                return [
                    'name' => $folder['fileName'],
                    'path' => $folderName === '' ? $folder['fileName'] : $folderName . '/' . $folder['fileName'],
                ];
            }, $folders);
            $output .= Renderer::renderFolders($mappedFolders, ['folder' => $folderName ?: '/']);
        }

        // Render files
        $output .= Renderer::render($files, [
            'view' => $atts['view'],
            'show' => $atts['show'],
        ]);

        return $output;
    }
}
