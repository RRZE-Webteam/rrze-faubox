<?php

namespace RRZE\FAUbox;

defined('ABSPATH') || exit;

use RRZE\FAUbox\Blocks\BlockRegistration;
use RRZE\FAUbox\Rest\RestController;
use RRZE\FAUbox\API\Client;
use RRZE\FAUbox\API\FileService;
use RRZE\FAUbox\Admin\Settings;

/**
 * Main class
 *
 * This class serves as the entry point for the plugin.
 * It initializes shortcodes, settings and other components.
 *
 * @package RRZE\FAUbox
 */
class Main
{
    public function __construct()
    {
        $this->init();
    }

    /**
     * Create shared service instances.
     */
    private function init(): void
    {
        $client = new Client();
        $fileService = new FileService($client);

        new BlockRegistration();
        new RestController($fileService, $client);

        if (is_admin()) {
            new Settings();
        }
    }
}
