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
            return '<p>' . esc_html__('No files found.', 'rrze-faubox') . '</p>';
        }

        $html = '<ul class="wp-block-list wp-block-faubox-list">';

        foreach ($data as $file) {
            $name = esc_html($file['name'] ?? '');
            $url = esc_url($file['url'] ?? '');

            $suffix = '';

            if (
                isset($atts['show']) &&
                is_array($atts['show']) &&
                in_array('type', $atts['show'], true)
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

        // Load column labels from Helper class
        $columnLabels = Helper::getColumnLabels();

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

}
