<?php

declare(strict_types=1);

namespace RRZE\FAUbox\Blocks;

use RRZE\FAUbox\Helper;

defined('ABSPATH') || exit;

/**
 * Handles the Registration & Localization of the FAUbox block.
 */
class BlockRegistration
{
    /**
     * Bootstraps hooks for this component.
     */
    public function __construct()
    {
        add_filter('block_categories_all', [$this, 'addRrzeCategory'], 10, 2);
        add_action('init', [$this, 'registerBlock']);
    }


    /**
     * Register Block
     */
    public function registerBlock(): void
    {
        $block = register_block_type(dirname(__DIR__, 2) . '/build/block', ['render_callback' => [\RRZE\FAUbox\Blocks\BlockRender::class, 'output'],]);

        if ($block && !empty($block->editor_script)) {
            wp_set_script_translations(
                $block->editor_script,
                'rrze-faubox',
                dirname(__DIR__, 2) . '/languages'
            );
            wp_localize_script($block->editor_script, 'rrze_faubox_data', [
                'settings_url' => admin_url('options-general.php?page=rrze-faubox'),
                'folder' => get_option('rrze_faubox_folder', ''),
            ]);
        }
    }


    /**
     * Adds the RRZE block category (if not present).
     */
    public function addRrzeCategory(array $categories, $post): array
    {
        foreach ($categories as $category) {
            if ($category['slug'] === 'rrze') {
                return $categories;
            }
        }

        $categories[] = [
            'slug' => 'rrze',
            'title' => __('RRZE', 'rrze-faubox'),
        ];

        return $categories;
    }
}
