<?php

namespace RRZE\FAUbox\Admin;

defined('ABSPATH') || exit;


/**
 * Registers and renders the FAUbox settings page in the WordPress admin.
 *
 * Responsibilities:
 * - Add settings page under "Settings"
 * - Register plugin options (username + token)
 * - Render the settings form
 */
class Settings
{
    /**
     * Constructor.
     *
     * Hooks the settings page and option registration into the appropriate WordPress admin actions.
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'addOptionsPage']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    /**
     * Adds the FAUbox settings page to the WordPress admin menu.
     *
     * @return void
     */
    public function addOptionsPage(): void
    {
        add_options_page(
                esc_html__('FAUbox Settings', 'rrze-faubox'),
                'RRZE FAUbox',
                'manage_options',
                'rrze-faubox',
                [$this, 'renderSettings']
        );
    }


    /**
     * Registers plugin settings including sanitization callbacks.
     *
     * @return void
     */
    public function registerSettings(): void
    {
        register_setting('rrze_faubox_settings', 'rrze_faubox_token', [
                'sanitize_callback' => [$this, 'sanitizeToken'],
        ]);
        register_setting('rrze_faubox_settings', 'rrze_faubox_username', [
                'sanitize_callback' => [$this, 'sanitizeUsername'],
        ]);
    }

    /**
     * Sanitize WebDAV username input.
     *
     * @param mixed $value Raw input value.
     * @return string Sanitized username.
     */
    public function sanitizeUsername(mixed $value): string
    {
        return sanitize_text_field((string)$value);
    }

    /**
     * Sanitize WebDAV token input
     *
     * @param mixed $valeu Raw input value.
     * @return string Sanitized token.
     */
    public function sanitizeToken(mixed $value): string
    {
        return sanitize_text_field((string)$value);
    }


    /**
     * Renders the settings page HTML form.
     *
     * @return void
     */
    public function renderSettings(): void
    {
        ?>
        <div class="wrap">
            <h1> <?php echo esc_html__('FAUbox Settings', 'rrze-faubox'); ?> </h1>
            <p> <?php echo esc_html__('Enter your FAUbox WebDAV credentials. You can find them in your FAUbox account under "My Account" →
   "Devices" → "Add WebDAV connection".', 'rrze-faubox'); ?>
            </p>

            <form method="post" action="options.php">
                <?php
                settings_fields('rrze_faubox_settings');
                do_settings_sections('rrze_faubox_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="rrze_faubox_username">
                                <?php
                            echo esc_html__('FAUbox Username', 'rrze-faubox');
                            ?>
                            </label>
                        </th>
                        <td>
                            <input type=" text" id="rrze_faubox_username" name="rrze_faubox_username"
                            value="<?php echo esc_attr(get_option('rrze_faubox_username')); ?>"
                            class="regular-text" autocomplete="off">
                            </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="rrze_faubox_token">
                                <?php
                                echo esc_html__('FAUbox WebDAV Token', 'rrze-faubox')
                                ?>
                            </label>
                        </th>
                        <td>
                            <input type="password" id="rrze_faubox_token" name="rrze_faubox_token"
                                   value="<?php echo esc_attr(get_option('rrze_faubox_token')); ?>"
                                   class="regular-text" autocomplete="off">
                            <p class="description">
                                <?php echo esc_html__('Token validity: 1 year. Generate a new token before it expires.', 'rrze-faubox'); ?>
                            </p>

                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
