<?php

declare(strict_types=1);

namespace RRZE\FAUbox;

defined('ABSPATH') || exit;

class Renderer
{
    /**
     * Render file list as HTML output based on view type.
     *
     * @param array $data The file list array.
     * @param array $atts Attributes from shortcode or block.
     * @return string Rendered HTML.
     */
    public static function render(array $data, array $atts): string
    {
        $view = $atts['view'] ?? 'list'; //wenn kein Attribut view, dann Starndard Liste

        switch ($view) {
            case 'table':
                return self::renderTable($data, $atts);
            case 'gallery':
                return self::renderGallery($data, $atts);
            case 'list':
            default:
                return self::renderList($data, $atts);
        }
    }

    /**
     * Renders a heading with the given folder name.
     *
     * This method outputs a consistent HTML heading (h2)
     * for use above the file list, regardless of the chosen view type.
     * It ensures the folder name is safely escaped for HTML output.
     *
     * @param string $folderName The name or identifier of the folder.
     * @return string The rendered HTML heading element.
     */
    public static function renderTitle(string $folderName): string
    {
        return sprintf('<h4 class="faubox-title">%s</h4>', esc_html($folderName));
    }


    /**
     * Render as unordered list.
     *
     * @param array $data
     * @return string
     */
    private static function renderList(array $data, array $atts = []): string
    {
        if (empty($data)) {
            return '<p>No files found.</p>';
        }

        $html = '<ul class="faubox-filelist">';

        foreach ($data as $file) {
            $html .= sprintf(
                '<li><a href="%s" target="_blank" rel="noopener">%s</a></li>',
                esc_url($file['url']),
                esc_html($file['name'])
            );
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Render as HTML table.
     *
     * @param array $data
     * @return string
     */
    private static function renderTable(array $data, array $atts = []): string
    {
        if (empty($data)) {
            return '<p>No files found.</p>';
        }

        $columns = $atts['show'] ?? ['name']; // fallback: nur Name

        $html = '<table class="faubox-filetable">';
        $html .= '<thead><tr>';

        foreach ($columns as $col) {
            switch ($col) {
                case 'name':
                    $html .= '<th>' . esc_html__('name', 'rrze-faubox') . '</th>';
                    break;
                case 'size':
                    $html .= '<th>' . esc_html__('size', 'rrze-faubox') . '</th>';
                    break;
                case 'type':
                    $html .= '<th>' . esc_html__('type', 'rrze-faubox') . '</th>';
                    break;
                case 'modified':
                    $html .= '<th>' . esc_html__('changed', 'rrze-faubox') . '</th>';
                    break;
            }
        }

        $html .= '</tr></thead>';
        $html .= '<tbody>';


        foreach ($data as $file) {
            $html .= '<tr>';

            foreach ($columns as $col) {
                switch ($col) {
                    case 'name':
                        $html .= sprintf(
                            '<td><a href="%s" target="_blank" rel="noopener">%s</a></td>',
                            esc_url($file['url']),
                            esc_html($file['name'])
                        );
                        break;

                    case 'size':
                        $html .= sprintf('<td>%s</td>', esc_html($file['size'] ?? '-'));
                        break;

                    case 'type':
                        // Dateityp aus URL ableiten (Dateiendung)
                        $extension = pathinfo($file['url'], PATHINFO_EXTENSION);
                        $html .= sprintf('<td>%s</td>', esc_html(strtoupper($extension)));
                        break;

                    case 'modified':
                        $modified = $file['modified'] ?? null;
                        $formatted = $modified ? date('d.m.Y', strtotime($modified)) : '-';
                        $html .= sprintf('<td>%s</td>', esc_html($formatted));
                        break;

                }
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Render as image gallery (very simple version).
     *
     * @param array $data
     * @return string
     */
    private static function renderGallery(array $data, array $atts = []): string
    {
        if (empty($data)) {
            return '<p>No images found.</p>';
        }

        $html = '<div class="faubox-gallery">';

        foreach ($data as $file) {
            // Basic check: file extension as image
            $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file['url']);
            if (! $isImage) {
                continue;
            }

            $html .= sprintf(
                '<div class="gallery-item"><a href="%s" target="_blank" rel="noopener"><img src="%s" alt="%s"></a></div>',
                esc_url($file['url']),
                esc_url($file['url']),
                esc_attr($file['name'])
            );
        }

        $html .= '</div>';

        return $html;
    }
}
