<?php

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
    public static function register(): void
    {
        add_filter('block_categories_all', [self::class, 'addRrzeCategory'], 10, 2);
        add_action('init', [self::class, 'registerBlock']);
    }


    /**
     * Register Block
     */
    public static function registerBlock(): void
    {

        error_log(print_r('rrze-faubox', true));
        Helper::debug('testlauf');
        Helper::debug(__DIR__ . '/build/block');
        Helper::debug(dirname(__DIR__, 2) . '/build/block');

        $block = register_block_type(
            dirname(__DIR__, 2) . '/build/block',
            [
                'render_callback' => [\RRZE\FAUbox\Blocks\BlockRender::class, 'output'],
            ]
        );


        if ($block && !empty($block->editor_script)) {
            wp_set_script_translations(
                $block->editor_script,
                'rrze-faubox',
                dirname(__DIR__, 2) .  '/languages'
            );
        }
    }


    /**
     * Adds the RRZE block category (if not present).
     */
    public static function addRrzeCategory(array $categories, $post): array
    {
//        Helper::debug('rrze-categories');
//        Helper::debug($categories);

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
