<?php

declare(strict_types=1);

namespace RRZE\FAUbox;

use RRZE\FAUbox\API;
use RRZE\FAUbox\Renderer;

defined('ABSPATH') || exit;

/**
 * Shortcode handler for [faubox].
 *
 * Now fully based on the FAUbox /wapi/filelink public API.
 */
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
     * Render shortcode output.
     *
     * @param array $atts
     * @param string|null $content
     * @return string
     */
    public static function render(array $atts = [], ?string $content = null): string
    {
        // 1) Load share link from settings
        $shareLink = get_option('rrze_faubox_sharelink', '');

        if (empty($shareLink)) {
            return '<p><strong>' . esc_html__('FAUbox share link is missing in settings.', 'rrze-faubox') . '</strong></p>';
        }

        // 2) Extract Share-ID
        $shareId = API::resolveShareIdFromUrl($shareLink);

        if (!$shareId) {
            return '<p><strong>' . esc_html__('Invalid FAUbox share link format.', 'rrze-faubox') . '</strong></p>';
        }

        // 3) Resolve Resource-ID
        $resourceId = API::resolveResourceId($shareId);

        if (!$resourceId) {
            return '<p><strong>' . esc_html__('Unable to resolve FAUbox resource ID.', 'rrze-faubox') . '</strong></p>';
        }


        // 4) Merge default shortcode attributes
        $atts = shortcode_atts([
            'index' => '',         // Subfolder
            'view' => 'list',
            'show' => ['name'],
            'show_title' => false,
            'filetype' => [],
            'sort' => 'asc',
            'orderby' => 'name',
            'changeTitle' => '',
        ], $atts, 'faubox');

        // Normalize subfolder
        $subdir = trim((string) $atts['index'], '/');


        // 5) Fetch items from FAUbox
        $items = ($subdir === '')
            ? API::fetchRoot($resourceId, $shareId)
            : API::fetchSubfolder($resourceId, $shareId, $subdir);

        if (!is_array($items) || empty($items)) {
            return '<p><strong>' . esc_html__('No files found.', 'rrze-faubox') . '</strong></p>';
        }

        // Extract files only
        $folders = API::filterFolders($items);
        $files = API::filterFiles($items);



        // 6) Filetype filters (pdf, jpg, png, etc.)
        $allowedTypes = is_array($atts['filetype']) ? $atts['filetype'] : [];
        $allowedTypes = array_map('strtolower', $allowedTypes);

        if (!empty($allowedTypes)) {
            $files = array_filter($files, static function ($file) use ($allowedTypes) {

                // Extract file extension
                $name = $file['fileName'] ?? '';
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                return in_array($ext, $allowedTypes, true);
            });
        }


        // 7) Transform items for Renderer
        $files = array_map(static function ($file): array {

            $name = $file['fileName'] ?? '';
            $downloadUrl = $file['resourceURL'] ?? '';

            // Determine extension type
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            return [
                'name' => $name,
                'url'  => $downloadUrl,
                'type' => $ext,
                'name_raw' => strtolower($name),
                'type_raw' => $ext,

            ];
        }, $files);


        // 8) Sorting
        $orderby = $atts['orderby'];
        $sort = strtolower($atts['sort']) === 'desc' ? 'desc' : 'asc';

        usort($files, static function ($a, $b) use ($orderby, $sort): int {

            $valA = $a[$orderby . '_raw'] ?? $a[$orderby] ?? '';
            $valB = $b[$orderby . '_raw'] ?? $b[$orderby] ?? '';

            if ($valA === $valB) {
                return 0;
            }

            return ($sort === 'asc')
                ? (($valA < $valB) ? -1 : 1)
                : (($valA < $valB) ? 1 : -1);
        });


        // 9) Optional folder title
        $output = '';
        if (!empty($atts['changeTitle'])) {
            $title = Renderer::renderTitle($atts['changeTitle']);
        }


        // 10) Render
        $output = '';

        if (!empty($folders)) {
            $output .= Renderer::renderFolders($folders, [
                'folder' => $subdir ?: '/',
            ]);
        }


        /* render before data */
        if (!empty($folders)) {
            $output .= Renderer::renderFolders($folders, [
                'folder' => $subdir ?: '/',
            ]);
        }

        /* render data*/
        $output .= Renderer::render($files, [
            'view' => $atts['view'],
            'show' => $atts['show'],
            'folder' => $subdir ?: '/',
        ]);


        return $output;
    }
}


