<?php
/**
 * Queue Monitor Page Class
 *
 * Provides a dedicated admin page for monitoring the email queue,
 * viewing active/recent jobs, and configuring queue settings.
 *
 * @package Penalis_Emailer
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Queue_Monitor_Page
 *
 * Renders the Queue Monitor admin page.
 */
class Penalis_Queue_Monitor_Page extends Penalis_Admin_Page {

    /**
     * Queue repository
     *
     * @var Penalis_Email_Queue_Repository
     */
    private $queue;

    /**
     * Constructor
     *
     * @param Penalis_Email_Queue_Repository $queue Queue repository
     */
    public function __construct(Penalis_Email_Queue_Repository $queue) {
        $this->queue     = $queue;
        $this->page_slug = 'penalis-email-queue';
    }

    // =========================================================================
    // RENDER
    // =========================================================================

    /**
     * Render the queue monitor page.
     *
     * @return void
     */
    public function render(): void {
        if (!$this->can_access()) {
            wp_die(__('You do not have permission to access this page.', 'penalis-emailer'));
        }

        // Handle settings save (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['penalis_queue_settings_nonce'])) {
            $this->handle_settings_save();
        }

        $active_job_ids = $this->queue->get_active_job_ids();
        $active_jobs    = [];
        foreach ($active_job_ids as $job_id) {
            $active_jobs[$job_id] = $this->queue->get_job_summary($job_id);
        }

        // Recent completed jobs — query last 10 sent items grouped by job_id
        $recent_jobs = $this->get_recent_completed_jobs(10);

        ?>
        <div class="wrap penalis-queue-monitor">
            <h1>
                <span class="dashicons dashicons-backup" style="font-size:26px;width:26px;height:26px;vertical-align:middle;margin-right:6px;color:#2271b1;"></span>
                <?php echo esc_html__('Queue Monitor', 'penalis-emailer'); ?>
            </h1>
            <p class="description">
                <?php echo esc_html__('Monitor background email sending jobs and configure queue behaviour.', 'penalis-emailer'); ?>
            </p>

            <?php $this->render_cron_health_notice(); ?>

            <div class="penalis-queue-monitor-grid">

                <!-- Left column: active + recent jobs -->
                <div class="penalis-queue-monitor-main">
                    <?php $this->render_active_jobs($active_jobs); ?>
                    <?php $this->render_recent_jobs($recent_jobs); ?>
                </div>

                <!-- Right column: settings -->
                <div class="penalis-queue-monitor-sidebar">
                    <?php $this->render_settings_form(); ?>
                    <?php $this->render_queue_stats(); ?>
                </div>

