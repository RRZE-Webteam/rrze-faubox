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

        $html = '<ul class="wp-block-list wp-block-faubox-list">';

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
     * Render image gallery in theme-compatible structure with navigation.
     *
     * @param array $data List of image file arrays.
     * @param array $atts Optional attributes like 'columns'.
     * @return string HTML output for gallery view.
     */
    private static function renderGallery(array $data, array $atts = []): string
    {
        if (empty($data)) {
            return '<p>No images found.</p>';
        }

        $columns = isset($atts['columns']) ? (int) $atts['columns'] : 3;
        if ($columns < 1) {
            $columns = 3;
        }

        $galleryClasses = [
            'wp-block-gallery',
            'faubox-gallery',
            'has-nested-images',
            'is-cropped',
            'is-layout-flex',
            sprintf('columns-%d', $columns),
            sprintf('has-%d-columns', $columns),
        ];

        $isPreview = ! empty($atts['is_preview']);

        if ($isPreview) {
            foreach ($data as $file) {
                $url = (string) ($file['url'] ?? $file['fileName'] ?? '');
                $name = (string) ($file['name'] ?? 'Image');

                if (! $url) {
                    continue;
                }

                // 🟢 Add wrapper only in preview to scope editor CSS
                return sprintf(
                    '<div class="wp-block-rrze-faubox"><div class="faubox-gallery-preview">
                <img src="%s" alt="%s" style="max-width: 100%%; height: auto;" />
            </div></div>',
                    esc_url($url),
                    esc_attr($name)
                );
            }

            return '<p>No preview image found.</p>';
        }

        // 🟢 Open outer container (required by theme)
        $html = '<div class="wp-block-gallery-container">';

        // 🟢 Open gallery <figure>
        $html .= sprintf(
            '<figure class="%s">',
            esc_attr(implode(' ', $galleryClasses))
        );

        $index = 1;
        $total = count($data);
        $itemsRendered = 0;

        foreach ($data as $file) {
            $url = (string) ($file['url'] ?? $file['fileName'] ?? '');
            if ($url === '') {
                continue;
            }

            $name = (string) ($file['name']
                ?? basename(parse_url($url, PHP_URL_PATH) ?: '')
                ?? 'Image');

            $type = (string) ($file['type'] ?? $file['mimeType'] ?? '');
            $isImageByMime = $type !== '' && str_starts_with($type, 'image/');
            $isImageByExt = (bool) preg_match('/\.(jpe?g|png|gif|webp|avif)$/i', $url);

            if (! ($isImageByMime || $isImageByExt)) {
                continue;
            }

            // 🟢 Each image as themed <figure> with overlay + navigation helpers
            $html .= sprintf(
                '<figure class="wp-block-image size-full is-style-large has-overlay">
                <div class="image-wrapper">
                    <img src="%s" alt="%s" />
                    <span class="gallery-index-display">%d/%d</span>
                    <button class="image-fullscreen-btn" onclick="openImageFullscreen(\'%s\')">⛶</button>
                </div>
            </figure>',
                esc_url($url),
                esc_attr($name),
                $index,
                $total,
                esc_url($url)
            );

            $index++;
            $itemsRendered++;
        }

        $html .= '</figure>';


        $html .= '</div>'; // Close gallery-container

        if ($itemsRendered === 0) {
            return '<p>No images found.</p>';
        }

        return $html;
    }



}
