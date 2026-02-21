<?php
/**
 * Uninstall Handler
 * This file is run automatically when users delete the plugin.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Clear Cron Jobs
wp_clear_scheduled_hook('ssw_full_sync_cron');
wp_clear_scheduled_hook('ssw_stock_sync_cron');
wp_clear_scheduled_hook('ssw_cleanup_cron');

// Remove Options
delete_option('ssw_api_base_url');
delete_option('ssw_api_key');
delete_option('ssw_api_secret');
delete_option('ssw_sync_frequency');
delete_option('ssw_auto_publish');
delete_option('ssw_last_sync_time');
delete_option('ssw_last_stock_sync');
delete_option('ssw_last_sync_count');
delete_option('ssw_last_sync_errors');

// Delete Log Table
$table_name = $wpdb->prefix . 'ssw_logs';
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe (prefix + hardcoded string)
    $wpdb->query("DROP TABLE IF EXISTS `{$table_name}`");
}

