<?php

declare(strict_types=1);

namespace RRZE\FAUbox;

use RRZE\FAUbox\API;
use RRZE\FAUbox\Renderer;

defined('ABSPATH') || exit;

/**
 * Shortcode handler for [faubox].
 *
 * Usage example:
 * [faubox folder="abc123" view="list"]
 */
class Shortcode
{
    /**
     * Registers the shortcode
     */
    public static function register(): void
    {
        add_shortcode('faubox', [self::class, 'render']);
    }


    /**
     * Handles the shortcode rendering logic.
     *
     * @param array $atts Shortcode attributes.
     * @param string|null $content Optional enclosed content.
     *
     * @return string HTML output
     */
    public static function render(array $atts = [], ?string $content = null): string
    {
        // Default attributes
        $atts = shortcode_atts([
            'index' => '', //subfile of FAUbox file
            'view' => 'list',
            'show' => 'name', //Default
            'show_title' => 'false',
            'filetype' => '',
            'sort' => 'asc',
            'orderby' => 'name',

        ],
            $atts,
            'faubox'
        );

        // Token und Ordner-ID aus den Plugin-Settings laden
        $token = get_option('rrze_faubox_token', '');
        $folderId = get_option('rrze_faubox_folder', '');
        $subdir = sanitize_text_field($atts['index']);


        // 🟩 Fallbacks aktivieren, wenn keine echten Werte da sind
        if (empty($token)) {
            $token = 'dummy-token';
        }

        if (empty($folderId)) {
            $folderId = 'dummy-folder';
        }

        // Sanitize inputs
        $view = sanitize_text_field($atts['view']);
        $showInput = sanitize_text_field($atts['show']);
        $filetype = sanitize_text_field($atts['filetype']);
        $showTitle = filter_var($atts['show_title'], FILTER_VALIDATE_BOOLEAN);
        $sort = strtolower(sanitize_text_field($atts['sort']));
        if (!in_array($sort, ['asc', 'desc'], true)) {
            $sort = 'asc'; // fallback default
        }
        $orderby = sanitize_text_field($atts['orderby']);
        $allowedOrderBy = ['name', 'size', 'type', 'modified'];
        if (!in_array($orderby, $allowedOrderBy, true)) {
            $orderby = 'name';
        }

        //Get files from API
        $files = API::fetchFiles($folderId, $token, $subdir);
        $files = is_array($files) ? $files : []; // 🟩 Schutz vor null → leeres Array

        if (empty($files)) {
            return '<p><strong>' . esc_html__('No files found.', 'rrze-faubox') . '</strong></p>';
        }

        //Filter by custom file extensions (e.g. pdf, docx)
        if (is_array($filetype) && !empty($filetype)) {
            $allowedExtensions = array_map('strtolower', array_map('trim', $filetype));

            $files = array_filter($files, static function ($item) use ($allowedExtensions): bool {
                $filename = $item['name'] ?? '';
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                return in_array($extension, $allowedExtensions, true);
            });
        }


        // Accept array (from block) OR comma-separated string (from shortcode)
        $showFields = is_array($atts['show']) //
            ? array_map('strtolower', array_map('trim', $atts['show']))
            : array_map('trim', explode(',', strtolower($showInput)));
        $allowed = ['name', 'size', 'type', 'modified'];
        $showFields = array_values(array_intersect($showFields, $allowed));
        if (empty($showFields)) {
            $showFields = ['name'];
        }

        // Transform API data to match Renderer expectations
        $transformedFiles = array_map(function ($file) use ($folderId, $subdir) {
            // Dummy URL - später durch echte Download-URL ersetzen
            $downloadUrl = sprintf(
                'https://faubox.fau.de/dl/%s/%s/%s',
                $folderId,
                trim($subdir, '/'),
                urlencode($file['fileName'])
            );

            return [
                'name' => $file['fileName'],
                'url' => $downloadUrl,
                'size' => size_format($file['fileSize']),
                'type' => $file['mimeType'],
                'modified' => $file['lastModified'],
                'name_raw' => strtolower($file['fileName']),
                'size_raw' => (int)($file['fileSize'] ?? 0),
                'type_raw' => strtolower($file['mimeType'] ?? ''),
                'modified_ts' => strtotime($file['lastModified'] ?? ''),
            ];
        }, $files);

        $files = $transformedFiles;

        // Render folder title (optional)
        $folderTitle = basename(trim($subdir));
        $titleHtml = ($showTitle && !empty($folderTitle))
            ? Renderer::renderTitle($folderTitle)
            : '';



        // Sort files
        usort($files, static function ($a, $b) use ($sort, $orderby): int {
            switch ($orderby) {
                case 'size':
                    $valueA = $a['size_raw'] ?? 0;
                    $valueB = $b['size_raw'] ?? 0;
                    break;
                case 'modified':
                    $valueA = $a['modified_ts'] ?? 0;
                    $valueB = $b['modified_ts'] ?? 0;
                    break;
                case 'type':
                    $valueA = $a['type_raw'] ?? '';
                    $valueB = $b['type_raw'] ?? '';
                    break;
                case 'name':
                default:
                    $valueA = $a['name_raw'] ?? '';
                    $valueB = $b['name_raw'] ?? '';
                    break;
            }

            if ($valueA === $valueB) {
                return 0;
            }

            if ($sort === 'desc') {
                return ($valueA < $valueB) ? 1 : -1;
            }

            return ($valueA < $valueB) ? -1 : 1;
        });


        // Render HTML
        $listHtml = Renderer::render($files, [
            'view' => $view,
            'show' => $showFields,
            'folder' => $subdir ?: '/',
        ]);

// Step 8: Combine title + list output
        return $titleHtml . $listHtml;
    }
}
