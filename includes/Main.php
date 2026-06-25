<?php

declare(strict_types=1);

namespace RRZE\FAUbox;

defined('ABSPATH') || exit;

use RRZE\FAUbox\Blocks\BlockRegistration;
use RRZE\FAUbox\Rest\RestController;
use RRZE\FAUbox\API\Client;
use RRZE\FAUbox\API\FileService;
use RRZE\FAUbox\API\IndexService;
use RRZE\FAUbox\Admin\Settings;

/**
 * Main class
 *
 * Main plugin class. Bootstraps all services and hooks.
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
        $indexService = new IndexService($fileService);

        new BlockRegistration();
        new RestController($fileService, $client, $indexService);

        // Rebuild index whenever credentials or root folder change.
        add_action('update_option_rrze_faubox_folder',   [$indexService, 'buildIndex']);
        add_action('update_option_rrze_faubox_token',    [$indexService, 'buildIndex']);
        add_action('update_option_rrze_faubox_username', [$indexService, 'buildIndex']);
        // Cron hook.
        add_action('rrze_faubox_rebuild_index', [$indexService, 'buildIndex']);


        if (is_admin()) {
            new Settings($indexService);
        }
    }
}
