<?php

namespace RRZE\FAUbox\Blocks;

defined( 'ABSPATH') || exit;


/**
 * Handles the Registration, Localization of the FAUbox block
 */
class BlockRegistration {

    /**
     * Bootstraps hooks for this component.
     * Call this once (e.g., from your plugin Main/Loader).
     */
    public static function register(): void
    {
        // 🟢 Register hooks explicitly (no constructor side effects).
        add_action('init', [self::class, 'fauboxBlockInit'], 15);
        add_filter('block_categories_all', [self::class, 'addRrzeCategory'], 10, 2);

        add_action('init', function () {
            wp_set_script_translations(
                'rrze-faubox-editor', // ← HANDLE des Editor-Skripts
                'rrze-faubox',
                plugin_dir_path(__DIR__) . '../languages'
            );
        });

    }

    /**
     * Registers the Faubox Block and initiates the l10n.
     *
     * It registers the Blocks and adds the required translation files for the
     * block.
     */
    public static function fauboxBlockInit(): void
    {
        self::fauboxRegisterBlocks();

    }

    /**
     * Register the Block
     */
    public static function fauboxRegisterBlocks(): void
    {
        $blockPath = dirname(__DIR__, 2) . '/build/block';
        register_block_type(
            $blockPath,
            [ 'render_callback' => [ BlockRender::class, 'output' ] ]
        );
    }

    /**
     * Adds custom block category if not already present.
     *
     * @param array $categories
     * @param $post
     * @return array
     */
    public static function addRrzeCategory (array $categories, $post): array
    {
        // Check if there is already a RRZE category present
        foreach ($categories as $category) {
            if (isset($category['slug']) && $category['slug'] === 'rrze') {
                return $categories;
            }
        }

        $customCategory = [
            'slug'  => 'rrze',
            'title' => __('RRZE', 'rrze-faubox'),
        ];

        $categories[] = $customCategory;

        return $categories;
    }





}