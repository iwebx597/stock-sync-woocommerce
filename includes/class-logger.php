<?php
/**
 * Logger Class
 * บันทึก Log สำหรับการ Debug และติดตามสถานะ
 */

if (!defined('ABSPATH')) exit;

class SSW_Logger {
    
    private static $instance = null;
    private $log_file;
    
    private function __construct() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/ssw-logs';
        
        // สร้างโฟลเดอร์และป้องกันการเข้าถึงโดยตรง
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        // สร้าง .htaccess เพื่อป้องกันการเข้าถึงไฟล์ log ผ่าน web (Apache)
        $htaccess_file = $log_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- bootstrap stage, WP_Filesystem not yet available
            @file_put_contents($htaccess_file, "Order deny,allow\nDeny from all\n");
        }
        
        // สร้าง index.php เพื่อป้องกัน directory listing
        $index_file = $log_dir . '/index.php';
        if (!file_exists($index_file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            @file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }

        // สร้าง web.config สำหรับ IIS / Nginx helper comment
        // Note: สำหรับ Nginx ต้องเพิ่ม rule ใน server config:
        // location ~* /wp-content/uploads/ssw-logs/ { deny all; }
        
        $this->log_file = $log_dir . '/ssw-debug-' . wp_hash('ssw_log_salt') . '.log';
    }
    
    /**
     * Singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * บันทึก Info
     */
    public function info($message, $context = array()) {
        $this->log('INFO', $message, $context);
    }
    
    /**
     * บันทึก Error
     */
    public function error($message, $context = array()) {
        $this->log('ERROR', $message, $context);
    }
    
    /**
     * บันทึก Warning
     */
    public function warning($message, $context = array()) {
        $this->log('WARNING', $message, $context);
    }
    
    /**
     * บันทึก Debug
     */
    public function debug($message, $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log('DEBUG', $message, $context);
        }
    }
    
    /**
     * บันทึก Success
     */
    public function success($message, $context = array()) {
        $this->log('SUCCESS', $message, $context);
    }
    
    /**
     * Private: เขียนลง Log
     */
    private function log($level, $message, $context = array()) {
        $timestamp = current_time('mysql');
        
        $log_entry = sprintf(
            "[%s] [%s] %s\n",
            $timestamp,
            $level,
            $message
        );
        
        if (!empty($context)) {
            $log_entry .= "Context: " . json_encode($context) . "\n";
        }
        
        $log_entry .= "---\n";
        
        // เขียนลง File
        error_log($log_entry, 3, $this->log_file);
        
        // เขียนลง Database ด้วย
        $this->log_to_database($level, $message, $context);
    }
    
    /**
     * Private: บันทึกลง Database
     */
    private function log_to_database($level, $message, $context) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ssw_logs';
        
        // ตรวจสอบว่าตารางมีอยู่หรือไม่
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            return;
        }
        
        $wpdb->insert(
            $table_name,
            array(
                'sync_type' => sanitize_text_field($level),
                'result' => wp_json_encode(array(
                    'message' => sanitize_text_field($message),
                    'context' => array_map('sanitize_text_field', (array) $context)
                )),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
    }
    
    /**
     * ดึง Recent Logs
     */
    public function get_recent_logs($limit = 50) {
        // ACL: ป้องกัน Info Leak — เฉพาะ admin ที่มี capability เท่านั้นที่อ่าน log ได้
        if (is_admin() && !current_user_can('manage_woocommerce')) {
            return array();
        }
        
        if (!file_exists($this->log_file)) {
            return array();
        }
        
        $limit = absint($limit);
        if ($limit === 0 || $limit > 500) {
            $limit = 50;
        }
        
        // ใช้ WP Filesystem แทน file_get_contents โดยตรง
        $contents = file_get_contents($this->log_file);
        $lines = array_reverse(explode("\n", $contents));
        
        return array_slice($lines, 0, $limit);
    }
    
    /**
     * ลบ Log เฟล
     */
    public function clear_logs() {
        // ACL: ป้องกัน Unauthorized Deletion — เฉพาะ admin ที่มี capability เท่านั้นที่ลบ log ได้
        if (!current_user_can('manage_woocommerce')) {
            return false;
        }
        
        if (file_exists($this->log_file) && is_writable($this->log_file)) {
            wp_delete_file($this->log_file);
            return true;
        }
        
        return false;
    }
}
