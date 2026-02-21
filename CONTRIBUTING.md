# Contributing Guide

ขอบคุณที่สนใจในการมีส่วนร่วมใน Stock Sync Plugin!

## วิธีการมีส่วนร่วม

### Report Bugs

ถ้าพบ Bug ให้ติดต่อทีม Support พร้อมข้อมูล:

1. **ขั้นตอนที่จะ Repeat ปัญหา**
2. **ความคาดหวัง vs ความเป็นจริง**
3. **Environment Info**:
   - WordPress Version
   - WooCommerce Version
   - PHP Version
   - Plugin Version
4. **Screenshots/Logs** (ถ้ามี)

### Request Features

เพื่อถูกพิจารณา Feature Request ต้องมี:

1. **Clear Description** - อธิบายฟีเจอร์ที่ต้องการ
2. **Use Case** - ทำไมต้องการฟีเจอร์นี้
3. **Benefits** - ประโยชน์ที่จะได้รับ
4. **Examples** - ตัวอย่างการใช้งาน

### Submit Code

#### ก่อนเริ่ม

1. Fork Repository (ถ้ามี)
2. สร้าง Branch ใหม่:
   ```bash
   git checkout -b feature/your-feature-name
   ```

#### Code Standards

ปลักอินต้องทำตาม:

1. **WordPress Coding Standards**
   - ใช้ Snake Case สำหรับ Function/Variable
   - ใช้ WordPress Functions (ไม่ใช้ PHP native)
   - Comment ทุกฟังก์ชัน

2. **Security**
   - `sanitize_*()` สำหรับ Input
   - `esc_*()` สำหรับ Output
   - `wp_verify_nonce()` สำหรับ Forms

3. **Performance**
   - Avoid N+1 Queries
   - Use Caching ที่เหมาะสม
   - Optimize Loops

4. **PHP Version**
   - Support PHP 7.4+
   - ใช้ modern PHP features (แต่ compatible)

#### Code Structure

```php
<?php
/**
 * Short description of what this does.
 *
 * Longer description if needed.
 * 
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

class My_Class {
    /**
     * Constructor
     */
    public function __construct() {
        // Constructor code
    }
    
    /**
     * Public method with description.
     *
     * @param string $param_name Description
     * @return bool|WP_Error
     */
    public function my_method($param_name) {
        // Method code
    }
}
```

#### Testing

ทดสอบโค้ดของคุณ:

1. **Local Testing**
   - ติดตั้ง WordPress + WooCommerce
   - ทดสอบทุก Code Path
   - ตรวจสอบ Edge Cases

2. **Browser Testing**
   - Chrome
   - Firefox
   - Safari
   - Mobile Browsers

3. **PHP Syntax**
   ```bash
   php -l includes/class-your-file.php
   ```

#### Commit Messages

เขียน Clear Commit Messages:

```
[Feature] Add new sync method
[Fix] Resolve API connection issue
[Docs] Update installation guide
[Refactor] Improve product-sync performance
```

Good Examples:
- `[Feature] Add WP-CLI commands for manual sync`
- `[Fix] Prevent duplicate SKU issues in batch sync`
- `[Docs] Add API rate limiting documentation`

Bad Examples:
- `fixed bug`
- `update`
- `blah`

### Pull Request / Merge Request

1. **Description**: เขียน Clear Description
   ```
   What: เปลี่ยนแปลงอะไร
   Why: ทำไมต้องเปลี่ยน
   How: ปรับเปลี่ยนวิธีไหน
   Testing: ทดสอบยังไง
   ```

2. **Link Issues**: อ้างอิง Related Issues
   ```
   Closes #123
   Related to #456
   ```

3. **Tests**: ผ่านการทดสอบหรือไม่

4. **Documentation**: Update Docs ถ้า Needed

## Development Setup

### Environment

1. **Install WordPress**
   ```bash
   # Use Local, Vagrant, Docker, etc.
   ```

2. **Install WooCommerce**
   - From WordPress Admin

