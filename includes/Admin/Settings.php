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
        add_action('admin_notices', [$this, 'tokenExpiryNotice']);
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
        register_setting('rrze_faubox_settings', 'rrze_faubox_folder', [
                'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('rrze_faubox_settings', 'rrze_faubox_token_created_at', [
                        'sanitize_callback' => [$this, 'sanitizeDate'],
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
     * @param mixed $value Raw input value.
     * @return string Sanitized token.
     */
    public function sanitizeToken(mixed $value): string
    {
        return sanitize_text_field((string)$value);
    }

    /**
     * Sanitize a date input value.
     *
     * Accepts only dates in YYYY-MM-DD format.
     *
     * @param mixed $value Raw input value.
     * @return string Sanitized date string, or empty string if invalid.
     */
    public function sanitizeDate(mixed $value): string
    {
        $date = sanitize_text_field((string)$value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                ? $date : '';
    }


    /**
     * Displays an admin dashboard notice when the FAUbox WebDAV token is about to expire (within 7 days) or has already expired.
     *
     * Reads the token creation date from options and calculates the expiry
     * date by adding one year. Shows an error notice if expired, or a
     * warning notice if expiry is within 7 days.
     *
     * @return void
     */
    public function tokenExpiryNotice(): void
    {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['dashboard', 'settings_page_rrze-faubox'], true)) {
            return;
        }
        $createdAt = get_option('rrze_faubox_token_created_at', '');
        if (empty($createdAt)) {
            return;
        }

        $expiryDate = (new \DateTime($createdAt))->modify('+1year');
        $today = new \DateTime('today');
        $daysLeft = (int)$today->diff($expiryDate)->days;
        $isPast = $today > $expiryDate;

        if ($isPast || $daysLeft <= 7) {
            $message = $isPast
                    ? __('Your FAUbox WebDAV token has expired. Please generate a new one.', 'rrze-faubox')
                    : sprintf(
                    /* translators: %d: number of days until token expiry */
                            __('Your FAUbox WebDAV token expires in %d days. Please generate a new one soon.', 'rrze-faubox'), $daysLeft);
            $type = $isPast ? 'error' : 'warning';
            printf('<div class="notice notice-%s is-dismissible"><p><strong>RRZE FAUbox:</strong> %s</p></div>',
                    esc_attr($type),
                    esc_html($message)
            );
        }
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
            <p> <?php echo esc_html__('Enter your FAUbox WebDAV credentials. You can generate them in your FAUbox account under "My Account" →
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
                            <input type="text" id="rrze_faubox_username" name="rrze_faubox_username"
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
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="rrze_faubox_token_created_at">
                                <?php echo esc_html__('Token created on','rrze-faubox'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="date" id="rrze_faubox_token_created_at"
                                   name="rrze_faubox_token_created_at"
                                   value="<?php echo esc_attr(get_option('rrze_faubox_token_created_at')); ?>"><?php
                            $createdAt = get_option('rrze_faubox_token_created_at', '');
                            if (!empty($createdAt)) {
                                $expiryDate = (new \DateTime($createdAt))->modify('+1 year');
                                $today = new \DateTime('today');
                                $daysLeft = (int)$today->diff($expiryDate)->days;
                                $isPast = $today > $expiryDate;

                                if ($isPast) {
                                    $label = esc_html__('Token has expired!', 'rrze-faubox');
                                    $class = 'notice-error';
                                } elseif ($daysLeft <= 30) {
                                    $label = sprintf(esc_html__('Expires in %d days — please renew soon.', 'rrze-faubox'), $daysLeft);
                                    $class = 'notice-warning';
                                } else {
                                    $label = sprintf(esc_html__('Token validity: 1 year. Valid for %d more days (until %s).', 'rrze-faubox'), $daysLeft,
                                            $expiryDate->format('d.m.Y'));
                                    $class = '';
                                }
                                printf('<p class="description %s">%s</p>',
                                        esc_attr($class), $label);
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="rrze_faubox_folder">
                                <?php
                                echo esc_html__('FAUbox Main Folder', 'rrze-faubox');
                                ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" id="rrze_faubox_folder" name="rrze_faubox_folder"
                                   value="<?php echo esc_attr(get_option('rrze_faubox_folder')); ?>"
                                   class="regular-text"
                                   autocomplete="off">
                            <p class="description">
                                <?php echo esc_html__('The main folder whose subfolders are to be displayed in the block editor.', 'rrze-faubox'); ?>
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
