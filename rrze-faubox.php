<?php

/**
 * Plugin Name:        RRZE FAUbox
 * Plugin URI:         https://github.com/RRZE-Webteam/rrze-faubox
 * Version:            1.0.2
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

define('RRZE_FAUBOX_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, __NAMESPACE__ . '\activatePlugin');
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\deactivatePlugin');

function activatePlugin(): void
{
    if (!wp_next_scheduled('rrze_faubox_rebuild_index')) {
        rescheduleIndexCron();
    }
}

/**
 * Register or re-register the index rebuild cron event.
 *
 * Uses the configured cache duration (rrze_faubox_index_ttl) to select
 * the appropriate WordPress cron recurrence: twicedaily (12h)
 * or daily (24h). Called on plugin activation and whenever the TTL setting changes.
 */
function rescheduleIndexCron(): void
{
    wp_clear_scheduled_hook('rrze_faubox_rebuild_index');
    $ttl = (int)get_option('rrze_faubox_index_ttl', 24);
    $recurrence = match($ttl) {
        12      => 'twicedaily',
        default => 'daily',
    };
    wp_schedule_event(time(), $recurrence, 'rrze_faubox_rebuild_index');
}

function deactivatePlugin(): void
{
    wp_clear_scheduled_hook('rrze_faubox_rebuild_index');
    wp_clear_scheduled_hook('rrze_faubox_rebuild_index_once');
}


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

function initializePlugin(): void
{
    load_plugin_textdomain(
        'rrze-faubox',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
    $error = systemRequirements();
    if ($error !== '') {
        add_action('admin_notices', static function () use ($error): void {
            echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
        });

        return;
    }
    new Main();
}
