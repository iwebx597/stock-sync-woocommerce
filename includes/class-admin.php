<?php
/**
 * Admin Interface Class
 * หน้าตั้งค่าและจัดการ Plugin ใน WordPress Admin
 */

if (!defined('ABSPATH')) exit;

class SSW_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_ssw_manual_sync', array($this, 'ajax_manual_sync'));
        add_action('wp_ajax_ssw_test_connection', array($this, 'ajax_test_connection'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * เพิ่มเมนู Admin
     */
    public function add_menu_page() {
        add_submenu_page(
            'woocommerce',
            __('Stock Sync Settings', 'ssw'),
            __('Stock Sync', 'ssw'),
            'manage_woocommerce',
            'ssw-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * ลงทะเบียน Settings
     */
    public function register_settings() {
        register_setting('ssw_settings', 'ssw_api_base_url', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_api_url'),
        ));
        register_setting('ssw_settings', 'ssw_api_key', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_api_key'),
        ));
        register_setting('ssw_settings', 'ssw_api_secret', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_api_secret'),
        ));
        register_setting('ssw_settings', 'ssw_sync_frequency', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_sync_frequency'),
        ));
        register_setting('ssw_settings', 'ssw_auto_publish', array(
            'type' => 'boolean',
            'sanitize_callback' => 'absint',
        ));
    }
    
    /**
     * Sanitize API URL - ตรวจสอบว่าเป็น HTTPS URL ที่ถูกต้อง
     */
    public function sanitize_api_url($url) {
        $url = esc_url_raw(trim($url));
        
        if (!empty($url) && !preg_match('/^https?:\/\//', $url)) {
            add_settings_error(
                'ssw_api_base_url',
                'invalid_url',
                __('API Base URL must be a valid HTTP/HTTPS URL.', 'ssw')
            );
            return get_option('ssw_api_base_url', '');
        }
        
        return $url;
    }
    
    /**
     * Sanitize Sync Frequency — whitelist เฉพาะค่าที่อนุญาต
     * กัน: Data Injection — ป้องกันค่าที่ไม่คาดคิดถูกบันทึก
     */
    public function sanitize_sync_frequency($value) {
        $allowed = array('fifteen_minutes', 'hourly', 'six_hours', 'twicedaily', 'daily');
        if (in_array($value, $allowed, true)) {
            return $value;
        }
        // คืนค่าเดิมหรือ default
        return get_option('ssw_sync_frequency', 'six_hours');
    }

    /**
     * Sanitize API Key — ถ้าส่งค่าว่างให้เก็บค่าเดิม (ป้องกันเขียนทับค่าที่ mask อยู่)
     * กัน: Secret Leak — form submit ส่ง masked value กลับมาไม่ให้เขียนทับค่าเดิม
     */
    public function sanitize_api_key($value) {
        $value = sanitize_text_field($value);
        // ถ้าส่งค่าว่าง หรือ ค่าที่เป็น masked placeholder → เก็บค่าเดิม
        if (empty($value) || preg_match('/^\*+/', $value)) {
            return get_option('ssw_api_key', '');
        }
        return $value;
    }

    /**
     * Sanitize API Secret — เหมือน API Key
     */
    public function sanitize_api_secret($value) {
        $value = sanitize_text_field($value);
        if (empty($value) || preg_match('/^\*+/', $value)) {
            return get_option('ssw_api_secret', '');
        }
        return $value;
    }
    
    /**
     * Enqueue Admin Scripts and Styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'ssw-settings') === false) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'ssw-admin-css',
            SSW_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SSW_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'ssw-admin-js',
            SSW_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            SSW_VERSION,
            true
        );
        
        // Localize script data — CSRF: แยก nonce ตาม action เพื่อจำกัด scope
        wp_localize_script('ssw-admin-js', 'ssw_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce_test' => wp_create_nonce('ssw_test_connection_nonce'),
            'nonce_sync' => wp_create_nonce('ssw_manual_sync_nonce'),
        ));
    }
    
    /**
     * แสดงหน้าตั้งค่า
     */
    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            // XSS: escape translated string ใน wp_die ป้องกัน XSS ผ่าน .mo/.po file ที่ถูก tamper
            wp_die(esc_html__('You do not have permission to access this page.', 'ssw'));
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Stock Sync Settings', 'ssw'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php echo esc_html__('Configure your Stock API connection below.', 'ssw'); ?></p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('ssw_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th>
                            <label for="ssw_api_base_url"><?php echo esc_html__('API Base URL', 'ssw'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="ssw_api_base_url"
                                   name="ssw_api_base_url" 
                                   value="<?php echo esc_attr(get_option('ssw_api_base_url')); ?>" 
                                   class="regular-text" 
                                   placeholder="https://api.yourstock.com/v1/">
                            <p class="description"><?php echo esc_html__('Enter the base URL of your Stock API', 'ssw'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="ssw_api_key"><?php echo esc_html__('API Key', 'ssw'); ?></label>
                        </th>
                        <td>
                            <?php
                            // Secret Masking: ไม่ส่งค่าจริงใน HTML — แสดงเฉพาะ 4 ตัวท้าย (กัน Info Leak ผ่าน view-source)
                            $api_key_raw = get_option('ssw_api_key', '');
                            $api_key_display = '';
                            if (!empty($api_key_raw)) {
                                $api_key_display = str_repeat('*', max(0, strlen($api_key_raw) - 4)) . substr($api_key_raw, -4);
                            }
                            ?>
                            <input type="password" 
                                   id="ssw_api_key"
                                   name="ssw_api_key" 
                                   value="<?php echo esc_attr($api_key_display); ?>" 
                                   class="regular-text"
                                   autocomplete="off">
                            <p class="description"><?php echo esc_html__('Your API authentication key (leave blank to keep current value)', 'ssw'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="ssw_api_secret"><?php echo esc_html__('API Secret', 'ssw'); ?></label>
                        </th>
                        <td>
                            <?php
                            $api_secret_raw = get_option('ssw_api_secret', '');
                            $api_secret_display = '';
                            if (!empty($api_secret_raw)) {
                                $api_secret_display = str_repeat('*', max(0, strlen($api_secret_raw) - 4)) . substr($api_secret_raw, -4);
                            }
                            ?>
                            <input type="password" 
                                   id="ssw_api_secret"
                                   name="ssw_api_secret" 
                                   value="<?php echo esc_attr($api_secret_display); ?>" 
                                   class="regular-text"
                                   autocomplete="off">
                            <p class="description"><?php echo esc_html__('Your API secret key (leave blank to keep current value)', 'ssw'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="ssw_auto_publish"><?php echo esc_html__('Auto Publish Products', 'ssw'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="ssw_auto_publish"
                                       name="ssw_auto_publish" 
                                       value="1" 
                                       <?php checked(get_option('ssw_auto_publish'), 1); ?>>
                                <?php echo esc_html__('Publish products immediately after sync', 'ssw'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr style="margin-top: 30px;">
            
            <h2><?php echo esc_html__('Actions', 'ssw'); ?></h2>
            <p>
                <button id="ssw-test-connection" class="button"><?php echo esc_html__('Test API Connection', 'ssw'); ?></button>
                <button id="ssw-manual-sync" class="button button-primary"><?php echo esc_html__('Run Full Sync Now', 'ssw'); ?></button>
            </p>
            
            <div id="ssw-result" style="margin-top: 20px;"></div>
            
            <h2><?php echo esc_html__('Sync Status', 'ssw'); ?></h2>
            <table class="widefat" style="margin-top: 10px;">
                <tbody>
                    <tr>
                        <td><strong><?php echo esc_html__('Last Full Sync:', 'ssw'); ?></strong></td>
                        <td><?php echo esc_html(get_option('ssw_last_sync_time', __('Never', 'ssw'))); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Last Stock Sync:', 'ssw'); ?></strong></td>
                        <td><?php echo esc_html(get_option('ssw_last_stock_sync', __('Never', 'ssw'))); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Products Synced:', 'ssw'); ?></strong></td>
                        <td><?php echo esc_html(get_option('ssw_last_sync_count', 0)); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Recent Errors:', 'ssw'); ?></strong></td>
                        <td><?php echo esc_html(get_option('ssw_last_sync_errors', 0)); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <h2 style="margin-top: 30px;"><?php echo esc_html__('Recent Logs', 'ssw'); ?></h2>
            <?php $this->render_logs_table(); ?>
        </div>
        <?php
    }
    
    /**
     * AJAX: ทดสอบการเชื่อมต่อ API
     */
    public function ajax_test_connection() {
        // CSRF: nonce เฉพาะสำหรับ test connection (read-only action)
        check_ajax_referer('ssw_test_connection_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'ssw'));
        }
        
        require_once SSW_PLUGIN_DIR . 'includes/class-api-client.php';
        $client = new SSW_API_Client();
        $result = $client->get_products(1, 1);
        
        if ($result) {
            wp_send_json_success(__('Connection successful! API is responding.', 'ssw'));
        } else {
            wp_send_json_error(__('Connection failed. Please check your API settings.', 'ssw'));
        }
    }
    
    /**
     * AJAX: Manual Sync
     */
    public function ajax_manual_sync() {
        // CSRF: nonce เฉพาะสำหรับ manual sync (write action — สร้าง/แก้ products)
        check_ajax_referer('ssw_manual_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'ssw'));
        }
        
        require_once SSW_PLUGIN_DIR . 'includes/class-product-sync.php';
        $sync = new SSW_Product_Sync();
        $result = $sync->sync_all_products();
        
        wp_send_json_success($result);
    }
    
    /**
     * แสดงตารางฟังก์ชัน Log
     */
    private function render_logs_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ssw_logs';
        
        // ตรวจสอบว่าตารางมีอยู่จริง
        $table_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
        );
        if ($table_exists !== $table_name) {
            echo '<p>' . esc_html__('Log table not found. Please deactivate and reactivate the plugin.', 'ssw') . '</p>';
            return;
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe (prefix + hardcoded string)
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table_name}` ORDER BY id DESC LIMIT %d",
                20
            )
        );
        
        if (empty($logs)) {
            echo '<p>' . esc_html__('No logs found yet.', 'ssw') . '</p>';
            return;
        }
        
        echo '<table class="widefat" style="margin-top: 10px;">';
        echo '<thead><tr><th>' . esc_html__('Time', 'ssw') . '</th><th>' . esc_html__('Type', 'ssw') . '</th><th>' . esc_html__('Result', 'ssw') . '</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($logs as $log) {
            echo '<tr>';
            echo '<td>' . esc_html($log->created_at) . '</td>';
            echo '<td><strong>' . esc_html($log->sync_type) . '</strong></td>';
            // XSS: esc_html + mb_strimwidth ป้องกัน multibyte char ตัดกลางคำสร้าง broken entity
            echo '<td><code>' . esc_html(mb_strimwidth($log->result, 0, 100, '...', 'UTF-8')) . '</code></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
}
