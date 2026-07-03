<?php

declare(strict_types=1);

namespace RRZE\FAUbox;

defined('ABSPATH') || exit;

use RRZE\FAUbox\API\DownloadService;
use RRZE\FAUbox\Blocks\BlockRegistration;
use RRZE\FAUbox\Rest\RestController;
use RRZE\FAUbox\API\Client;
use RRZE\FAUbox\API\FileService;
use RRZE\FAUbox\API\IndexService;
use RRZE\FAUbox\Admin\Settings;
use RRZE\FAUbox\Blocks\BlockRender;
use RRZE\FAUbox\Frontend\Renderer;

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
        $downloadService = new DownloadService($client);
        $renderer = new Renderer();
        $blockRender = new BlockRender ($fileService, $renderer);

        new BlockRegistration($blockRender);
        new RestController($fileService, $indexService, $downloadService);

        // Rebuild index whenever credentials or root folder change.
        add_action('update_option_rrze_faubox_folder',   [$indexService, 'scheduleBuild']);
        add_action('update_option_rrze_faubox_token',    [$indexService, 'scheduleBuild']);
        add_action('update_option_rrze_faubox_username', [$indexService, 'scheduleBuild']);

        // Cron hook.
        add_action('rrze_faubox_rebuild_index', [$indexService, 'buildIndex']);
        add_action('rrze_faubox_rebuild_index_once', [$indexService, 'buildIndex']);


        if (is_admin()) {
            new Settings($indexService);
        }
    }
}
