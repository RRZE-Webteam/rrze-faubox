<?php

declare(strict_types=1);

namespace RRZE\FAUbox\Admin;

use RRZE\FAUbox\API\IndexService;
use RRZE\FAUbox\Encryption;

defined('ABSPATH') || exit;


/**
 * Registers and renders the FAUbox settings page in the WordPress admin.
 *
 * Responsibilities:
 * - Add settings page under "Settings"
 * - Register plugin options (username + token)
 * - Render the settings form
 * - Handle manual folder index refresh
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
        add_action('admin_post_rrze_faubox_refresh_index', [$this, 'handleManualRefresh']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

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
        register_setting('rrze_faubox_settings', 'rrze_faubox_folder', [
                'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('rrze_faubox_settings', 'rrze_faubox_username', [
                'sanitize_callback' => [$this, 'sanitizeApiUsername'],
        ]);
        register_setting('rrze_faubox_settings', 'rrze_faubox_token', [
                'sanitize_callback' => [$this, 'sanitizeApiKey'],
        ]);
        register_setting('rrze_faubox_settings', 'rrze_faubox_token_created_at', [
                'sanitize_callback' => [$this, 'sanitizeDate'],
        ]);
        register_setting('rrze_faubox_settings', 'rrze_faubox_index_ttl', [
                'sanitize_callback' => [$this, 'sanitizeIndexTtl'],
        ]);
    }

    /**
     * Sanitize and encrypt the WebDAV token before saving to DB.
     */
    public function sanitizeApiKey(mixed $input): string
    {
        $clean = sanitize_text_field((string)$input);

        // Placeholder
        if ($clean === str_repeat('*', 16)) {
            return get_option('rrze_faubox_token', '');
        }

        if ($clean === '') return '';

        return (new Encryption())->encrypt($clean);
    }

    /**
     * Sanitize Username before saving to DB.
     */
    public function sanitizeApiUsername(mixed $input): string
    {
        return sanitize_text_field((string)$input);
    }


    /**
     * Sanitize a date input value.
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
        $allowed = [12, 24];
        $value = (int)$value;
        return in_array($value, $allowed, true) ? $value : 24;
    }


    /**
     * Calculate token expiry information from the stored creation date.
     *
     * @return array{expiryDate: \DateTime, daysLeft: int, isPast: bool}|null
     * Null if no valid creation date is stored.
     */
    private function getTokenExpiryInfo(): ?array
    {
        $createdAt = get_option('rrze_faubox_token_created_at', '');
        if (empty($createdAt)) {
            return null;
        }

        try {
            $expiryDate = (new \DateTime($createdAt))->modify('+1 year');
        } catch (\Exception $e) {
            return null;
        }

        $today = new \DateTime('today');

        return [
                'expiryDate' => $expiryDate,
                'daysLeft' => (int)$today->diff($expiryDate)->days,
                'isPast' => $today > $expiryDate,
        ];
    }


    /**
     * Displays an admin dashboard notice when the FAUbox WebDAV token is about to expire (within 7 days) or has already expired.
     *
     * Reads the token creation date from options and calculates the expiry date by adding one year.
     * Shows an error notice if expired, or a warning notice if expiry is within 7 days.
     *
     * @return void
     */
    public function tokenExpiryNotice(): void
    {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['dashboard',
                        'settings_page_rrze-faubox'], true)) {
            return;
        }

        $expiry = $this->getTokenExpiryInfo();
        if (!$expiry || (!$expiry['isPast'] && $expiry['daysLeft'] > 7)) {
            return;
        }

        $message = $expiry['isPast']
                ? __('Your FAUbox WebDAV token has expired. Please generate a new one.', 'rrze-faubox')
                : sprintf(
                /* translators: %d: number of days until token expiry */
                        __('Your FAUbox WebDAV token expires in %d days. Please generate a new one soon.', 'rrze-faubox'),
                        $expiry['daysLeft']
                );

        printf(
                '<div class="notice notice-%s is-dismissible"><p><strong>RRZE FAUbox:</strong> %s</p></div>',
                esc_attr($expiry['isPast'] ? 'error' : 'warning'),
                esc_html($message)
        );
    }


    /**
     * Handle the manual index refresh form submission with Cooldown Check.
     *
     * Verifies nonce and capability, triggers index rebuild, then redirects back to settings.
     *
     * @return void
     */
    public function handleManualRefresh(): void
    {
        check_admin_referer('rrze_faubox_refresh_index');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'rrze-faubox'), 403);
        }

        if ($this->indexService->isOnCooldown()) {
            wp_redirect(admin_url('options-general.php?page=rrze-faubox&index_cooldown=1'));
            exit;
        }

        $this->indexService->directRebuild();
        $this->indexService->setCooldown();

        wp_redirect(admin_url('options-general.php?page=rrze-faubox&index_refreshed=1'));
        exit;
    }


    /**
     * Enqueues admin stylesheet and script on the FAUbox settings page.
     * Localizes polling data for the index status script.
     *
     * @param string $hookSuffix The current admin page hook suffix.
     * @return void
     */
    public function enqueueAdminAssets(string $hookSuffix): void
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
        wp_enqueue_script(
                'rrze-faubox-admin',
                RRZE_FAUBOX_URL . 'assets/js/faubox-admin.js',
                [],
                '1.0.0',
                true
        );
        $info = $this->indexService->getLastBuiltInfo();
        $indexBuildScheduled = (isset($_GET['settings-updated']) || isset($_GET['index_refreshed']))
                && $this->indexService->isBuildScheduled();
        wp_localize_script('rrze-faubox-admin', 'fauboxAdmin', [
                'polling' => $indexBuildScheduled,
                'knownTime' => (int)($info['time'] ?? 0),
                'restUrl' => rest_url('rrze-faubox/v1/index/status'),
                'nonce' => wp_create_nonce('wp_rest'),
                'labelLastBuild' => __('Last index build', 'rrze-faubox'),
                'labelFolders' => __('folders indexed', 'rrze-faubox'),
        ]);
    }

    /**
     * Renders the settings page HTML form.
     *
     * @return void
     */
    public function renderSettings(): void
    {
        // If a settings save just happened and the index is still empty
        // (e.g. cron did not run yet), rebuild synchronously as a fallback.
        if (isset($_GET['settings-updated']) && $this->indexService->isBuildScheduled()) {
            $this->indexService->directRebuild();
        }

        ?>
        <div class="wrap">
            <h1> <?php echo esc_html__('FAUbox Settings', 'rrze-faubox'); ?> </h1>
            <p> <?php echo esc_html__('Enter your FAUbox WebDAV credentials. You can generate them in your FAUbox account under "My Account" → "Devices" → "Add WebDAV connection".', 'rrze-faubox'); ?>
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
                                   value="<?php echo esc_attr(get_option('rrze_faubox_username', '')); ?>"
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
                                   value="<?php echo esc_attr(get_option('rrze_faubox_token') ? str_repeat('*', 16) : ''); ?>"
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
                            $expiry = $this->getTokenExpiryInfo();
                            if ($expiry) {
                                if ($expiry['isPast']) {
                                    $label = esc_html__('Your token has expired - please renew!', 'rrze-faubox');
                                    $class = 'rrze-faubox-token-expired';
                                } elseif ($expiry['daysLeft'] <= 30) {
                                    $label = sprintf(esc_html__('Your token expires in %d days — please renew soon.', 'rrze-faubox'), $expiry['daysLeft']);
                                    $class = 'rrze-faubox-token-expiring';
                                } else {
                                    $label = sprintf(
                                            esc_html__('Token validity: 1 year. Valid for %d more days (until %s).', 'rrze-faubox'),
                                            $expiry['daysLeft'],
                                            $expiry['expiryDate']->format('d.m.Y')
                                    );
                                    $class = '';
                                }
                                printf('<p class="description %s">%s</p>', esc_attr($class), $label);
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
                                <?php foreach ([12 => '12h', 24 => '24h'] as $hours => $label) : ?>
                                    <option value="<?php echo esc_attr($hours); ?>" <?php
                                    selected((int)get_option('rrze_faubox_index_ttl', 12), $hours); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Cache duration of the folder index. The index updates automatically after the set time.', 'rrze-faubox'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2><?php esc_html_e('Folder Index', 'rrze-faubox'); ?></h2>
            <p>
                <?php esc_html_e('The plugin builds a folder index of your FAUbox main folder in the background.', 'rrze-faubox'); ?>
                <br>
                <?php esc_html_e('This index powers the folder tree in the block editor.', 'rrze-faubox'); ?><br>
            </p>
            <?php if ((isset($_GET['settings-updated']) || isset($_GET['index_refreshed'])) && $this->indexService->isBuildScheduled()) : ?>
                <p id="faubox-building-notice"
                   class="description rrze-faubox-warning rrze-faubox-refresh-progress is-active">
                    <span class="spinner"></span>
                    <span>
                        <?php esc_html_e('Folder index is being built in the background. This may take a few minutes.', 'rrze-faubox'); ?>
                    </span>
                </p>
            <?php endif; ?>

            <?php
            $info = $this->indexService->getLastBuiltInfo();
            ?>
            <?php if ($info) : ?>
                <p id="faubox-index-status" class="description">
                    <strong><?php printf(
                                esc_html__('Last index build: %1$s (%2$d folders indexed)', 'rrze-faubox'),
                                esc_html(wp_date(get_option('date_format') . ' ' .
                                        get_option('time_format'), $info['time'])),
                                (int)$info['count']
                        ); ?></strong>
                </p>
                <?php if (isset($_GET['index_cooldown'])) : ?>
                    <p class="description rrze-faubox-warning">
                        <?php esc_html_e('Please wait 5 minutes before refreshing the index again.', 'rrze-faubox'); ?>
                    </p>
                <?php endif; ?>
            <?php else : ?>
                <p id="faubox-index-status" class="description">
                    <?php esc_html_e('No folder index built yet. Click "Refresh index" to build it.', 'rrze-faubox'); ?>
                </p>
            <?php endif; ?>
            <form method="post" action="<?php echo
            esc_url(admin_url('admin-post.php')); ?>" id="faubox-index-refresh-form">
                <input type="hidden" name="action" value="rrze_faubox_refresh_index">
                <?php wp_nonce_field('rrze_faubox_refresh_index'); ?>
                <?php submit_button(esc_html__('Refresh index now', 'rrze-faubox'), 'secondary'); ?>
            </form>
        </div>
        <?php
    }
}
