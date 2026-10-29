<?php

// Exit if not called by WordPress uninstall.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove all plugin data.
 *
 * Options contain encrypted credentials — these must not remain
 * in the database after the plugin is deleted.
 */

$options = [
    'rrze_faubox_username',
    'rrze_faubox_token',
    'rrze_faubox_token_created_at',
    'rrze_faubox_folder',
    'rrze_faubox_index_ttl',
    'rrze_faubox_index_last_built',
];

$transients = [
    'rrze_faubox_folder_index',
    'rrze_faubox_index_status',
    'rrze_faubox_index_cooldown',
];

$cron_hooks = [
    'rrze_faubox_rebuild_index',
    'rrze_faubox_rebuild_index_once',
];

if (is_multisite()) {
    $site_ids = get_sites(['fields' => 'ids', 'number' => 0]);
    foreach ($site_ids as $site_id) {
        switch_to_blog($site_id);

        foreach ($options as $option) {
            delete_option($option);
        }
        foreach ($transients as $transient) {
            delete_transient($transient);
        }
        foreach ($cron_hooks as $hook) {
            wp_clear_scheduled_hook($hook);
        }

        restore_current_blog();
    }
} else {
    foreach ($options as $option) {
        delete_option($option);
    }
    foreach ($transients as $transient) {
        delete_transient($transient);
    }
    foreach ($cron_hooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }
}
