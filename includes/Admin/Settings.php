<?php

namespace RRZE\FAUbox\Admin;

use RRZE\FAUbox\API\IndexService;

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
    private IndexService $indexService;

    /**
     * Constructor.
     *
     * Hooks the settings page and option registration into the appropriate WordPress admin actions.
     */
    public function __construct(IndexService $indexService)
    {
        $this->indexService = $indexService;

        add_action('admin_menu', [$this, 'addOptionsPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_notices', [$this, 'tokenExpiryNotice']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminStyles']);
        add_action('admin_post_rrze_faubox_refresh_index', [$this, 'handleManualRefresh']);
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
        register_setting('rrze_faubox_settings', 'rrze_faubox_index_ttl', [
                'sanitize_callback' => [$this, 'sanitizeIndexTtl'],
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
     * Sanitize index TTL input.
     *
     * @param mixed $value Raw input value.
     * @return int Sanitized TTL in hours.
     */
    public function sanitizeIndexTtl(mixed $value): int
    {
        $allowed = [1, 6, 12, 24];
        $value   = (int) $value;
        return in_array($value, $allowed, true) ? $value : 12;
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

        try {
            $expiryDate = (new \DateTime($createdAt))->modify('+1 year');
        } catch (\Exception $e) {
            return;
        }

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

    public function handleManualRefresh(): void
    {
        check_admin_referer('rrze_faubox_refresh_index');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'rrze-faubox'));
        }

        $this->indexService->buildIndex();

        wp_redirect(admin_url('options-general.php?page=rrze-faubox&index_refreshed=1'));
        exit;
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
            <?php if (isset($_GET['index_refreshed'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Folder index has been refreshed.', 'rrze-faubox'); ?></p>
                </div>
            <?php endif; ?>

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
                                <?php echo esc_html__('Token created on', 'rrze-faubox'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="date" id="rrze_faubox_token_created_at"
                                   name="rrze_faubox_token_created_at"
                                   value="<?php echo esc_attr(get_option('rrze_faubox_token_created_at')); ?>"><?php
                            $createdAt = get_option('rrze_faubox_token_created_at', '');
                            if (!empty($createdAt)) {
                                try {
                                    $expiryDate = (new \DateTime($createdAt))->modify('+1 year');
                                } catch (\Exception $e) {
                                    $expiryDate = null;
                                }
                                if ($expiryDate) {
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
                            <?php if (empty(get_option('rrze_faubox_folder', ''))) : ?>
                                <p class="description rrze-faubox-warning">
                                    <strong><?php esc_html_e('No main folder configured — the plugin will not display any files.', 'rrze-faubox'); ?></strong>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="rrze_faubox_index_ttl">
                                <?php esc_html_e('Index cache duration', 'rrze-faubox'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="rrze_faubox_index_ttl" name="rrze_faubox_index_ttl">
                                <?php foreach ([1 => '1h', 6 => '6h', 12 => '12h', 24 =>
                                        '24h'] as $hours => $label) : ?>
                                    <option value="<?php echo esc_attr($hours); ?>" <?php
                                    selected((int)get_option('rrze_faubox_index_ttl', 12), $hours); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('How long the folder index is cached. Use the refresh button or save settings to rebuild immediately.', 'rrze-faubox'); ?>
                            </p>
                        </td>
                    </tr>

                </table>
                <?php submit_button(); ?>
            </form>

            <h2><?php esc_html_e('Folder Index', 'rrze-faubox'); ?></h2>
            <p><?php esc_html_e('The folder index is built automatically when
  settings are saved. Use this button to refresh it manually.',
                        'rrze-faubox'); ?></p>
            <form method="post" action="<?php echo
            esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action"
                       value="rrze_faubox_refresh_index">
                <?php wp_nonce_field('rrze_faubox_refresh_index'); ?>
                <?php submit_button(esc_html__('Refresh index now', 'rrze-faubox'),
                        'secondary'); ?>
            </form>
        </div>
        <?php
    }

    public function enqueueAdminStyles(string $hookSuffix): void
    {
        if ($hookSuffix !== 'settings_page_rrze-faubox') {
            return;
        }
        wp_enqueue_style(
                'rrze-faubox-admin',
                RRZE_FAUBOX_URL . 'build/admin/admin.css',
                [],
                '1.0.0'
        );
    }
}
