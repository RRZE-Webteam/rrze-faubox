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
            'index' => '',
            'view' => 'list',
            'show' => ['name'],
            'filetype' => [],
            'sort' => 'asc',
            'show_title' => false,
            'changeTitle' => '',
            'selectedFolders' => [],

        ], $atts, 'faubox');

        $folderName = trim((string)$atts['index']);
        $selectedFolders = array_map('strval', (array)$atts['selectedFolders']);

        $atts['show_title'] = filter_var($atts['show_title'], FILTER_VALIDATE_BOOLEAN);

        /**
         * --------------------------------------------------------------
         * 3) API LOAD (ROOT OR SUBFOLDER)
         * --------------------------------------------------------------
         */
        $allItems = [];

        // load selected folders
        foreach ($selectedFolders as $folder) {
            $items = API::fetchSubfolder($resourceId, $shareId, $folder);

            if (is_array($items)) {
                // keep only files
                $filesOnly = API::filterFiles($items);

                $allItems = array_merge($allItems, $filesOnly);
            }
        }


        // If nothing is selected: Load root (fallback)
        if (empty($selectedFolders)) {
            $allItems = API::fetchRoot($resourceId, $shareId) ?? [];
        }

        /**
         * --------------------------------------------------------------
         * 4) SPLIT INTO FILES
         * --------------------------------------------------------------
         */
        $files = API::filterFiles($allItems);


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
            $downloadUrl = str_replace('/files/', '/download/', $resourceUrl);

            return [
                'name' => $name,
                'url' => $downloadUrl, //Downl
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
            $title = trim((string)$atts['changeTitle']);
            if ($title === '') {
                if (!empty($selectedFolders)) {
                    $title = rawurldecode((string)$selectedFolders[0]);
                } elseif ($folderName !== '') {
                    $title = $folderName;
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
}
