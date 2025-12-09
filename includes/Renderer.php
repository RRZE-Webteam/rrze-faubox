<?php

declare(strict_types=1);

namespace RRZE\FAUbox;

defined('ABSPATH') || exit;

class Renderer
{

    /**
     * Gallery instance counter to generate unique container classes.
     *
     * @var int
     */
    private static int $galleryInstance = 0;

    /**
     * Render file list as HTML output based on view type.
     *
     * @param array $data The file list array.
     * @param array $atts Attributes from shortcode or block.
     * @return string Rendered HTML.
     */
    public static function render(array $data, array $atts): string
    {
        $view = $atts['view'] ?? 'list';

        switch ($view) {
            case 'table':
                return self::renderTable($data, $atts);
            case 'list':
            default:
                return self::renderList($data, $atts);
        }
    }

    /**
     * Renders title for folder block.
     *
     * @param string $folderName
     * @return string
     */
    public static function renderTitle(string $folderName): string
    {
        return sprintf('<h3 class="wp-block-heading faubox-title">%s</h3>', esc_html($folderName));
    }


    private static function getPreviewIcon(): string
    {
        $path = plugin_dir_path(__DIR__, 2) . 'assets/svg/image_arrow_up.svg';

        if (file_exists($path)) {
            return file_get_contents($path);
        }

        return ''; // fallback
    }


    /**
     * Renders a simple list.
     *
     * @param array $data
     * @param array $atts
     * @return string
     */
    private static function renderList(array $data, array $atts = []): string
    {
        if (empty($data)) {
            return '<p>' . esc_html__('No files found.', 'rrze-faubox') . ' </p>';
        }

        $html = '<ul class="wp-block-list wp-block-faubox-list">';

        foreach ($data as $file) {
            $name = esc_html($file['name'] ?? '');
            $url = esc_url($file['url'] ?? '');


            // find images
            $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
            $imgExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];

            // preview link & icon for images
            if (in_array($ext, $imgExtensions, true)) {

                // Build preview link instead of download link
                $previewUrl = str_replace('/download/', '/dl/', $url) . '?inline';

                $previewIcon = sprintf(
                    '<a href="%s" target="_blank" preview class="faubox-preview-icon" title="%s" aria-label="%s">%s</a>',
                    esc_url($previewUrl),
                    esc_attr__('Preview', 'rrze-faubox'),
                    esc_attr__('Preview', 'rrze-faubox'),
                    self::getPreviewIcon()
                );
            } else {
                $previewIcon = '';
            }

            $suffix = '';

            if (
                isset($atts['show'])
                && is_array($atts['show'])
                && in_array('type', $atts['show'], true)
            ) {
                $ext = strtoupper(pathinfo($url, PATHINFO_EXTENSION));
                $suffix = ' (' . esc_html($ext) . ')';
            }

            $html .= sprintf(
                '<li><a href="%s" target="_blank" rel="noopener">%s</a>%s%s</li>',
                $url,
                $name,
                $suffix,
                $previewIcon
            );
        }


        $html .= '</ul>';

        return $html;
    }

    /**
     * Renders HTML table.
     *
     * @param array $data
     * @param array $atts
     * @return string
     */
    private static function renderTable(array $data, array $atts): string
    {
        if (empty($data)) {
            return '<p>' . esc_html__('No files found.', 'rrze-faubox') . '</p>';
        }

        $columns = $atts['show'] ?? ['name'];

        // 🟩 Mapping: internal column → translated label
        $columnLabels = [
            'name' => __('File Name', 'rrze-faubox'),
            'type' => __('Type', 'rrze-faubox'),

        ];

        $html = '<figure class="wp-block-table"><table class="faubox-filetable">';
        $html .= '<thead><tr>';

        foreach ($columns as $col) {
            $label = $columnLabels[$col] ?? $col;
            $html .= '<th>' . esc_html($label) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($data as $file) {
            $name = esc_html($file['name']);
            $url = esc_url($file['url']);

            // find images
            $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
            $imgExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

            // preview link & icon for images
            if (in_array($ext, $imgExtensions, true)) {

                // Build preview link instead of download link
                $previewUrl = str_replace('/download/', '/dl/', $url) . '?inline';

                $previewIcon = sprintf(
                    '<a href="%s" target="_blank" preview class="faubox-preview-icon" title="%s" aria-label="%s">%s</a>',
                    esc_url($previewUrl),
                    esc_attr__('Preview', 'rrze-faubox'),
                    esc_attr__('Preview', 'rrze-faubox'),
                    self::getPreviewIcon()
                );
            } else {
                $previewIcon = '';
            }

            $html .= '<tr>';

            foreach ($columns as $col) {
                if ($col === 'name') {
                    $html .= sprintf(
                        '<td><a href="%s" target="_blank" rel="noopener">%s</a>%s</td>',
                        $url,
                        $name,
                        $previewIcon
                    );
                } elseif ($col === 'type') {
                    $ext = strtoupper(pathinfo($url, PATHINFO_EXTENSION));
                    $html .= '<td>' . esc_html($ext) . '</td>';
                }
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table></figure>';

        return $html;
    }

}
