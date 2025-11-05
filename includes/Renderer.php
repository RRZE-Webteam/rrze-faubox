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
     * @param string $folderName The name or identifier of the folder.
     * @return string The rendered HTML heading element.
     */
    public static function renderTitle(string $folderName): string
    {
        return sprintf('<h3 class="wp-block-heading faubox-title">%s</h3>', esc_html($folderName));
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

        $html = '<ul class="wp-block-faubox-list">';

        foreach ($data as $file) {
            $name = esc_html($file['name']);
            $details = [];

            // size anzeigen, wenn in show enthalten
            if (isset($atts['show']) && is_array($atts['show']) && in_array('size', $atts['show'], true)) {
                $details[] = esc_html($file['size'] ?? '-');
            }

            // type anzeigen, wenn in show enthalten
            if (isset($atts['show']) && is_array($atts['show']) && in_array('type', $atts['show'], true)) {
                $extension = pathinfo($file['url'], PATHINFO_EXTENSION);
                $details[] = esc_html(strtoupper($extension));
            }

            // modified anzeigen, wenn in show enthalten
            if (isset($atts['show']) && is_array($atts['show']) && in_array('modified', $atts['show'], true)) {
                $modified = $file['modified'] ?? '';
                $formatted = $modified ? date('d.m.Y', strtotime($modified)) : '-';
                $details[] = esc_html($formatted);
            }

            // Klammer nur anzeigen, wenn mind. ein Zusatzfeld aktiv ist
            $suffix = $details ? ' (' . implode(', ', $details) . ')' : '';

            $html .= sprintf(
                '<li><a href="%s" target="_blank" rel="noopener">%s</a>%s</li>',
                esc_url($file['url']),
                $name,
                $suffix
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

        $html = '<figure class="wp-block-table"><table class="faubox-filetable">';
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

        $html .= '</tbody></table></figure>';

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

        $html = '<ul class="wp-block-gallery faubox-gallery columns-3 is-cropped">';

        foreach ($data as $file) {
            // Check: is file an image (by extension)
            $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file['url']);
            if (! $isImage) {
                continue;
            }

            $html .= sprintf(
                '<li class="blocks-gallery-item">
                <figure>
                    <a href="%s" target="_blank" rel="noopener">
                        <img src="%s" alt="%s" />
                    </a>
                </figure>
            </li>',
                esc_url($file['url']),
                esc_url($file['url']),
                esc_attr($file['name'])
            );
        }

        $html .= '</ul>';

        return $html;
    }
}