            </div>
        </div>
        <?php
    }

    // =========================================================================
    // SECTION: CRON HEALTH
    // =========================================================================

    /**
     * Warn if WP-Cron appears to be disabled.
     *
     * @return void
     */
    private function render_cron_health_notice(): void {
        $cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $next_run      = wp_next_scheduled(Penalis_Config::CRON_HOOK);

        if ($cron_disabled): ?>
            <div class="notice notice-warning inline" style="margin:12px 0;">
                <p>
                    <strong><?php echo esc_html__('WP-Cron is disabled (DISABLE_WP_CRON = true).', 'penalis-emailer'); ?></strong>
                    <?php echo esc_html__('Queue processing will not run automatically. Make sure a real server cron calls wp-cron.php on a schedule.', 'penalis-emailer'); ?>
                </p>
            </div>
        <?php elseif ($next_run): ?>
            <div class="penalis-cron-status penalis-cron-status--ok">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php
                printf(
                    /* translators: %s = human-readable time */
                    esc_html__('Next queue run scheduled in %s.', 'penalis-emailer'),
                    esc_html(human_time_diff(time(), $next_run))
                );
                ?>
            </div>
        <?php else: ?>
            <div class="penalis-cron-status penalis-cron-status--idle">
                <span class="dashicons dashicons-clock"></span>
                <?php echo esc_html__('No queue run scheduled — queue is idle (no pending jobs).', 'penalis-emailer'); ?>
            </div>
        <?php endif;
    }

    // =========================================================================
    // SECTION: ACTIVE JOBS
    // =========================================================================

    /**
     * Render the active jobs table.
     *
     * @param array $active_jobs Keyed by job_id, value = summary array
     * @return void
     */
    private function render_active_jobs(array $active_jobs): void {
        ?>
        <div class="penalis-form-card">
            <h3>
                <span class="dashicons dashicons-update"></span>
                <?php echo esc_html__('Active Jobs', 'penalis-emailer'); ?>
                <?php if (!empty($active_jobs)): ?>
                    <span class="penalis-badge penalis-badge--blue"><?php echo count($active_jobs); ?></span>
                <?php endif; ?>
            </h3>

            <?php if (empty($active_jobs)): ?>
                <p class="penalis-muted"><?php echo esc_html__('No active jobs. Queue is idle.', 'penalis-emailer'); ?></p>
            <?php else: ?>
                <table class="widefat striped penalis-jobs-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Job ID', 'penalis-emailer'); ?></th>
                            <th><?php echo esc_html__('Progress', 'penalis-emailer'); ?></th>
                            <th><?php echo esc_html__('Sent', 'penalis-emailer'); ?></th>
                            <th><?php echo esc_html__('Pending', 'penalis-emailer'); ?></th>
                            <th><?php echo esc_html__('Failed', 'penalis-emailer'); ?></th>
                            <th><?php echo esc_html__('Actions', 'penalis-emailer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_jobs as $job_id => $summary): ?>
                            <?php
                            $total   = $summary['total']   ?: 1;
                            $sent    = $summary['sent']    ?? 0;
                            $pending = ($summary['pending'] ?? 0) + ($summary['processing'] ?? 0) + ($summary['failed'] ?? 0);
                            $perm_failed = $summary['permanently_failed'] ?? 0;
                            $pct     = round(($sent / $total) * 100);
                            ?>
                            <tr data-job-id="<?php echo esc_attr($job_id); ?>">
                                <td>
                                    <code class="penalis-job-id"><?php echo esc_html($job_id); ?></code>
                                </td>
                                <td class="penalis-progress-cell">
                                    <div class="penalis-progress-track penalis-progress-track--sm">
                                        <div class="penalis-progress-bar" style="width:<?php echo esc_attr($pct); ?>%"></div>
                                    </div>
                                    <span class="penalis-progress-pct"><?php echo esc_html($pct); ?>%</span>
                                </td>
                                <td><strong><?php echo esc_html($sent); ?></strong> / <?php echo esc_html($total); ?></td>
                                <td><?php echo esc_html($pending); ?></td>
                                <td>
                                    <?php if ($perm_failed > 0): ?>
                                        <span class="penalis-badge penalis-badge--red"><?php echo esc_html($perm_failed); ?></span>
                                    <?php else: ?>
                                        <span class="penalis-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button"
                                            class="button button-small penalis-cancel-job"
                                            data-job-id="<?php echo esc_attr($job_id); ?>"
                                            data-nonce="<?php echo esc_attr(wp_create_nonce('penalis_cancel_job')); ?>">
                                        <?php echo esc_html__('Cancel', 'penalis-emailer'); ?>
                                    </button>
                                    <button type="button"
                                            class="button button-small penalis-refresh-job"
                                            data-job-id="<?php echo esc_attr($job_id); ?>"
                                            data-nonce="<?php echo esc_attr(wp_create_nonce('penalis_get_queue_status')); ?>">
                                        <?php echo esc_html__('Refresh', 'penalis-emailer'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // =========================================================================
    // SECTION: RECENT COMPLETED JOBS
    // =========================================================================

    /**
     * Render the recent completed jobs table.
     *
     * @param array $recent_jobs
     * @return void
     */
    private function render_recent_jobs(array $recent_jobs): void {
        ?>
        <div class="penalis-form-card">
            <h3>
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html__('Recently Completed Jobs', 'penalis-emailer'); ?>
            </h3>

            <?php if (empty($recent_jobs)): ?>
                <p class="penalis-muted"><?php echo esc_html__('No completed jobs yet.', 'penalis-emailer'); ?></p>
            <?php else: ?>
                <table class="widefat striped penalis-jobs-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Job ID', 'penalis-emailer'); ?></th>
                            <th><?php echo esc_html__('Sent', 'penalis-emailer'); ?></th>
                            <th><?php echo esc_html__('Failed', 'penalis-emailer'); ?></th>
                            <th><?php echo esc_html__('Completed', 'penalis-emailer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_jobs as $job): ?>
                            <tr>
                                <td><code class="penalis-job-id"><?php echo esc_html($job['job_id']); ?></code></td>
                                <td><strong><?php echo esc_html($job['sent']); ?></strong></td>
                                <td>
                                    <?php if ($job['permanently_failed'] > 0): ?>
                                        <span class="penalis-badge penalis-badge--red"><?php echo esc_html($job['permanently_failed']); ?></span>
                                    <?php else: ?>
                                        <span class="penalis-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="penalis-muted">
                                    <?php
                                    if ($job['last_sent_at'] > 0) {
                                        echo esc_html(
                                            human_time_diff($job['last_sent_at'], current_time('timestamp', true))
                                            . ' ' . __('ago', 'penalis-emailer')
                                        );
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // =========================================================================
    // SECTION: SETTINGS FORM
    // =========================================================================

    /**
     * Render the queue settings form.
     *
     * @return void
     */
    private function render_settings_form(): void {
        $batch_size   = Penalis_Config::get_queue_batch_size();
        $interval     = Penalis_Config::get_queue_interval();
        $max_attempts = Penalis_Config::get_queue_max_attempts();
        ?>
        <div class="penalis-form-card">
            <h3>
                <span class="dashicons dashicons-admin-settings"></span>
                <?php echo esc_html__('Queue Settings', 'penalis-emailer'); ?>
            </h3>

            <form method="post" action="">
                <?php wp_nonce_field('penalis_save_queue_settings', 'penalis_queue_settings_nonce'); ?>

                <div class="penalis-form-group">
                    <label for="queue_batch_size">
                        <?php echo esc_html__('Emails per batch', 'penalis-emailer'); ?>
                    </label>
                    <input type="number"
                           id="queue_batch_size"
                           name="queue_batch_size"
                           value="<?php echo esc_attr($batch_size); ?>"
                           min="1"
                           max="200"
                           class="small-text">
                    <p class="description">
                        <?php echo esc_html__('How many emails to send per cron run. Default: 30.', 'penalis-emailer'); ?>
                    </p>
                </div>

                <div class="penalis-form-group" style="margin-top:14px;">
                    <label for="queue_interval">
                        <?php echo esc_html__('Interval between batches (seconds)', 'penalis-emailer'); ?>
                    </label>
                    <input type="number"
                           id="queue_interval"
                           name="queue_interval"
                           value="<?php echo esc_attr($interval); ?>"
                           min="30"
                           max="3600"
                           class="small-text">
                    <p class="description">
                        <?php echo esc_html__('Seconds between each batch. Default: 60 (1 minute). Minimum: 30.', 'penalis-emailer'); ?>
                    </p>
                </div>

                <div class="penalis-form-group" style="margin-top:14px;">
                    <label for="queue_max_attempts">
                        <?php echo esc_html__('Max retry attempts', 'penalis-emailer'); ?>
                    </label>
                    <input type="number"
                           id="queue_max_attempts"
                           name="queue_max_attempts"
                           value="<?php echo esc_attr($max_attempts); ?>"
                           min="1"
                           max="10"
                           class="small-text">
                    <p class="description">
                        <?php echo esc_html__('How many times to retry a failed email before marking it permanently failed. Default: 3.', 'penalis-emailer'); ?>
                    </p>
                </div>

                <div style="margin-top:16px;">
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html__('Save Settings', 'penalis-emailer'); ?>
                    </button>
                </div>
            </form>

            <hr style="margin:20px 0 16px;">

            <h4 style="margin:0 0 8px 0;font-size:13px;">
                <?php echo esc_html__('Retry Backoff Schedule', 'penalis-emailer'); ?>
            </h4>
            <table class="widefat striped" style="font-size:12px;">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Attempt', 'penalis-emailer'); ?></th>
                        <th><?php echo esc_html__('Retry after', 'penalis-emailer'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><?php echo esc_html__('1st failure', 'penalis-emailer'); ?></td><td>5 <?php echo esc_html__('minutes', 'penalis-emailer'); ?></td></tr>
                    <tr><td><?php echo esc_html__('2nd failure', 'penalis-emailer'); ?></td><td>15 <?php echo esc_html__('minutes', 'penalis-emailer'); ?></td></tr>
                    <tr><td><?php echo esc_html__('3rd failure', 'penalis-emailer'); ?></td><td><?php echo esc_html__('Permanently failed', 'penalis-emailer'); ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    // =========================================================================
    // SECTION: QUEUE STATS
    // =========================================================================

    /**
     * Render overall queue statistics.
     *
     * @return void
     */
    private function render_queue_stats(): void {
        global $wpdb;
        $table = Penalis_Database::get_queue_table();

        $rows = $wpdb->get_results(
            "SELECT status, COUNT(*) as cnt FROM {$table} GROUP BY status",
            ARRAY_A
        );

        $counts = [
            'pending'            => 0,
            'processing'         => 0,
            'sent'               => 0,
            'failed'             => 0,
            'permanently_failed' => 0,
        ];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (array_key_exists($row['status'], $counts)) {
                    $counts[$row['status']] = (int) $row['cnt'];
                }
            }
        }

        $total = array_sum($counts);
        ?>
        <div class="penalis-form-card">
            <h3>
                <span class="dashicons dashicons-chart-bar"></span>
                <?php echo esc_html__('Queue Statistics', 'penalis-emailer'); ?>
            </h3>

            <table class="widefat" style="font-size:13px;">
                <tbody>
                    <tr>
                        <td><?php echo esc_html__('Total items in queue', 'penalis-emailer'); ?></td>
                        <td><strong><?php echo esc_html(number_format_i18n($total)); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php echo esc_html__('Pending', 'penalis-emailer'); ?></td>
                        <td><?php echo esc_html(number_format_i18n($counts['pending'])); ?></td>
                    </tr>
                    <tr>
                        <td><?php echo esc_html__('Processing', 'penalis-emailer'); ?></td>
                        <td><?php echo esc_html(number_format_i18n($counts['processing'])); ?></td>
                    </tr>
                    <tr>
                        <td><?php echo esc_html__('Sent', 'penalis-emailer'); ?></td>
                        <td><span style="color:#00a32a;font-weight:600;"><?php echo esc_html(number_format_i18n($counts['sent'])); ?></span></td>
                    </tr>
                    <tr>
                        <td><?php echo esc_html__('Retrying (failed)', 'penalis-emailer'); ?></td>
                        <td><?php echo esc_html(number_format_i18n($counts['failed'])); ?></td>
                    </tr>
                    <tr>
                        <td><?php echo esc_html__('Permanently failed', 'penalis-emailer'); ?></td>
                        <td>
                            <?php if ($counts['permanently_failed'] > 0): ?>
                                <span style="color:#d63638;font-weight:600;"><?php echo esc_html(number_format_i18n($counts['permanently_failed'])); ?></span>
                            <?php else: ?>
                                <span style="color:#646970;">0</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php if ($counts['sent'] > 0 || $counts['permanently_failed'] > 0): ?>
                <div style="margin-top:14px;">
                    <form method="post" action=""
                          onsubmit="return confirm('<?php echo esc_js(__('Delete all sent and permanently failed items from the queue table? This cannot be undone.', 'penalis-emailer')); ?>');">
                        <?php wp_nonce_field('penalis_cleanup_queue', 'penalis_cleanup_queue_nonce'); ?>
                        <button type="submit" name="penalis_cleanup_queue" class="button button-secondary" style="color:#d63638;border-color:#d63638;">
                            <?php echo esc_html__('Clean up completed items', 'penalis-emailer'); ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // =========================================================================
    // FORM HANDLERS
    // =========================================================================

    /**
     * Handle queue settings save (POST).
     *
     * @return void
     */
    private function handle_settings_save(): void {
        // Handle cleanup action
        if (isset($_POST['penalis_cleanup_queue'])) {
            if (!wp_verify_nonce($_POST['penalis_cleanup_queue_nonce'] ?? '', 'penalis_cleanup_queue')) {
                wp_die(__('Security check failed.', 'penalis-emailer'));
            }
            if (!$this->can_access()) {
                wp_die(__('Insufficient permissions.', 'penalis-emailer'));
            }
            $deleted = $this->queue->cleanup_old(0); // 0 days = delete all completed
            $this->redirect_with_notice(
                $this->page_slug,
                'success',
                sprintf(__('Cleaned up %d completed queue items.', 'penalis-emailer'), $deleted)
            );
            return;
        }

        // Handle settings save
        if (!wp_verify_nonce($_POST['penalis_queue_settings_nonce'] ?? '', 'penalis_save_queue_settings')) {
            wp_die(__('Security check failed.', 'penalis-emailer'));
        }
        if (!$this->can_access()) {
            wp_die(__('Insufficient permissions.', 'penalis-emailer'));
        }

        $batch_size   = max(1,  min(200, (int) ($_POST['queue_batch_size']   ?? Penalis_Config::DEFAULT_QUEUE_BATCH_SIZE)));
        $interval     = max(30, min(3600, (int) ($_POST['queue_interval']    ?? Penalis_Config::DEFAULT_QUEUE_INTERVAL)));
        $max_attempts = max(1,  min(10,  (int) ($_POST['queue_max_attempts'] ?? Penalis_Config::DEFAULT_QUEUE_MAX_ATTEMPTS)));

        update_option(Penalis_Config::OPTION_KEY_QUEUE_BATCH_SIZE,    $batch_size);
        update_option(Penalis_Config::OPTION_KEY_QUEUE_INTERVAL,      $interval);
        update_option(Penalis_Config::OPTION_KEY_QUEUE_MAX_ATTEMPTS,  $max_attempts);

        $this->redirect_with_notice(
            $this->page_slug,
            'success',
            __('Queue settings saved successfully.', 'penalis-emailer')
        );
    }

    // =========================================================================
    // DATA HELPERS
    // =========================================================================

    /**
     * Get recently completed jobs from the queue table.
     *
     * Groups by job_id, returns only jobs where all items are sent or
     * permanently_failed (i.e. no pending/processing/failed rows remain).
     *
     * @param int $limit
     * @return array
     */
    private function get_recent_completed_jobs(int $limit = 10): array {
        global $wpdb;
        $table = Penalis_Database::get_queue_table();

        // Get job_ids that have NO pending/processing/failed items
        // and have at least one sent item, ordered by most recent sent_at
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    job_id,
                    SUM(status = 'sent') AS sent,
                    SUM(status = 'permanently_failed') AS permanently_failed,
                    MAX(sent_at) AS last_sent_at
                 FROM {$table}
                 GROUP BY job_id
                 HAVING
                    SUM(status IN ('pending','processing','failed')) = 0
                    AND SUM(status = 'sent') > 0
                 ORDER BY last_sent_at DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        return array_map(function (array $row): array {
            return [
                'job_id'             => $row['job_id'],
                'sent'               => (int) $row['sent'],
                'permanently_failed' => (int) $row['permanently_failed'],
                'last_sent_at'       => (int) $row['last_sent_at'],
            ];
        }, $rows);
    }
}
