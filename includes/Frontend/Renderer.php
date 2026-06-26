<?php

declare(strict_types=1);

namespace RRZE\FAUbox\Frontend;

use RRZE\FAUbox\Helper;

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
     * Renders the folder title using the configured heading level.
     *
     * @param string $folderName The folder name to display.
     * @param string $tag The HTML heading tag (h2–h5).
     * @return string Rendered heading HTML.
     */
    public static function renderTitle(string $folderName, string $tag = 'h3'): string
    {
        $allowed = ['h2', 'h3', 'h4', 'h5'];
        $tag = in_array($tag, $allowed, true) ? $tag : 'h3';
        return sprintf('<%1$s class="wp-block-heading faubox-title">%2$s</%1$s>',
            $tag,
            esc_html($folderName)
        );
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
        $html = '';

        if (!empty($atts['show_title'])) {
            $title = !empty($atts['changetitle'])
                ? $atts['changetitle']
                : basename((string)($atts['path'] ?? ''));
            $html .= self::renderTitle($title, $atts['heading_level'] ?? 'h3');
        }

        if (empty($data)) {
            return $html . '<p>' . esc_html__('No files found.', 'rrze-faubox') . '</p>';
        }

        $html .= '<ul class="wp-block-list wp-block-faubox-list">';

        foreach ($data as $file) {
            $name = esc_html($file['name'] ?? '');
            $url = esc_url($file['url'] ?? '');
            $show = isset($atts['show']) && is_array($atts['show']) ? $atts['show'] : [];
            $suffix = '';

            if (in_array('type', $show, true)) {
                $ext = strtoupper(pathinfo($name, PATHINFO_EXTENSION));
                $suffix .= ' (' . esc_html($ext) . ')';
            }
            if (in_array('size', $show, true) && !empty($file['size'])) {
                $suffix .= ', ' . esc_html($file['size']);
            }
            if (in_array('modified', $show, true) && !empty($file['modified'])) {
                $suffix .= ', ' . esc_html($file['modified']);
            }

            $html .= sprintf('<li><a href="%s" target="_blank" rel="noopener noreferrer">%s<span class="screen-reader-text"> ' . esc_html__('(opens in new tab)', 'rrze-faubox') . '</span></a>%s</li>',
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
        $html = '';

        if (!empty($atts['show_title'])) {
            $title = !empty($atts['changetitle'])
                ? $atts['changetitle']
                : basename((string)($atts['path'] ?? ''));
            $html .= self::renderTitle($title, $atts['heading_level'] ?? 'h3');

        }

        if (empty($data)) {
            return $html . '<p>' . esc_html__('No files found.', 'rrze-faubox') . '</p>';
        }

        $columnOrder = ['name', 'size', 'modified', 'type'];
        $columns = array_values(array_intersect($columnOrder, $atts['show'] ?? ['name']));

        // Load column labels from Helper class
        $columnLabels = Helper::getColumnLabels();

        $folderName = !empty($atts['changetitle']) ? $atts['changetitle'] : basename((string)($atts['path'] ?? ''));
        $html .= '<figure class="wp-block-table"><table class="faubox-filetable">';
        $caption = !empty($folderName) ? $folderName : __('FAUbox files', 'rrze-faubox');
        $html .= '<caption class="screen-reader-text">' . esc_html($caption) . '</caption>';

        $html .= '<thead><tr>';

        foreach ($columns as $col) {
            $label = $columnLabels[$col] ?? $col;
            $style = $col === 'name' ? ' style="width:100%"' : ' style="white-space:nowrap"';
            $html .= '<th scope="col"' . $style . '>' . esc_html($label) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($data as $file) {
            $name = esc_html($file['name']);
            $url = esc_url($file['url']);

            $html .= '<tr>';
            foreach ($columns as $col) {
                if ($col === 'name') {
                    $html .= sprintf(
                        '<td><a href="%s" target="_blank" rel="noopener noreferrer">%s<span class="screen-reader-text"> ' . __('(opens in new tab)', 'rrze-faubox') . '</span></a></td>',
                        $url,
                        $name
                    );
                } elseif ($col === 'type') {
                    $ext = strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $html .= '<td>' . esc_html($ext) . '</td>';
                } elseif ($col === 'size') {
                    $html .= '<td>' . esc_html($file['size'] ?? '—') . '</td>';
                } elseif ($col === 'modified') {
                    $html .= '<td>' . esc_html($file['modified'] ?? '—') . '</td>';
                }
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table></figure>';

        return $html;
    }

}
