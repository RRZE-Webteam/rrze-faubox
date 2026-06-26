<?php

declare(strict_types=1);

namespace RRZE\FAUbox\Blocks;

use RRZE\FAUbox\API\FileService;
use RRZE\FAUbox\Frontend\Renderer;


defined('ABSPATH') || exit;

/**
 * Handles server-side rendering for the FAUbox block.
 */
class BlockRender
{
    private FileService $fileService;
    private Renderer $renderer;

    public function __construct(FileService $fileService, Renderer $renderer)
    {
        $this->fileService = $fileService;
        $this->renderer = $renderer;
    }

    /**
     * Server-side render callback for the block.
     *
     * @param array $attributes Block attributes from the editor.
     * @return string HTML output.
     */
    public function output(array $attributes = []): string
    {
        $files = $this->fileService->getPreparedFilesFromFolder(
            (string)($attributes['path'] ?? ''),
            (array)($attributes['filetype'] ?? []),
            (string)($attributes['sort'] ?? 'asc'),
            (string)($attributes['orderby'] ?? 'name')
        );

        return $this->renderer->render($files, $attributes);
    }
}