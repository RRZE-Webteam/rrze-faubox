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
            return '<p>' . esc_html__('FAUbox share link missing.', 'rrze-faubox') . '</p>';
        }

        $shareId = API::resolveShareIdFromUrl($shareLink);
        if (!$shareId) {
            return '<p>' . esc_html__('Invalid FAUbox public link.', 'rrze-faubox') . '</p>';
        }

        $resourceId = API::resolveResourceId($shareId);
        if (!$resourceId) {
            return '<p>' . esc_html__('Invalid FAUbox public link.', 'rrze-faubox') . '</p>';
        }

        /**
         * --------------------------------------------------------------
         * 2) DEFAULT ATTRIBUTES
         * --------------------------------------------------------------
         */
        $atts = shortcode_atts([
            'view' => 'list',
            'show' => ['name'],
            'filetype' => [],
            'sort' => 'asc',
            'show_title' => false,
            'changetitle' => '',
            'selectedfolders' => [],

        ], $atts, 'faubox');

        // ensure array formats
        $atts['show'] = self::normalizeShowAttributes($atts['show']);
        $atts['filetype'] = (array)$atts['filetype'];
        $selectedFolders = self::normalizeSelectedFolders($atts['selectedfolders']);

        $atts['show_title'] = filter_var($atts['show_title'], FILTER_VALIDATE_BOOLEAN);


        /**
         * --------------------------------------------------------------
         * 3) API LOAD (ROOT OR SUBFOLDER)
         * --------------------------------------------------------------
         */
        $allItems = [];

        // If no folder selected → Load root
        if (empty($selectedFolders)) {
            $rootItems = API::fetchRoot($resourceId, $shareId);
            if (is_array($rootItems)) {
                $allItems = $rootItems;
            }
        } else {
            // 🟩 Load multiple folders
            foreach ($selectedFolders as $folder) {
                $items = API::fetchSubfolder($resourceId, $shareId, $folder);
                if (is_array($items)) {
                    $files = API::filterFiles($items);
                    $allItems = array_merge($allItems, $files);
                }
            }
        }


        /** --------------------------------------------------------------
         * 4) KEEP FILES ONLY
         * --------------------------------------------------------------
         */
        $files = API::filterFiles($allItems);


        /**
         * --------------------------------------------------------------
         * 5) NORMALIZE FILE DATA FOR RENDERER
         * --------------------------------------------------------------
         */
        $files = array_map(static function ($file): array {

            $name = $file['fileName'] ?? '';
            $resourceUrl = $file['resourceURL'] ?? '';

            // Extract actual file ID from URL
            $downloadUrl = str_replace('/files/', '/download/', $resourceUrl);

            return [
                'name' => $name,
                'url' => $downloadUrl,
                'type' => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
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

        $sort = strtolower($atts['sort']) === 'desc' ? 'desc' : 'asc';

        usort($files, static function ($a, $b) use ($sort): int {
            $valA = strtolower($a['name'] ?? '');
            $valB = strtolower($b['name'] ?? '');

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
            $title = trim((string)$atts['changetitle']);

            if ($title === '') {
                if (!empty($selectedFolders)) {
                    $title = rawurldecode((string)$selectedFolders[0]);
                } else {
                    $title = 'FAUbox';
                }
            }

            $output .= Renderer::renderTitle($title);
        }


        // Render files
        $output .= Renderer::render($files, [
            'view' => $atts['view'],
            'show' => $atts['show'],
        ]);

        return $output;
    }

    /**
     * Normalizes the "show" attribute to valid table/list columns.
     *
     * Accepts Gutenberg arrays or comma-separated shortcode strings.
     * Always falls back to ['name'] if no valid entries were provided.
     *
     * @param mixed $rawShow
     * @return array
     */
    private static function normalizeShowAttributes($rawShow): array
    {
        if (is_string($rawShow)) {
            $decoded = json_decode($rawShow, true);
            if (is_array($decoded)) {
                $rawShow = $decoded;
            } else {
                $rawShow = preg_split('/[;,|]/', $rawShow) ?: [];
            }
        }

        if (!is_array($rawShow)) {
            $rawShow = [];
        }

        $allowed = ['name', 'type'];
        $normalized = [];

        foreach ($rawShow as $column) {
            $column = strtolower(trim((string)$column));
            if ($column === '' || !in_array($column, $allowed, true)) {
                continue;
            }

            if (!in_array($column, $normalized, true)) {
                $normalized[] = $column;
            }
        }

        if (empty($normalized)) {
            $normalized = ['name'];
        }

        return $normalized;
    }

    /**
     * Normalizes the selected folder input from shortcode/block attributes.
     *
     * Accepts array attributes (Gutenberg) as well as comma separated strings
     * (shortcode usage) and ensures folders are URL encoded as expected by the API.
     *
     * @param mixed $rawFolders
     * @return array
     */
    private static function normalizeSelectedFolders($rawFolders): array
    {
        if (is_string($rawFolders)) {
            $decoded = json_decode($rawFolders, true);
            if (is_array($decoded)) {
                $rawFolders = $decoded;
            } else {
                $rawFolders = preg_split('/[;,|]/', $rawFolders) ?: [];
            }
        }

        if (!is_array($rawFolders)) {
            $rawFolders = [];
        }

        $normalized = [];

        foreach ($rawFolders as $folder) {
            $folder = trim((string)$folder);
            if ($folder === '') {
                continue;
            }

            $segments = array_map(static function (string $segment): string {
                $decoded = rawurldecode($segment);
                return rawurlencode($decoded);
            }, explode('/', $folder));

            $normalized[] = implode('/', $segments);
        }

        return array_values(array_unique($normalized));
    }
}
