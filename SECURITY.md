# Security Policy — Stock Sync WooCommerce

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.0.x   | ✅ Active  |

## Reporting a Vulnerability

หากคุณพบช่องโหว่ด้านความปลอดภัย **โปรดอย่าเปิด Issue สาธารณะ**

### วิธีรายงาน

1. ส่งอีเมลไปที่ **security@iwebxstudio.com**
2. ระบุรายละเอียด:
   - ประเภทของช่องโหว่ (XSS, SQLi, CSRF, RCE ฯลฯ)
   - ไฟล์และบรรทัดที่พบปัญหา
   - ขั้นตอนในการ reproduce
   - ผลกระทบที่อาจเกิดขึ้น
3. เราจะตอบกลับภายใน **48 ชั่วโมง**
4. โปรดอย่าเปิดเผยช่องโหว่จนกว่าจะได้รับการแก้ไข

## Security Measures

มาตรการรักษาความปลอดภัยที่ปลั๊กอินนี้ใช้:

### 1. Access Control
- ทุกไฟล์ PHP มี `if (!defined('ABSPATH')) exit;` ป้องกัน direct access
- Admin functions ตรวจสอบ `current_user_can('manage_woocommerce')`
- Log read/delete ตรวจสอบ capability ก่อน return ข้อมูล
- mock-api.php บล็อกโดย default — อนุญาตเฉพาะเมื่อ `WP_DEBUG=true`

### 2. CSRF Protection
- ใช้ nonce แยกต่างหากสำหรับแต่ละ action (`ssw_test_connection_nonce`, `ssw_manual_sync_nonce`)
- Settings page ใช้ `settings_fields()` ที่สร้าง nonce อัตโนมัติ
- ทุก AJAX handler ตรวจ `check_ajax_referer()` ก่อนดำเนินการ

### 3. Input Validation & Sanitization
- ข้อมูลจาก external API ผ่าน `sanitize_product_data()` ก่อนบันทึก (field-by-field)
- ราคา/น้ำหนัก ตรวจ `is_numeric()` + `abs(floatval())`
- SKU/ชื่อ ผ่าน `sanitize_text_field()`
- Description ผ่าน `wp_kses_post()`
- URLs ผ่าน `esc_url_raw()`
- Sync frequency ใช้ whitelist validation
- Register settings มี `sanitize_callback` ครบทุก field

### 4. Output Escaping
- HTML output ใช้ `esc_html()`, `esc_attr()`, `esc_url()` ครบ
- `wp_die()` ใช้ `esc_html__()` ป้องกัน XSS ผ่าน tampered .mo file
- AJAX responses ใช้ `wp_send_json_success/error` (auto-encode)
- Multibyte-safe truncation ด้วย `mb_strimwidth()`

### 5. SQL Injection Prevention
- ทุก query ใช้ `$wpdb->prepare()` กับ placeholders (`%s`, `%d`)
- `$wpdb->insert()` ใช้ format specifier array
- ชื่อตารางมาจาก `$wpdb->prefix` + hardcoded string เท่านั้น
- ไม่มี dynamic column names หรือ ORDER BY จาก user input

### 6. SSRF Protection
- API client มี `validate_api_url()` บล็อก private/reserved IP
- Image upload ตรวจ URL scheme (http/https only) + private IP check
- ใช้ `filter_var()` กับ `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`

### 7. File Upload Security
- Image filename ผ่าน `sanitize_file_name()` + ตัด query string
- Extension whitelist: jpg, jpeg, png, gif, webp, bmp, svg
- Non-image extensions ถูกบล็อก + tmp file ถูกลบ (ป้องกัน RCE)
- `media_handle_sideload()` ทำ MIME type check เพิ่มเติม

### 8. Log File Protection
- Log directory: `/wp-content/uploads/ssw-logs/`
- `.htaccess` — `Deny from all` (Apache)
- `index.php` — ป้องกัน directory listing
- Log filename มี hash: `ssw-debug-{wp_hash}.log`
- **Nginx**: ต้องเพิ่ม rule ใน server config:
  ```nginx
  location ~* /wp-content/uploads/ssw-logs/ { deny all; }
  ```

### 9. API Key Storage
- API credentials เก็บใน `wp_options` (encrypted at rest ถ้า DB encrypted)
- ส่งผ่าน `Authorization: Bearer` header กับ HTTPS (`sslverify => true`)
- ไม่แสดง secret ใน log หรือ error messages
- **Secret Masking**: หน้า Settings แสดงเฉพาะ 4 ตัวท้ายของ key/secret (กัน Info Leak ผ่าน view-source)
- **Log Redaction**: `log_error()` strip `Bearer` token และ credentials จาก error messages อัตโนมัติ
- Sanitize callback ตรวจจับ masked value (`***...`) → เก็บค่าเดิมไว้ไม่เขียนทับ

### 10. Cron Overlap & DoS Prevention
- ทุก cron action (`full_sync`, `stock_sync`, `batch_sync`) ใช้ **transient-based lock** กัน concurrent runs
- Lock มี TTL (30/10/15 นาที) เป็น safety net กรณี process crash
- `try/finally` ปล่อย lock ทุกกรณี (success + exception)
- `sync_all_products()` จำกัด **max 200 pages** ป้องกัน infinite pagination
- `sync_batch()` จำกัด **max 100 batches** (5,000 products)

### 11. API Resilience & Rate Limiting
- **Retry with Exponential Backoff**: retry สูงสุด 3 ครั้งสำหรับ HTTP 429/500/502/503
- Backoff: 2s → 4s → 8s (exponential) + respect `Retry-After` header (≤ 60s)
- **Default request config**: `timeout=30s`, `redirection=3`, `sslverify=true`
- `download_url()` สำหรับ images มี `timeout=30s`
- `usleep(100ms)` ระหว่าง product sync ลด API load

### 12. Data Integrity
- **Stock ≥ 0**: ทุกจุดที่ set stock quantity ใช้ `max(0, intval(...))` (simple + variable + stock-only)
- **Price ≥ 0**: ราคาใช้ `abs(floatval(...))` + sale price ต้อง < regular price
- **Timezone**: ใช้ `gmdate()` สำหรับ API timestamps (ไม่ใช่ `date()`)
- **SKU Mapping**: ใช้ `wc_get_product_id_by_sku()` (WC core) + `_ssw_external_id` meta สำหรับ external ID mapping

## Security Checklist for Contributors

ก่อน submit code ใหม่ ตรวจสอบ:

- [ ] ทุกไฟล์ PHP มี ABSPATH guard
- [ ] Input จาก user/API ผ่าน `sanitize_*()` ก่อนใช้งาน
- [ ] Output ไป HTML ผ่าน `esc_*()` ก่อนแสดง
- [ ] SQL queries ใช้ `$wpdb->prepare()` ครบ
- [ ] AJAX handlers มี nonce check + capability check
- [ ] ไม่ hardcode credentials ใน source code
- [ ] File operations ตรวจ path traversal
- [ ] External URLs ตรวจ SSRF (scheme + private IP)
- [ ] Error messages ไม่เปิดเผย internal details ให้ end user

## Dependencies

- WordPress 5.6+
- WooCommerce 5.0+
- PHP 7.4+

ไม่มี third-party library dependencies ภายนอก WordPress/WooCommerce core
