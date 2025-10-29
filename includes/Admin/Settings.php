<?php

namespace RRZE\FAUbox\Admin;

defined('ABSPATH') || exit;

class Settings
{
    /**
     * Register settings page in admin menu.
     */
    public static function register()
    {
        // Menü-Eintrag erst NACH WordPress-Initialisierung registrieren
        add_action('admin_menu', [self::class, 'addOptionsPage']);
        add_action('admin_init', [self::class, 'registerSettings']);
    }

    /**
     * Add settings page in admin menu.
     */
    public static function addOptionsPage()
    {
        add_options_page(
            esc_html__('FAUbox Settings', 'rrze-faubox'),
            'RRZE FAUbox',
            'manage_options',
            'rrze-faubox',
            [self::class, 'render']
        );
    }


    /**
     * Register settings fields.
     */
    public static function registerSettings()
    {
        register_setting('rrze_faubox_settings', 'rrze_faubox_token');
        register_setting('rrze_faubox_settings', 'rrze_faubox_folder');
    }

    /**
     * Render the settings form.
     */
    public static function render()
    {
        ?>
        <div class="wrap">
            <h1> <?php echo esc_html__('FAUbox Settings', 'rrze-faubox'); ?> </h1>
            <p> <?php echo esc_html__('To access the FAUbox folder, please register with your token and ID.', 'rrze-faubox'); ?></p>
            <form method="post" action="options.php">
                <?php
                settings_fields('rrze_faubox_settings');
                do_settings_sections('rrze_faubox_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="rrze_faubox_token">
                                <?php
                                echo esc_html__('FAUbox API-Token', 'rrze-faubox');
                                ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" id="rrze_faubox_token" name="rrze_faubox_token"
                                   value="<?php echo esc_attr(get_option('rrze_faubox_token')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="rrze_faubox_folder">
                                <?php
                                echo esc_html__('File-ID (Base64)', 'rrze-faubox')
                                ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" id="rrze_faubox_folder" name="rrze_faubox_folder"
                                   value="<?php echo esc_attr(get_option('rrze_faubox_folder')); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
