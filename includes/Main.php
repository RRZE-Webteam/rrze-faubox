<?php

namespace RRZE\FAUbox;

defined('ABSPATH') || exit;

use RRZE\FAUbox\Blocks\BlockRegistration;
use RRZE\FAUbox\Admin\Rest;

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
        $this->initHooks();
    }

    private function initHooks(): void
    {
        $this->initShortcodes();
        $this->initBlockRegistration();
        $this->registerRestRoute();
    }


    private function initShortcodes(): void
    {
        add_action('init', [Shortcode::class, 'register']);
    }

    public function initBlockRegistration(): void
    {
        BlockRegistration::register();
    }

    /**
     * Registers custom REST API routes for the FAUbox plugin.
     */
    private function registerRestRoute(): void
    {
        Rest::register();
    }
}
