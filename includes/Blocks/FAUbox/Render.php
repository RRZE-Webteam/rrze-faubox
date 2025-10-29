<?php

declare(strict_types=1);

namespace RRZE\FAUbox\Blocks\FAUbox;

use RRZE\FAUbox\Shortcode;

defined('ABSPATH') || exit;

/**
 * Handles server-side rendering for the FAUbox block.
 */
class Render
{
    /**
     * Returns the rendered output for the FAUbox block.
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    public static function output(array $attributes = []): string
    {
        return Shortcode::render($attributes);
    }
}
