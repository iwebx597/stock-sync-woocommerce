<?php
/**
 * API Client Class
 * เชื่อมต่อกับ Stock API เพื่อดึงข้อมูลสินค้า
 */

if (!defined('ABSPATH')) exit;

class SSW_API_Client {
    private $api_base_url;
    private $api_key;
    private $api_secret;

    /**
     * Max retries for transient API errors (429/500/502/503)
     */
    private $max_retries = 3;

    /**
     * Default request arguments (timeout, redirection, SSL)
     * กัน: SSRF (redirection limit), DoS (timeout), MITM (sslverify)
     */
    private $default_request_args = array(
        'timeout'     => 30,
        'redirection' => 3,
        'sslverify'   => true,
        'blocking'    => true,
    );
    
    public function __construct() {
        $this->api_base_url = esc_url_raw(get_option('ssw_api_base_url', ''));
        $this->api_key = sanitize_text_field(get_option('ssw_api_key', ''));
        $this->api_secret = sanitize_text_field(get_option('ssw_api_secret', ''));
    }
    
    /**
     * ตรวจสอบว่า API URL ถูกต้องและปลอดภัย
     */
    private function validate_api_url() {
        if (empty($this->api_base_url)) {
            $this->log_error('API Base URL is not configured');
            return false;
        }
        
        $parsed = wp_parse_url($this->api_base_url);
        if (empty($parsed['scheme']) || !in_array($parsed['scheme'], array('http', 'https'), true)) {
            $this->log_error('API Base URL must use HTTP or HTTPS scheme');
            return false;
        }
        
        // ป้องกัน SSRF - ไม่อนุญาต request ไปยัง private IP
        if (!empty($parsed['host'])) {
            $ip = gethostbyname($parsed['host']);
            if ($ip && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $this->log_error('API URL resolves to a private/reserved IP address');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * ดึงรายการสินค้าทั้งหมดจาก Stock System
     */
    public function get_products($page = 1, $per_page = 100) {
        if (!$this->validate_api_url()) {
            return false;
        }
        
        $endpoint = trailingslashit($this->api_base_url) . 'products';
        
        $args = array_merge($this->default_request_args, array(
            'headers' => $this->get_headers(),
            'body' => array(
                'page' => absint($page),
                'limit' => absint($per_page),
                'updated_since' => $this->get_last_sync_time()
            )
        ));
        
        $response = $this->request_with_retry('GET', $endpoint, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('API Request Failed: ' . $response->get_error_message());
            return false;
        }
        
        // ตรวจสอบ HTTP status code
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            $this->log_error('API returned HTTP status: ' . $status_code);
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_error('JSON Parse Error: ' . json_last_error_msg());
            return false;
        }
        
        // รองรับ FakeStore API format (array โดยตรง)
        if (is_array($data) && !isset($data['data']) && !isset($data['meta'])) {
            // แปลง FakeStore format มาใช้งาน
            $data = $this->convert_fakestore_format($data);
        }
        
        return $data;
    }

    /**
     * แปลง FakeStore API format ให้เข้ากับ plugin format
     */
    private function convert_fakestore_format($products) {
        $converted = array();
        
        foreach ($products as $product) {
            $converted[] = array(
                'id' => isset($product['id']) ? (string)$product['id'] : '',
                'sku' => isset($product['id']) ? 'FAKESTORE-' . $product['id'] : 'UNKNOWN',
                'name' => isset($product['title']) ? $product['title'] : (isset($product['name']) ? $product['name'] : ''),
                'description' => isset($product['description']) ? $product['description'] : '',
                'short_description' => isset($product['title']) ? substr($product['title'], 0, 100) : '',
                'regular_price' => isset($product['price']) ? (string)$product['price'] : '0.00',
                'sale_price' => '',
                'stock_quantity' => isset($product['stock']) ? (int)$product['stock'] : 100, // Default 100 if not exist
                'categories' => isset($product['category']) ? array($product['category']) : array(),
                'images' => isset($product['image']) ? array($product['image']) : array(),
                'weight' => '0.5'
            );
        }
        
        return array(
            'data' => $converted,
            'meta' => array(
                'total' => count($converted),
                'page' => 1,
                'limit' => count($converted),
                'pages' => 1
            ),
            'has_more' => false
        );
    }
    
    /**
     * ดึงข้อมูลสินค้ารายตัว
     */
    public function get_product($sku) {
        if (!$this->validate_api_url()) {
            return false;
        }
        
        $endpoint = trailingslashit($this->api_base_url) . 'products/' . rawurlencode(sanitize_text_field($sku));
        
        $response = $this->request_with_retry('GET', $endpoint, array_merge($this->default_request_args, array(
            'headers' => $this->get_headers(),
        )));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            return false;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    /**
     * ดึงข้อมูลสต็อกล่าสุด
     */
    public function get_stock_updates() {
        if (!$this->validate_api_url()) {
            return false;
        }
        
        $endpoint = trailingslashit($this->api_base_url) . 'stock/updates';
        
        $response = $this->request_with_retry('GET', $endpoint, array_merge($this->default_request_args, array(
            'headers' => $this->get_headers(),
            'body' => array(
                'since' => $this->get_last_sync_time()
            )
        )));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            return false;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    /**
     * Private method: ตั้งค่า Headers สำหรับ API Request
     */
    private function get_headers() {
        return array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        );
    }
    
    /**
     * Private method: ดึงเวลา Sync ล่าสุด
     */
    private function get_last_sync_time() {
        return get_option('ssw_last_sync_time', gmdate('Y-m-d H:i:s', strtotime('-1 day')));
    }

    /**
     * HTTP request with exponential backoff retry.
     * Retry เฉพาะ transient errors: 429 (rate limit), 500, 502, 503
     * กัน: DoS ต่อ API ปลายทาง + ป้องกัน sync พังเพราะ transient error
     *
     * @param string $method  'GET' or 'POST'
     * @param string $url     Request URL
     * @param array  $args    wp_remote_* args
     * @return array|WP_Error
     */
    private function request_with_retry($method, $url, $args) {
        $retryable_codes = array(429, 500, 502, 503);

        for ($attempt = 0; $attempt <= $this->max_retries; $attempt++) {
            if ($attempt > 0) {
                // Exponential backoff: 2s, 4s, 8s
                $wait = pow(2, $attempt);
                sleep($wait);
                error_log('[Stock Sync] Retry #' . $attempt . ' after ' . $wait . 's backoff');
            }

            $response = ($method === 'POST')
                ? wp_remote_post($url, $args)
                : wp_remote_get($url, $args);

            // Network error — retry
            if (is_wp_error($response)) {
                if ($attempt < $this->max_retries) {
                    continue;
                }
                return $response;
            }

            $status = wp_remote_retrieve_response_code($response);

            // Retryable HTTP status — retry
            if (in_array($status, $retryable_codes, true) && $attempt < $this->max_retries) {
                // Respect Retry-After header if present
                $retry_after = wp_remote_retrieve_header($response, 'retry-after');
                if ($retry_after && is_numeric($retry_after) && (int) $retry_after <= 60) {
                    sleep((int) $retry_after);
                }
                continue;
            }

            return $response;
        }

        return new WP_Error('ssw_max_retries', 'Max API retries exceeded');
    }
    
    /**
     * Private method: บันทึก Error (ห้าม log secret/token)
     * กัน: Secret Leakage — ไม่เขียน Authorization header ลง log
     */
    private function log_error($message) {
        // Strip any accidentally-included auth tokens/keys from error messages
        $safe_message = preg_replace('/Bearer\s+[A-Za-z0-9\-._~+\/]+/i', 'Bearer [REDACTED]', $message);
        $safe_message = preg_replace('/(api[_-]?key|api[_-]?secret|token|authorization)[=:]\s*\S+/i', '$1=[REDACTED]', $safe_message);
        error_log('[Stock Sync] ' . $safe_message);
    }
}
