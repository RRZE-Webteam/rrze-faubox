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
        ],
            $atts,
            'faubox'
        );

        // Token und Ordner-ID aus den Plugin-Settings laden
        $token = get_option('rrze_faubox_token', '');
        $folderId = get_option('rrze_faubox_folder', '');
        $subdir = sanitize_text_field($atts['index']);

        // Validate folder ID
        if (empty($folderId)) {
            return '<p><strong>'. esc_html__('FAUbox: Token or folder ID not configured.', 'rrze-faubox').' </strong></p>';
        }

        // Sanitize inputs
        $view = sanitize_text_field($atts['view']);
        $showInput = sanitize_text_field($atts['show']);
        $filetype = sanitize_text_field($atts['filetype']);
        $showTitle = strtolower($atts['show_title']) !== 'false';


        //Erlaubte Spalten
        $showFields = array_map('trim', explode(',', strtolower($showInput)));
        $allowed = ['name', 'size', 'type', 'modified'];
        $showFields = array_values(array_intersect($showFields, $allowed));

        // Get data from API (currently dummy)
        $files = API::fetchFiles($folderId, $token, $subdir);

        if (!empty($files)) {
            return '<p><strong>Keine Dateien gefunden.</strong></p>';
        }

        // Optional: Ordner-Titel (Subdir oder Root-Ordner anzeigen)
        $folderTitle = $subdir ?: 'FAUbox'; // später von API ersetzen
        $titleHtml = $showTitle ? Renderer::renderTitle($folderTitle) : '';

// Dateien filtern nach Dateityp
        if (!empty($filetype)) {
            $files = array_filter($files, static function (array $item) use ($filetype): bool {
                $extension = strtolower(pathinfo($item['fileName'] ?? '', PATHINFO_EXTENSION));
                return $extension === strtolower($filetype);
            });
        }


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
