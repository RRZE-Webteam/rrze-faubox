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
 * Load plugin textdomain for PHP strings.
 */
add_action('init', function () {
    load_plugin_textdomain('rrze-faubox', false, dirname(plugin_basename(__FILE__)) . '/languages');
});



/**
 * Bootstrap the plugin once all plugins are loaded.
 */
add_action('plugins_loaded', static function (): void {
    new Main();
});
