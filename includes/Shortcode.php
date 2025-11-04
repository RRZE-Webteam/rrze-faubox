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
            'show_title' => 'true',
            'filetype' => '',
            'order' => '',
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
        $showTitle = !in_array(strtolower((string)$atts['show_title']), ['0', 'false', 'no', 'off'], true);
        $order = strtolower(sanitize_text_field($atts['order']));
        if (!in_array($order, ['asc', 'desc'], true)) {
            $order = 'asc'; // fallback default
        }

        //Erlaubte Spalten
        // Accept array (from block) OR comma-separated string (from shortcode)
        $showFields = is_array($atts['show']) //
            ? array_map('strtolower', array_map('trim', $atts['show']))
            : array_map('trim', explode(',', strtolower($showInput)));
        $allowed = ['name', 'size', 'type', 'modified'];
        $showFields = array_values(array_intersect($showFields, $allowed));
        if (empty($showFields)) {
            $showFields = ['name'];
        }

        // Get data from API (currently dummy)
        $files = API::fetchFiles($folderId, $token, $subdir);

        if (empty($files)) {
            return '<p><strong>' . esc_html__('No files found.', 'rrze-faubox') . '</strong></p>';
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
            ];
        }, $files);

        $files = $transformedFiles;

        // Optional: Ordner-Titel (Subdir oder Root-Ordner anzeigen)
        $folderTitle = $subdir ?: 'FAUbox'; // später von API ersetzen
        $titleHtml = $showTitle ? Renderer::renderTitle($folderTitle) : '';


        // Filter files by extension if requested
        if (!empty($filetype)) {
            $expectedExtension = strtolower($filetype);
            $filtered = [];

            foreach ($files as $item) {
                $filename = $item['name'] ?? '';
                $extension = pathinfo($filename, PATHINFO_EXTENSION);

                if (strtolower($extension) === $expectedExtension) {
                    $filtered[] = $item;
                }
            }

            $files = $filtered; // replace original list with the filtered one

            // If filtering removed all files, show the empty state (user friendly)
            if (empty($files)) {
                return '<p><strong>' . esc_html__('No files found.', 'rrze-faubox') . '</strong></p>';
            }
        }

        // Sort files alphabetically by fileName
        usort($files, static function ($a, $b) use ($order): int {
            return $order === 'asc'
                ? strcmp($a['name'], $b['name'])
                : strcmp($b['name'], $a['name']);
        });


        // HTML-Ausgabe rendern
        $listHtml = Renderer::render($files, [
            'view' => $view,
            'show' => $showFields,
            'folder' => $subdir ?: '/',
        ]);

// Step 8: Combine title + list output
        return $titleHtml . $listHtml;
    }
}
