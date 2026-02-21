<?php
/**
 * Product Sync Class
 * จัดการการ Sync สินค้า กับ WooCommerce
 */

if (!defined('ABSPATH')) exit;

class SSW_Product_Sync {
    private $api_client;
    
    public function __construct() {
        $this->api_client = new SSW_API_Client();
    }
    
    /**
     * Sync สินค้าทั้งหมด
     */
    public function sync_all_products() {
        $page = 1;
        $has_more = true;
        $synced_count = 0;
        $error_count = 0;
        // DoS prevention: จำกัด pagination ไม่เกิน 200 หน้า (ป้องกัน API ตอบ has_more=true ตลอด → infinite loop)
        $max_pages = 200;
        
        while ($has_more && $page <= $max_pages) {
            $products = $this->api_client->get_products($page, 50);
            
            if (!$products || empty($products['data'])) {
                break;
            }
            
            foreach ($products['data'] as $product_data) {
                $result = $this->sync_single_product($product_data);
                
                if ($result) {
                    $synced_count++;
                } else {
                    $error_count++;
                }
                
                // Delay เล็กน้อยเพื่อไม่ให้ Server โอเวอร์โหลด
                usleep(100000); // 0.1 วินาที
            }
            
            $has_more = !empty($products['has_more']) || count($products['data']) === 50;
            $page++;
        }

        if ($page > $max_pages) {
            error_log('[Stock Sync] Warning: sync_all_products reached max page limit (' . $max_pages . ')');
        }
        
        // บันทึกเวลา Sync ล่าสุด
        update_option('ssw_last_sync_time', current_time('mysql'));
        update_option('ssw_last_sync_count', $synced_count);
        update_option('ssw_last_sync_errors', $error_count);
        
        return array(
            'synced' => $synced_count,
            'errors' => $error_count
        );
    }
    
    /**
     * Sync สินค้ารายตัว
     */
    public function sync_single_product($data) {
        try {
            // Validate ข้อมูลที่จำเป็น
            if (empty($data['sku']) || empty($data['name'])) {
                error_log('[Stock Sync] Missing required fields (sku or name)');
                return false;
            }
            
            // Sanitize ข้อมูลจาก API ก่อนบันทึก
            $data = $this->sanitize_product_data($data);
            
            // หา Product ที่มีอยู่แล้วจาก SKU
            $existing_id = wc_get_product_id_by_sku($data['sku']);
            
            if ($existing_id) {
                $product = wc_get_product($existing_id);
            } else {
                // สร้าง Product ใหม่
                $product = new WC_Product();
            }
            
            // ตั้งค่าข้อมูลพื้นฐาน
            $product->set_name($data['name']);
            $product->set_sku($data['sku']);
            $product->set_description($data['description'] ?? '');
            $product->set_short_description($data['short_description'] ?? '');
            
            // ราคา - Validate เป็นตัวเลข
            $regular_price = is_numeric($data['regular_price']) ? abs(floatval($data['regular_price'])) : 0;
            $product->set_regular_price($regular_price);
            if (!empty($data['sale_price']) && is_numeric($data['sale_price'])) {
                $sale_price = abs(floatval($data['sale_price']));
                if ($sale_price < $regular_price) {
                    $product->set_sale_price($sale_price);
                }
            }
            
            // สต็อก - Validate เป็นตัวเลขจำนวนเต็ม และต้อง >= 0 (กัน Data Poisoning: stock ติดลบ)
            $stock_quantity = isset($data['stock_quantity']) ? max(0, intval($data['stock_quantity'])) : 0;
            $product->set_manage_stock(true);
            $product->set_stock_quantity($stock_quantity);
            $product->set_stock_status($stock_quantity > 0 ? 'instock' : 'outofstock');
            
            // หมวดหมู่
            if (!empty($data['categories'])) {
                $cat_ids = $this->get_or_create_categories($data['categories']);
                $product->set_category_ids($cat_ids);
            }
            
            // รูปภาพ
            if (!empty($data['images'])) {
                $image_id = $this->upload_product_image($data['images'][0], $product->get_id());
                if ($image_id) {
                    $product->set_image_id($image_id);
                }
            }
            
            // คุณลักษณะ (Attributes) สำหรับ Variations
            if (!empty($data['attributes'])) {
                $this->set_product_attributes($product, $data['attributes']);
            }
            
            // น้ำหนักและขนาด
            if (!empty($data['weight'])) {
                $product->set_weight($data['weight']);
            }
            
            $product->save();
            
            // บันทึก Meta สำหรับอ้างอิง
            update_post_meta($product->get_id(), '_ssw_external_id', sanitize_text_field($data['id']));
            update_post_meta($product->get_id(), '_ssw_last_sync', current_time('mysql'));
            
            return true;
            
        } catch (Exception $e) {
            // Log Injection prevention: sanitize user-controllable data ก่อนเขียน log
            $safe_sku = isset($data['sku']) ? sanitize_text_field($data['sku']) : 'unknown';
            error_log('[Stock Sync] Error syncing product ' . $safe_sku . ': ' . sanitize_text_field($e->getMessage()));
            return false;
        }
    }
    
