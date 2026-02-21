<?php
/**
 * Cron Manager Class
 * จัดการ WordPress Cron Jobs สำหรับการ Sync อัตโนมัติ
 */

if (!defined('ABSPATH')) exit;

class SSW_Cron_Manager {
    
    public function __construct() {
        add_action('ssw_full_sync_cron', array($this, 'run_full_sync'));
        add_action('ssw_stock_sync_cron', array($this, 'run_stock_sync'));
        add_action('ssw_cleanup_cron', array($this, 'run_cleanup'));
        add_action('ssw_batch_sync', array($this, 'run_batch_sync'));
        add_action('ssw_process_product_sync', array($this, 'run_product_sync_queue'));
        
        // Hook สำหรับตั้งค่า Cron เมื่อเปิดใช้ Plugin
        register_activation_hook(SSW_PLUGIN_DIR . 'stock-sync-woocommerce.php', array($this, 'activate'));
        register_deactivation_hook(SSW_PLUGIN_DIR . 'stock-sync-woocommerce.php', array($this, 'deactivate'));
    }
    
    /**
     * ตั้งค่า Cron เมื่อเปิดใช้ Plugin
     */
    public function activate() {
        // สร้างตารางสำหรับเก็บ Log
        $this->create_tables();
        
        // Full Sync: ทุก 6 ชั่วโมง
        if (!wp_next_scheduled('ssw_full_sync_cron')) {
            wp_schedule_event(time(), 'six_hours', 'ssw_full_sync_cron');
        }
        
        // Stock Sync: ทุก 15 นาที
        if (!wp_next_scheduled('ssw_stock_sync_cron')) {
            wp_schedule_event(time(), 'fifteen_minutes', 'ssw_stock_sync_cron');
        }
        
        // Cleanup: ทุกวัน
        if (!wp_next_scheduled('ssw_cleanup_cron')) {
            wp_schedule_event(time(), 'daily', 'ssw_cleanup_cron');
        }
        
        // เพิ่ม Custom Schedule Intervals
        add_filter('cron_schedules', array($this, 'add_custom_schedules'));
    }
    
    /**
     * ยกเลิก Cron เมื่อปิดใช้ Plugin
     */
    public function deactivate() {
        wp_clear_scheduled_hook('ssw_full_sync_cron');
        wp_clear_scheduled_hook('ssw_stock_sync_cron');
        wp_clear_scheduled_hook('ssw_cleanup_cron');
    }
    
    /**
     * เพิ่ม Custom Cron Schedules
     */
    public function add_custom_schedules($schedules) {
        $schedules['fifteen_minutes'] = array(
            'interval' => 900,
            'display' => __('Every 15 Minutes', 'ssw')
        );
        $schedules['six_hours'] = array(
            'interval' => 21600,
            'display' => __('Every 6 Hours', 'ssw')
        );
        return $schedules;
    }
    
    /**
     * Acquire a transient-based lock to prevent overlapping cron runs.
     * กัน: DoS / Duplicate Sync — ถ้า cron fire ซ้ำ จะไม่ให้รันซ้อนกัน
     *
     * @param string $lock_name  Unique lock identifier
     * @param int    $ttl        Lock expiry in seconds (safety net)
     * @return bool  true if lock acquired, false if already running
     */
    private function acquire_lock($lock_name, $ttl = 600) {
        $lock_key = 'ssw_lock_' . sanitize_key($lock_name);
        if (get_transient($lock_key)) {
            error_log('[Stock Sync] Skipped ' . $lock_name . ' — previous run still in progress (lock active)');
            return false;
        }
        set_transient($lock_key, time(), $ttl);
        return true;
    }

    /**
     * Release a transient lock.
     */
    private function release_lock($lock_name) {
        delete_transient('ssw_lock_' . sanitize_key($lock_name));
    }

