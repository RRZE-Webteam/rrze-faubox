<?php

declare(strict_types=1);

namespace RRZE\FAUbox\Blocks;

use RRZE\FAUbox\Shortcode;

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
        return Shortcode::render($attributes);
    }

}