    /**
     * Sync Variable Products (สินค้ามีตัวเลือก)
     */
    public function sync_variable_product($data) {
        try {
            // Validate ข้อมูลที่จำเป็น
            if (empty($data['sku']) || empty($data['name'])) {
                error_log('[Stock Sync] Missing required fields for variable product');
                return false;
            }
            
            // Sanitize ข้อมูลจาก API
            $data = $this->sanitize_product_data($data);
            
            // หา Parent Product ที่มีอยู่แล้ว
            $existing_id = wc_get_product_id_by_sku($data['sku']);
            
            if ($existing_id) {
                $parent = wc_get_product($existing_id);
            } else {
                // สร้าง Variable Product ใหม่
                $parent = new WC_Product_Variable();
            }
            
            $parent->set_name($data['name']);
            $parent->set_sku($data['sku']);
            $parent->set_description($data['description'] ?? '');
            
            // สร้าง Attributes
            if (!empty($data['variations']['attributes'])) {
                $attributes = array();
                foreach ($data['variations']['attributes'] as $attr_name => $attr_values) {
                    $attribute = new WC_Product_Attribute();
                    $attribute->set_name(sanitize_text_field($attr_name));
                    $attribute->set_options(array_map('sanitize_text_field', $attr_values));
                    $attribute->set_visible(true);
                    $attribute->set_variation(true);
                    $attributes[] = $attribute;
                }
                $parent->set_attributes($attributes);
            }
            
            $parent_id = $parent->save();
            
            // สร้าง Variations (Children)
            if (!empty($data['variations']['items'])) {
                foreach ($data['variations']['items'] as $variation_data) {
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id($parent_id);
                    $variation->set_sku(sanitize_text_field($variation_data['sku']));
                    $variation->set_regular_price(abs(floatval($variation_data['price'])));
                    // Data Integrity: variation stock ต้อง >= 0 (กัน Data Poisoning)
                    $stock = max(0, intval($variation_data['stock']));
                    $variation->set_stock_quantity($stock);
                    $variation->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
                    
                    // ตั้งค่า Attributes สำหรับ Variation นี้
                    $var_attributes = array();
                    if (!empty($variation_data['attributes'])) {
                        foreach ($variation_data['attributes'] as $attr_name => $attr_value) {
                            // Sanitize: attribute value จาก external API (กัน Data Injection/XSS)
                            $var_attributes['attribute_' . sanitize_title($attr_name)] = sanitize_text_field($attr_value);
                        }
                    }
                    $variation->set_attributes($var_attributes);
                    $variation->save();
                }
            }
            
            update_post_meta($parent_id, '_ssw_external_id', sanitize_text_field($data['id']));
            update_post_meta($parent_id, '_ssw_last_sync', current_time('mysql'));
            
            return true;
            
        } catch (Exception $e) {
            // Log Injection prevention: sanitize user-controllable data
            $safe_sku = isset($data['sku']) ? sanitize_text_field($data['sku']) : 'unknown';
            error_log('[Stock Sync] Error syncing variable product ' . $safe_sku . ': ' . sanitize_text_field($e->getMessage()));
            return false;
        }
    }
    
