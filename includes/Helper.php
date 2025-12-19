<?php

declare(strict_types=1);

namespace RRZE\FAUbox;

defined('ABSPATH') || exit;

class Helper
{
    /**
     * Returns translated column labels for table rendering.
     *
     * @return array<string, string>
     */
    public static function getColumnLabels(): array
    {
        return [
            'name' => __('File Name', 'rrze-faubox'),
            'type' => __('Type', 'rrze-faubox'),
//            'size' => __('File Size', 'rrze-faubox'),
//            'modified' => __('Changed', 'rrze-faubox'),
        ];
    }
}
