<?php

namespace RRZE\FAUbox\Blocks;

defined('ABSPATH') || exit;

/**
 * Handles the Registration & Localization of the FAUbox block.
 */
class BlockRegistration
{
    /**
     * Bootstraps hooks for this component.
     */
    public static function register(): void
    {
        add_action('init', [self::class, 'fauboxBlockInit'], 15);
        add_filter('block_categories_all', [self::class, 'addRrzeCategory'], 10, 2);
    }

    /**
     * Register block on init.
     */
    public static function fauboxBlockInit(): void
    {
        self::fauboxRegisterBlocks();
    }

    /**
     * Register the block and SSR.
     */
    public static function fauboxRegisterBlocks(): void
    {
        $blockPath = dirname(__DIR__, 2) . '/build/block';

        register_block_type(
            $blockPath,
            [
                'render_callback' => [BlockRender::class, 'output']
            ]
        );
    }

    /**
     * Adds the RRZE block category (if not present).
     */
    public static function addRrzeCategory(array $categories, $post): array
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
