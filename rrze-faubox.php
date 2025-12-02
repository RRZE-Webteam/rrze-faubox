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

//load textdomain (PHP files)
add_action('init', function () {
    load_plugin_textdomain('rrze-faubox', false, dirname(plugin_basename(__FILE__)) . '/languages');
});


//Register block editor script + JS translations early (runs on init).
add_action('init', function (): void {
    // Register the compiled editor bundle. Adjust path/version if needed.
    wp_register_script(
        'rrze-faubox-editor',
        plugins_url('build/block/index.js', __FILE__),
        ['wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor', 'wp-api-fetch'],
        '1.0.0',
        true
    );

    // Bind JS translations found in /languages to the registered handle.
    wp_set_script_translations(
        'rrze-faubox-editor',
        'rrze-faubox',
        plugin_dir_path(__FILE__) . '/languages'
    );
});

//Enqueue the editor script only inside the block editor.
add_action('enqueue_block_editor_assets', function (): void {
    wp_enqueue_script('rrze-faubox-editor');
});



// Starte das Plugin über die zentrale Main-Klasse
new Main();
