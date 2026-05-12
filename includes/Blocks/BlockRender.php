<?php

declare(strict_types=1);

namespace RRZE\FAUbox\Blocks;

use RRZE\FAUbox\API\Client;
use RRZE\FAUbox\API\FileService;
use RRZE\FAUbox\Frontend\Renderer;


defined('ABSPATH') || exit;

/**
 * Handles server-side rendering for the FAUbox block.
 */
class BlockRender
{
    /**
     * Server-side render callback for the block.
     *
     * @param array $attributes Block attributes from the editor.
     * @return string HTML output.
     */
    public static function output(array $attributes = []): string
    {
        $client = new Client();
        $service = new FileService($client);

        $files = $service->getPreparedFilesFromFolder(
            (string)($attributes['path'] ?? ''),
            (array)($attributes['filetype'] ?? []),
            (string)($attributes['sort'] ?? 'asc'),
            (string)($attributes['orderby'] ?? 'name')
        );


        return Renderer::render($files, $attributes);
    }
}