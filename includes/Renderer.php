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
     * Renders title for folder block.
     *
     * @param string $folderName
     * @return string
     */
    public static function renderTitle(string $folderName): string
    {
        return sprintf('<h3 class="wp-block-heading faubox-title">%s</h3>', esc_html($folderName));
    }


    /**
     * Renders folder list (before files).
     *
     * @param array $folders
     * @param array $atts
     * @return string
     */
    public static function renderFolders(array $folders, array $atts = []): string
    {
        if (empty($folders)) {
            return '';
        }

        $currentFolder = $atts['folder'] ?? '/';

        $html = '<div class="faubox-folders">';
        $html .= sprintf(
            '<p class="faubox-folders__intro">%s</p>',
            sprintf(
                esc_html__('Folders available in %s:', 'rrze-faubox'),
                esc_html($currentFolder)
            )
        );

        $html .= '<ul class="faubox-folder-list">';

        foreach ($folders as $folder) {
            $name = esc_html($folder['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $path = $folder['path'] ?? '';

            $html .= sprintf(
                '<li class="faubox-folder"><strong>%s</strong>%s</li>',
                $name,
                $path !== '' ? ' <span class="faubox-folder-path">' . esc_html($path) . '</span>' : ''
            );
        }

        $html .= '</ul>';
        $html .= '</div>';

        return $html;
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
            return '<p>' . esc_html__('No files found.', 'rrze-faubox') . ' </p>'; // 🟢
        }

        $html = '<ul class="wp-block-list wp-block-faubox-list">';

        foreach ($data as $file) {
            $name = esc_html($file['name'] ?? '');
            $url = esc_url($file['url'] ?? '');

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
                '<li><a href="%s" target="_blank" rel="noopener">%s</a>%s</li>',
                $url,
                $name,
                $suffix
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

        $html = '<figure class="wp-block-table"><table class="faubox-filetable">';
        $html .= '<thead><tr>';

        foreach ($columns as $col) {
            $html .= '<th>' . esc_html($col) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($data as $file) {
            $name = esc_html($file['name']);
            $url  = esc_url($file['url']);

            $html .= '<tr>';

            foreach ($columns as $col) {
                if ($col === 'name') {
                    $html .= sprintf(
                        '<td><a href="%s" target="_blank" rel="noopener">%s</a></td>',
                        $url,
                        $name
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
    /**
     * Renders image gallery.
     *
     * @param array $data
     * @param array $atts
     * @return string
     */
    private static function renderGallery(array $data, array $atts): string
    {
        wp_enqueue_script('faue-gallery-slider');
        wp_enqueue_script('image-fullscreen');

        if (empty($data)) {
            return '<p>' . esc_html__('No images found.', 'rrze-faubox') . '</p>';
        }

        // Editor SSR preview
        if (
            defined('REST_REQUEST')
            && REST_REQUEST
            && isset($_SERVER['REQUEST_URI'])
            && strpos($_SERVER['REQUEST_URI'], 'block-renderer') !== false
        ) {
            $count = 0;
            foreach ($data as $f) {
                $ext = strtolower(pathinfo($f['url'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','webp','avif'], true)) {
                    $count++;
                }
            }

            if ($count === 0) {
                return '<p><strong>' .
                    esc_html__('FAUbox gallery: no images available yet.', 'rrze-faubox') .
                    '</strong></p>';
            }

            return sprintf(
                '<p><strong>' .
                esc_html__('You create a FAUbox gallery with %d images.', 'rrze-faubox') .
                '</strong></p>',
                $count
            );
        }

        // Frontend rendering
        $columns = isset($atts['columns']) ? max(1, (int)$atts['columns']) : 3;

        $galleryClasses = [
            'wp-block-gallery',
            'faubox-gallery',
            'is-layout-flex',
            'has-nested-images',
            sprintf('columns-%d', $columns),
        ];

        self::$galleryInstance++;
        $html = sprintf(
            '<div class="wp-block-gallery-container gallery-%d"><figure class="%s">',
            self::$galleryInstance,
            esc_attr(implode(' ', $galleryClasses))
        );

        $index = 1;
        $total = count($data);

        foreach ($data as $file) {
            $url = $file['url'];
            $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));

            if (!in_array($ext, ['jpg','jpeg','png','gif','webp','avif'], true)) {
                continue;
            }

            $html .= sprintf(
                '<figure class="wp-block-image size-full has-overlay">
                    <div class="image-wrapper">
                        <img src="%s" alt=""/>
                        <span class="gallery-index-display">%d/%d</span>
                        <button class="image-fullscreen-btn" onclick="openImageFullscreen(\'%s\')">⛶</button>
                    </div>
                </figure>',
                esc_url($url),
                $index,
                $total,
                esc_url($url)
            );

            $index++;
        }

        $html .= '</figure></div>';
        return $html;
    }
}