3. **Activate Plugin**
   - ตั้ง `WP_DEBUG` เป็น true

### Debug Mode

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

View Logs:
```bash
tail -f /path/to/wp-content/debug.log
```

### Database

Useful Commands:

```sql
-- ดู Recent Logs
SELECT * FROM wp_ssw_logs ORDER BY id DESC LIMIT 10;

-- Clear Logs
DELETE FROM wp_ssw_logs;

-- Check Options
SELECT * FROM wp_options WHERE option_name LIKE 'ssw_%';
```

## File Structure

```
stock-sync-woocommerce/
├── stock-sync-woocommerce.php      ← Main entry
├── uninstall.php                    ← Cleanup
├── includes/
│   ├── class-api-client.php        ← API integration
│   ├── class-product-sync.php      ← Product logic
│   ├── class-cron-manager.php      ← Cron jobs
│   ├── class-admin.php             ← Admin panel
│   └── class-logger.php            ← Logging
├── assets/
│   ├── css/admin.css               ← Styles
│   └── js/admin.js                 ← Scripts
├── classes/                         ← Old structure
├── docs/
│   ├── README.md
│   ├── INSTALLATION.md
│   ├── API-SPECIFICATION.md
│   ├── TROUBLESHOOTING.md
│   └── CHANGELOG.md
└── .gitignore
```

## Naming Conventions

### Functions/Methods
```php
// Private methods: prefix with underscore
private function _sync_product() {}

// Public methods: no prefix
public function sync_all_products() {}
```

### Hooks
```php
// Actions: ssw_[noun]_[verb]
do_action('ssw_product_synced');
do_action('ssw_sync_started');

// Filters: ssw_[noun]
apply_filters('ssw_product_data', $data);
apply_filters('ssw_api_response', $response);
```

### Classes
```php
// Class names: SSW_[Noun]_[Noun]
class SSW_API_Client {}
class SSW_Product_Sync {}
class SSW_Cron_Manager {}
```

### Constants
```php
// Constants: UPPERCASE_WITH_UNDERSCORES
define('SSW_VERSION', '1.0.0');
define('SSW_PLUGIN_DIR', plugin_dir_path(__FILE__));
```

## Documentation

### Code Comments

```php
/**
 * Short description.
 *
 * Longer description explaining what, why, and how.
 *
 * @since 1.0.0
 * @param string $sku Product SKU
 * @param int $quantity Stock quantity
 * @return bool|WP_Error True on success, error on failure
 */
public function update_product_stock($sku, $quantity) {
    // Code here
}
```

### Inline Comments

```php
// ใช้ Inline comments สำหรับ Complex Logic เท่านั้น
if ($stock > 0) {
    // Set product status to in-stock และ update order dates
    $product->set_stock_status('instock');
}
```

## Version Numbers

ใช้ Semantic Versioning: `MAJOR.MINOR.PATCH`

- `1.0.0` - First release
- `1.1.0` - New features (minor)
- `1.1.1` - Bug fix (patch)
- `2.0.0` - Breaking changes (major)

## Review Process

1. **Initial Review**
   - Code quality
   - Following standards
   - Tests passing

2. **Functional Review**
   - Does it work?
   - Edge cases covered?
   - Performance OK?

3. **Security Review** (ดู [SECURITY.md](SECURITY.md) สำหรับ checklist เต็ม)
   - Input/output properly handled
   - No SQL injection risks
   - Proper capabilities
   - ABSPATH guard ครบทุกไฟล์
   - Nonce verification ครบทุก form/AJAX

4. **Code Style** — รัน PHPCS ก่อน submit:
   ```bash
   composer require --dev wp-coding-standards/wpcs
   vendor/bin/phpcs --standard=phpcs.xml.dist
   ```

5. **Merge**
   - After approval
   - Squash commits (optional)

## Questions?

- ดู Existing Code
- ดู Issue Tracker
- ติดต่อ Maintainers

---

ขอบคุณที่มีส่วนร่วม! 🎉