    /**
     * อัปเดตเฉพาะสต็อก (เร็วกว่า Full Sync)
     */
    public function sync_stock_only() {
        $stock_updates = $this->api_client->get_stock_updates();
        
        if (!$stock_updates) {
            return false;
        }
        
        $updated = 0;
        foreach ($stock_updates as $update) {
            // Sanitize: ข้อมูลจาก external API ต้อง sanitize ทุกครั้ง (กัน Data Injection)
            if (empty($update['sku']) || !isset($update['quantity'])) {
                continue;
            }
            $sku = sanitize_text_field($update['sku']);
            $quantity = max(0, intval($update['quantity']));
            
            $product_id = wc_get_product_id_by_sku($sku);
            
            if ($product_id) {
                $product = wc_get_product($product_id);
                $product->set_stock_quantity($quantity);
                $product->set_stock_status($quantity > 0 ? 'instock' : 'outofstock');
                $product->save();
                $updated++;
            }
        }
        
        update_option('ssw_last_stock_sync', current_time('mysql'));
        return $updated;
    }
    
    /**
     * Batch Processing สำหรับสินค้าจำนวนมาก
     */
    public function sync_batch($batch_number = 0) {
        $per_batch = 50;
        // DoS prevention: จำกัด batch ไม่เกิน 100 รอบ (5,000 products)
        $max_batches = 100;
        if ($batch_number >= $max_batches) {
            error_log('[Stock Sync] Batch sync reached max batch limit (' . $max_batches . ')');
            return false;
        }

        $offset = $batch_number * $per_batch;
        
        $products = $this->api_client->get_products(1, $per_batch);
        
        if (empty($products['data'])) {
            return false;
        }
        
        foreach ($products['data'] as $product) {
            $this->sync_single_product($product);
        }
        
        // ถ้ายังมีอีก ให้ Schedule Cron ถัดไป
        if (count($products['data']) === $per_batch) {
            wp_schedule_single_event(time() + 60, 'ssw_batch_sync', array($batch_number + 1));
        }
        
        return true;
    }
    
