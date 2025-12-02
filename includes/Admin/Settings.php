<?php

namespace RRZE\FAUbox\Admin;

defined('ABSPATH') || exit;

/**
 * Admin settings page for FAUbox plugin.
 *
 * Stores the public FAUbox share link.
 * Example:
 * https://faubox.rrze.uni-erlangen.de/getlink/fiW1p6e2svmkxDNCVTB/
 */
class Settings
{
    /**
     * Register menu + settings.
     */
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'addOptionsPage']);
        add_action('admin_init', [self::class, 'registerSettings']);
    }


    /**
     * Add settings page under "Settings".
     */
    public static function addOptionsPage(): void
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
     * Register single setting: rrze_faubox_sharelink
     */
    public static function registerSettings(): void
    {
        register_setting('rrze_faubox_settings', 'rrze_faubox_sharelink');
    }


    /**
     * Render settings form.
     */
    public static function render(): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('FAUbox Settings', 'rrze-faubox'); ?></h1>

            <p><?php echo esc_html__('Enter your FAUbox public share link.', 'rrze-faubox'); ?></p>

            <form method="post" action="options.php">
                <?php
                settings_fields('rrze_faubox_settings');
                ?>

                <table class="form-table">

                    <tr>
                        <th scope="row">
                            <label for="rrze_faubox_sharelink">
                                <?php echo esc_html__('Public Share Link', 'rrze-faubox'); ?>
                            </label>
                        </th>

                        <td>
                            <input type="text"
                                   id="rrze_faubox_sharelink"
                                   name="rrze_faubox_sharelink"
                                   value="<?php echo esc_attr(get_option('rrze_faubox_sharelink')); ?>"
                                   class="regular-text"
                                   autocomplete="off"
                                   placeholder="https://faubox.rrze.uni-erlangen.de/getlink/XXXXX/"
                            />

                            <p class="description">
                                <?php echo esc_html__('Paste your FAUbox public share link here. Example: https://faubox.../getlink/fiW1p6e...', 'rrze-faubox'); ?>
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
