<?php

declare(strict_types=1);

namespace RRZE\FAUbox\Blocks;

defined('ABSPATH') || exit;

/**
 * Handles the Registration & Localization of the FAUbox block.
 */
class BlockRegistration
{
    private BlockRender $blockRender;

    /**
     * Bootstraps hooks for this component.
     */
    public function __construct(BlockRender $blockRender)
    {
        $this->blockRender = $blockRender;

        add_filter('block_categories_all', [$this, 'addRrzeCategory'], 10, 2);
        add_action('init', [$this, 'registerBlock']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);

    }


    /**
     * Register Block
     */
    public function registerBlock(): void
    {
        $block = register_block_type(
            dirname(__DIR__, 2) . '/build/block', ['render_callback' => [$this->blockRender, 'output']]
        );

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
     * Enqueue frontend assets for the FAUbox block.
     *
     * Only loads the table sort script when the block is present on the current page.
     */
    public function enqueueFrontendAssets(): void
    {
        if (!has_block('rrze/faubox')) {
            return;
        }
        wp_enqueue_script(
            'faubox-table-sort',
            RRZE_FAUBOX_URL . 'assets/js/faubox-table-sort.js',
            [],
            '1.0.0',
            true
        );
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
