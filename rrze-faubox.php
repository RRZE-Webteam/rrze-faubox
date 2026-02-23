<?php

/**
 * Plugin Name:        RRZE FAUbox
 * Plugin URI:         https://github.com/RRZE-Webteam/rrze-faubox
 * Version:            1.0.0
 * Description:        A Plugin for FAUbox data integration
 * Author:             RRZE Webteam
 * Author URI:         https://www.wp.rrze.fau.de/
 * License:            GNU General Public License Version 3
 * License URI:        https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:        rrze-faubox
 * Domain Path:        /languages
 * Requires at least:  6.8
 * Requires PHP:       8.2
 */

declare(strict_types=1);

namespace RRZE\FAUbox;

defined('ABSPATH') || exit;

const FAUBOX_PHP_VERSION = '8.2';
const FAUBOX_WP_VERSION = '6.8';



spl_autoload_register(function ($class) {
    $prefix = __NAMESPACE__ . '\\';
    $baseDir = __DIR__ . '/includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }

});

/**
 * System requirements verification.
 * @return string Return an error message.
 */
function systemRequirements(): string
{
    $error = '';
    if (version_compare(PHP_VERSION, FAUBOX_PHP_VERSION, '<')) {
        $error = sprintf(
        /* translators: 1: Server PHP version number, 2: Required PHP version number. */
            __('The server is running PHP version %1$s. The Plugin requires at least PHP version %2$s.', 'rrze-faubox'),
            PHP_VERSION,
            FAUBOX_PHP_VERSION
        );
    } elseif (version_compare($GLOBALS['wp_version'], FAUBOX_WP_VERSION, '<')) {
        $error = sprintf(
        /* translators: 1: Server WordPress version number, 2: Required WordPress version number. */
            __('The server is running WordPress version %1$s. The Plugin requires at least WordPress version %2$s.', 'rrze-faubox'),
            $GLOBALS['wp_version'],
            FAUBOX_WP_VERSION
        );
    }
    return $error;
}


/**
 * Load the Textdomain and new Main
 */
add_action('plugins_loaded', __NAMESPACE__ . '\initializePlugin');

function initializePlugin(): void {
    load_plugin_textdomain(
        'rrze-faubox',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
    $error = systemRequirements();
    if ($error !==''){
        add_action('admin_notices', static function () use ($error): void {
            echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
        });

        return;
    }
    new Main();
}