    /**
     * รัน Full Sync
     */
    public function run_full_sync() {
        // Lock กันรันซ้อน (TTL 30 นาที — safety net ถ้า process crash)
        if (!$this->acquire_lock('full_sync', 1800)) {
            return;
        }

        try {
            require_once SSW_PLUGIN_DIR . 'includes/class-product-sync.php';
            $sync = new SSW_Product_Sync();
            $result = $sync->sync_all_products();

            // บันทึก Log
            $this->log_sync_result('full_sync', $result);

            // ส่ง Notification ถ้ามี Error เยอะ
            if ($result['errors'] > 10) {
                $this->send_alert_email('Full sync completed with ' . $result['errors'] . ' errors');
            }
        } finally {
            $this->release_lock('full_sync');
        }
    }
    
    /**
     * รัน Stock Sync (เฉพาะสต็อก)
     */
    public function run_stock_sync() {
        // Lock กันรันซ้อน (TTL 10 นาที)
        if (!$this->acquire_lock('stock_sync', 600)) {
            return;
        }

        try {
            require_once SSW_PLUGIN_DIR . 'includes/class-product-sync.php';
            $sync = new SSW_Product_Sync();
            $updated = $sync->sync_stock_only();

            $this->log_sync_result('stock_sync', array('updated' => $updated));
        } finally {
            $this->release_lock('stock_sync');
        }
    }
    
    /**
     * รัน Batch Sync
     */
    public function run_batch_sync($batch_number = 0) {
        // ACL: validate parameter type — ป้องกัน data injection ผ่าน cron parameter
        $batch_number = absint($batch_number);

        // Lock กันรันซ้อน (TTL 15 นาที)
        if (!$this->acquire_lock('batch_sync', 900)) {
            return;
        }

        try {
            require_once SSW_PLUGIN_DIR . 'includes/class-product-sync.php';
            $sync = new SSW_Product_Sync();
            $sync->sync_batch($batch_number);
        } finally {
            $this->release_lock('batch_sync');
        }
    }
    
    /**
     * รัน Product Sync Queue
     */
    public function run_product_sync_queue($product_data) {
        // ACL: validate queue data structure — ป้องกัน malformed data จาก Action Scheduler queue
        if (!is_array($product_data) || empty($product_data['sku']) || empty($product_data['name'])) {
            error_log('[Stock Sync] Invalid product data in sync queue — skipped');
            return;
        }
        
        require_once SSW_PLUGIN_DIR . 'includes/class-product-sync.php';
        $sync = new SSW_Product_Sync();
        $sync->sync_single_product($product_data);
    }
    
    /**
     * รัน Cleanup
     */
    public function run_cleanup() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ssw_logs';
        
        // ลบ Log เก่ากว่า 30 วัน
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe (prefix + hardcoded string)
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$table_name}` WHERE created_at < %s",
                gmdate('Y-m-d H:i:s', strtotime('-30 days'))
            )
        );
        
        // บันทึก Cleanup log
        $this->log_sync_result('cleanup', array('status' => 'completed'));
    }
    
    /**
     * ส่ง Alert Email
     */
    private function send_alert_email($message) {
        $admin_email = get_option('admin_email');
        $subject = '[Stock Sync] Alert: ' . get_bloginfo('name');
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        wp_mail($admin_email, $subject, wp_strip_all_tags($message), $headers);
    }
    
    /**
     * บันทึก Sync Result ลง Database
     */
    private function log_sync_result($type, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ssw_logs';
        
        $wpdb->insert($table, array(
            'sync_type' => sanitize_key($type),
            'result' => wp_json_encode($data),
            'created_at' => current_time('mysql')
        ), array('%s', '%s', '%s'));
    }
    
    /**
     * สร้างตารางสำหรับเก็บ Log
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'ssw_logs';
        
        // ตรวจสอบว่าตารางมีอยู่แล้วหรือไม่
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
            return;
        }
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sync_type varchar(50) NOT NULL,
            result longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sync_type (sync_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
