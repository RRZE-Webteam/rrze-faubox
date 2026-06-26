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
            'size' => __('File Size', 'rrze-faubox'),
            'modified' => __('Last modified', 'rrze-faubox'),
        ];
    }

    /**
     * Writes a message to the WordPress debug log.
     *
     * Only active when WP_DEBUG and WP_DEBUG_LOG are enabled.
     *
     * @param mixed  $input Log message or array/object to print.
     * @param string $level Log level: 'i' (info), 'e' (error), 'd' (debug).
     */
    public static function debug($input, string $level = 'i'): void
    {
        if (!WP_DEBUG) {
            return;
        }
        if (in_array(strtolower((string)WP_DEBUG_LOG), ['true', '1'], true)) {
            $logPath = WP_CONTENT_DIR . '/debug.log';
        } elseif (is_string(WP_DEBUG_LOG)) {
            $logPath = WP_DEBUG_LOG;
        } else {
            return;
        }

        if (is_array($input) || is_object($input)) {
            $input = print_r($input, true);
        }

        switch (strtolower($level)) {
            case 'e':
            case 'error':
                $level = 'Error';
                break;
            case 'd':
            case 'debug':
                $level = 'Debug';
                break;
            default:
                $level = 'Info';
        }

        $trace  = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($trace[1])
            ? basename((string)($trace[1]['file'] ?? '')) . ':' . ($trace[1]['line'] ??
                '?')
            : 'unknown';

        error_log(
            date("[d-M-Y H:i:s \U\T\C]")
            . " WP $level: "
            . $caller . ' '
            . $input
            . PHP_EOL,
            3,
            $logPath
        );
    }


    /**
     * Check whether WordPress debug mode is active.
     *
     * @return bool True if WP_DEBUG is defined and enabled.
     */
    public static function isDebug(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }


    /**
     * Strip the WebDAV prefix from a server-returned href and URL-decode it.
     * '/webdav/My%20Folder/Sub/' → 'My Folder/Sub'
     */
    public static function stripWebdavPrefix(string $href): string
    {
        return trim(preg_replace('#^/webdav/?#', '',
            rawurldecode($href)), '/');
    }


    /**
     * Convert a clean path to a full WebDAV path.
     * 'My Folder/Sub' → '/webdav/My Folder/Sub'
     */
    public static function toWebdavPath(string $cleanPath): string
    {
        return '/webdav/' . trim($cleanPath, '/');
    }


}
