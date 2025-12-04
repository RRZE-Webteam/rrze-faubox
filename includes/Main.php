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
//        $this->initAdmin();
        $this->initShortcodes();
        $this->initBlocks();
        $this->restAPI();


    }

//    private function initAdmin(): void
//    {
//        if (is_admin()) {
//            Admin\Settings::register();
//        }
//    }

    private function initShortcodes(): void
    {
        add_action('init', [Shortcode::class, 'register']);
    }

    private function initBlocks(): void
    {
        BlockRegistration::register();
    }

    /**
     * Registers custom REST API routes for the FAUbox plugin.
     *
     * Currently provides a dummy folder list for use in the block editor.
     * Replace or extend with real API routes later.
     */
    private function restAPI(): void
    {
        Rest::register();
    }



}
