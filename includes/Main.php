<?php

namespace RRZE\FAUbox;

defined('ABSPATH') || exit;

/**
 * Main class
 *
 * This class serves as the entry point for the plugin.
 * It initializes shortcodes, settings and other components.
 *
 * @package RRZE\FAUbox
 */
final class Main
{
    public function __construct()
    {
        if (is_admin()) {
            Admin\Settings::register(); // Settings-Seite im Backend
        }

        add_action('init', [Shortcode::class, 'register']); // Registriert den Shortcode
        add_action('init', [self::class, 'registerBlock']);

        add_filter('block_categories_all', [self::class, 'rrzeBlockCategory'], 10, 2);

    }


    public static function registerBlock(): void
    {
        register_block_type(__DIR__ . '/../build/block');
    }

    /**
     * Adds custom block category if not already present.
     *
     * @param array $categories
     * @param $post
     * @return array
     */
    public static function rrzeBlockCategory(array $categories, $post): array
    {
        // Check if there is already a RRZE category present
        foreach ($categories as $category) {
            if (isset($category['slug']) && $category['slug'] === 'rrze') {
                return $categories;
            }
        }

        $custom_category = [
            'slug'  => 'rrze',
            'title' => __('RRZE', 'rrze-faubox'),
        ];

        $categories[] = $custom_category;

        return $categories;
    }




}