    /**
     * Queue System (ใช้ Action Scheduler ของ WooCommerce)
     */
    public function queue_product_sync($product_data) {
        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(
                time(),
                'ssw_process_product_sync',
                array('product' => $product_data),
                'ssw-sync'
            );
        }
    }
    
    /**
     * Private method: จัดการรูปภาพสินค้า
     */
    private function upload_product_image($image_url, $product_id) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Validate URL scheme (ป้องกัน SSRF)
        $image_url = esc_url_raw($image_url);
        $parsed_url = wp_parse_url($image_url);
        
        if (empty($parsed_url['scheme']) || !in_array($parsed_url['scheme'], array('http', 'https'), true)) {
            // Log Injection prevention: sanitize URL before writing to log
            error_log('[Stock Sync] Invalid image URL scheme: ' . esc_url_raw($image_url));
            return false;
        }
        
        // ป้องกัน local/private IP (SSRF prevention)
        if (!empty($parsed_url['host'])) {
            $host = $parsed_url['host'];
            $ip = gethostbyname($host);
            if ($ip && $this->is_private_ip($ip)) {
                // Log Injection prevention: sanitize URL before writing to log
                error_log('[Stock Sync] Blocked private IP image URL: ' . esc_url_raw($image_url));
                return false;
            }
        }
        
        // ตรวจสอบว่าเคยอัปโหลดรูปนี้แล้วหรือไม่
        $existing = $this->get_image_by_url($image_url);
        if ($existing) {
            return $existing;
        }
        
        $tmp = download_url($image_url, 30); // timeout 30s ป้องกัน hang จาก slow/malicious server
        
        if (is_wp_error($tmp)) {
            return false;
        }
        
        // Sanitize filename: ตัด query string + fragment ออก, จำกัดเฉพาะ extension รูปภาพที่อนุญาต
        $url_path = wp_parse_url($image_url, PHP_URL_PATH);
        $filename = $url_path ? sanitize_file_name(basename($url_path)) : 'image.jpg';

        // File extension whitelist — ป้องกัน RCE ผ่าน disguised file (เช่น .php, .phtml)
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg');
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($file_ext, $allowed_extensions, true)) {
            @unlink($tmp);
            error_log('[Stock Sync] Blocked image upload with disallowed extension: ' . sanitize_text_field($file_ext));
            return false;
        }

        $file_array = array(
            'name' => $filename,
            'tmp_name' => $tmp
        );
        
        $id = media_handle_sideload($file_array, $product_id);
        
        if (is_wp_error($id)) {
            @unlink($tmp);
            return false;
        }
        
        // บันทึก URL ต้นทางเป็น Meta
        update_post_meta($id, '_ssw_source_url', esc_url_raw($image_url));
        
        return $id;
    }
    
    /**
     * Private method: ดึงรูปภาพจาก URL
     */
    private function get_image_by_url($image_url) {
        global $wpdb;
        
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ssw_source_url' AND meta_value = %s",
            $image_url
        ));
        
        return $post_id ? $post_id : false;
    }
    
    /**
     * Private method: สร้างหรือหาหมวดหมู่
     */
    private function get_or_create_categories($categories) {
        $ids = array();
        
        foreach ($categories as $cat_name) {
            $cat_name = sanitize_text_field($cat_name);
            if (empty($cat_name)) {
                continue;
            }
            
            $term = get_term_by('name', $cat_name, 'product_cat');
            
            if (!$term) {
                $result = wp_insert_term($cat_name, 'product_cat');
                if (!is_wp_error($result)) {
                    $ids[] = $result['term_id'];
                }
            } else {
                $ids[] = $term->term_id;
            }
        }
        
        return $ids;
    }
    
    /**
     * Private method: ตั้งค่า Attributes ของสินค้า
     */
    private function set_product_attributes($product, $attributes) {
        $product_attributes = array();
        
        foreach ($attributes as $attr_name => $attr_values) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name(sanitize_text_field($attr_name));
            $attribute->set_options(array_map('sanitize_text_field', (array) $attr_values));
            $attribute->set_visible(true);
            $product_attributes[] = $attribute;
        }
        
        $product->set_attributes($product_attributes);
    }
    
    /**
     * Sanitize ข้อมูลสินค้าจาก External API
     */
    private function sanitize_product_data($data) {
        return array(
            'id'                => isset($data['id']) ? sanitize_text_field($data['id']) : '',
            'sku'               => isset($data['sku']) ? sanitize_text_field($data['sku']) : '',
            'name'              => isset($data['name']) ? sanitize_text_field($data['name']) : '',
            'description'       => isset($data['description']) ? wp_kses_post($data['description']) : '',
            'short_description' => isset($data['short_description']) ? wp_kses_post($data['short_description']) : '',
            'regular_price'     => isset($data['regular_price']) && is_numeric($data['regular_price']) ? abs(floatval($data['regular_price'])) : 0,
            'sale_price'        => isset($data['sale_price']) && is_numeric($data['sale_price']) ? abs(floatval($data['sale_price'])) : '',
            'stock_quantity'    => isset($data['stock_quantity']) ? max(0, intval($data['stock_quantity'])) : 0,
            'categories'        => isset($data['categories']) ? array_map('sanitize_text_field', (array) $data['categories']) : array(),
            'images'            => isset($data['images']) ? array_map('esc_url_raw', (array) $data['images']) : array(),
            'weight'            => isset($data['weight']) && is_numeric($data['weight']) ? abs(floatval($data['weight'])) : '',
            'attributes'        => isset($data['attributes']) ? $data['attributes'] : array(),
            'variations'        => isset($data['variations']) ? $data['variations'] : array(),
        );
    }
    
    /**
     * ตรวจสอบว่า IP เป็น Private/Reserved IP หรือไม่ (ป้องกัน SSRF)
     */
    private function is_private_ip($ip) {
        // Block private, reserved, and loopback IPs
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
