# Stock Sync Troubleshooting Guide

คู่มือแก้ไขปัญหาทั่วไป

## Quick Checklist

ก่อนทำการแก้ไข ให้ตรวจสอบเรื่องต่อไปนี้:

- [ ] WooCommerce เปิดใช้งานอยู่
- [ ] Stock Sync Plugin เปิดใช้งานอยู่
- [ ] API Credentials ถูกต้อง
- [ ] Server สามารถเข้าถึง API endpoint
- [ ] PHP Version >= 7.4
- [ ] Memory Limit >= 128MB
- [ ] cURL Extension เปิดใช้งาน

## Common Issues & Solutions

### Issue 1: "WooCommerce is Required" Message

#### ปัญหา
- ปลักอินแสดงข้อความ "WooCommerce is required for Stock Sync plugin to work!"

#### สาเหตุ
- WooCommerce ไม่ได้ติดตั้ง
- WooCommerce ไม่ได้เปิดใช้งาน
- WooCommerce ถูกลบออก

#### วิธีแก้

**ตัวเลือก 1: ติดตั้ง WooCommerce**
1. เข้า WordPress Admin
2. ไปที่ **Plugins > Add New**
3. ค้นหา "WooCommerce"
4. คลิก **Install Now** → **Activate**

**ตัวเลือก 2: เปิดใช้งาน WooCommerce**
1. เข้า **Plugins**
2. มองหา "WooCommerce"
3. คลิก **Activate**

---

### Issue 2: API Connection Failed

#### ปัญหา
- "Connection failed. Please check your API settings."
- ไม่สามารถเชื่อมต่อไปยัง Stock API

#### สาเหตุ
- API URL ไม่ถูกต้อง
- API Key/Secret ไม่ถูกต้อง
- Firewall บล็อก outbound connections
- API Server offline

#### วิธีแก้

**ขั้นตอนที่ 1: ตรวจสอบ Settings**
1. ไปที่ **WooCommerce > Stock Sync**
2. ตรวจสอบ API Base URL:
   - ถูกต้องหรือไม่?
   - มี `https://` หรือไม่?
   - ลงท้ายด้วย `/` หรือไม่?
   
   ✓ ถูกต้อง: `https://api.yourstock.com/v1/`
   ✗ ผิด: `http://api.yourstock.com` (ขาดเสลัลจากท้าย)

3. ตรวจสอบ API Key และ Secret:
   - ไม่มีช่องว่างพิเศษหรือไม่?
   - มีตัวอักษรถูกต้องหรือไม่?

**ขั้นตอนที่ 2: ทดสอบด้วย cURL**

เพิ่มบรรทัดนี้ในไฟล์ test-api.php ในโฟลเดอร์ plugin:

```php
<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.yourstock.com/v1/products?limit=1");
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer YOUR_API_KEY',
    'Content-Type: application/json'
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: " . $http_code . "\n";
echo "Response: " . $response . "\n";
echo "Error: " . $error . "\n";
?>
```

เข้าไป execute ไฟล์นี้และบันทึกผลลัพธ์

**ขั้นตอนที่ 3: ตรวจสอบ Firewall**

ถามผู้ให้บริการ Hosting ของคุณ:
- "เซิร์ฟเวอร์ของฉันสามารถทำ Outbound Requests ไปยัง External Servers ได้หรือไม่?"
- "มี IP Whitelist requirement หรือไม่?"
- "มี Firewall rule บล็อก certain ports หรือไม่?"

**ขั้นตอนที่ 4: ตรวจสอบ API Server**

- ลอง access API URL จากเบราว์เซอร์:
  ```
  https://api.yourstock.com/v1/products?limit=1
  ```
  
- ถ้าได้ JSON response = API ใช้ได้
- ถ้า Connection timeout = API Server offline

---

### Issue 3: Sync Not Running / Cron Jobs Not Working

#### ปัญหา
- "Last Full Sync: Never"
- Cron Jobs ไม่เกิดขึ้น
- Manual Sync ทำงาน แต่ Auto Sync ไม่ทำงาน

#### สาเหตุ
- WordPress Pseudo-Cron ต้องการ Site Traffic
- Cron Disabled
- Server ไม่สนับสนุน Cron

#### วิธีแก้

**ตัวเลือก 1: ใช้ WordPress Pseudo-Cron (ค่าเริ่มต้น)**

1. ให้ Site มี Traffic ปกติ
2. ทุกครั้งที่มี Visitor ระบบจะ Check ว่ามี Cron ต้องทำหรือไม่
3. ถ้าไม่มี Traffic = Cron ไม่ทำงาน

**ตัวเลือก 2: ตั้ง System Cron (แนะนำ)**

1. เปิด `wp-config.php` ด้วย FTP/SFTP
2. เพิ่มบรรทัดนี้ (**ก่อน** comment `/* That's all, stop editing! */`):
   ```php
   define('DISABLE_WP_CRON', true);
   ```
   บันทึก

3. ติดต่อ Hosting Provider เพื่อตั้ง Cron Job:
   ```bash
   */5 * * * * curl https://yoursite.com/wp-admin/admin-ajax.php?action=wp_cron
   ```
   
   หรือใช้ Wget:
   ```bash
   */5 * * * * wget -q -O - https://yoursite.com/wp-admin/admin-ajax.php?action=wp_cron
   ```

4. ปกติ 5-10 นาที ก็ Browser-based Cron จะทำงาน

**ตัวเลือก 3: ตรวจสอบใน cPanel (ถ้ามี)**

1. เข้า cPanel
2. ไปที่ **Cron Jobs**
3. ตรวจสอบ Cron Jobs ที่มิใช่ WordPress
4. เพิ่ม Cron Job ใหม่ (frequency: Every 5 minutes)

---

### Issue 4: Products Not Syncing / Sync Errors

#### ปัญหา
- "Sync completed: 0 products"
- "Recent Errors" มีค่าสูง
- Products ไม่ถูกสร้าง

#### สาเหตุ
- API Response Format ผิด
- Required Fields หายไป
- SKU ซ้ำกัน
- Permissions ไม่ถูกต้อง

#### วิธีแก้

**ขั้นตอนที่ 1: ตรวจสอบ API Response**

1. รัน: Manual Sync
2. ดูใน Admin: **WooCommerce > Stock Sync > Recent Logs**
3. ค้นหา Errors

**ขั้นตอนที่ 2: ตรวจสอบ API Data Format**

API ต้องคืน:
```json
{
    "data": [ ... ],      // ต้องมี "data" key
    "has_more": false
}
```

ไม่ควรคืน:
```json
[ ... ]  // แค่ Array โดยตรง
```

**ขั้นตอนที่ 3: ตรวจสอบ Required Fields**

API ต้องมี fields เหล่านี้สำหรับแต่ละ Product:
- `id` - Unique ID
- `sku` - Must be unique and not empty
- `name` - Product name
- `regular_price` - ต้องเป็นตัวเลข

**ขั้นตอยที่ 4: ตรวจสอบ SKU Duplicates**

ถ้า API มี SKU ซ้ำ Plugin จะอัปเดตครั้งแรกที่เจอเท่านั้น

1. ตรวจสอบ API เพื่อให้แน่ใจ SKU เป็น Unique
2. ลองลบ Product ที่มี SKU เดียวกัน แล้ว Sync ใหม่

**ขั้นตอนที่ 5: ตรวจสอบ Permissions**

User สำหรับ Cron ต้องมี Permissions:
- Create Products
- Edit Products
- Manage WooCommerce

โดยปกติ WordPress Cron ใช้ System User ซึ่งมี Full Permissions

---

### Issue 5: "Out of Memory" Error

#### ปัญหา
```
PHP Fatal error:  Allowed memory exhausted
```

#### สาเหตุ
- PHP Memory Limit ต่ำเกินไป
- มีสินค้าจำนวนมาก

#### วิธีแก้

**ตัวเลือก 1: เพิ่ม Memory Limit**

1. เปิด `wp-config.php`
2. เพิ่มบรรทัด:
   ```php
   define('WP_MEMORY_LIMIT', '256M');
   ```
   (ขึ้นหน่วย 128M → 256M → 512M ตามความต้องการ)

3. บันทึก

**ตัวเลือก 2: ลดจำนวนสินค้าต่อครั้ง**

Plugin ใช้ Batch Processing แล้ว แต่ถ้ายังใหญ่เกิน:

1. ติดต่อ API Provider เพื่อขอ Pagination Options
2. ขอให้ API คืน 50 items แต่ละครั้ง (แล้วใช้ pagination)

**ตัวเลือก 3: ขึ้นเซิร์ฟเวอร์ที่ส่วนนิ่มกว่า**

ติดต่อ Hosting Provider เพื่อขอเซิร์ฟเวอร์ที่มี:
- RAM มากกว่า
- PHP Memory Limit สูงกว่า
- CPU โหลด ต่ำer

---

### Issue 6: Images Not Downloading

#### ปัญหา
- Products ถูกสร้าง แต่ ไม่มีรูปภาพ
- Featured Image หายไป

#### สาเหตุ
- Image URL ไม่ถูกต้อง
- Server ไม่สามารถ Download Images
- Firewall บล็อก Image Downloads

#### วิธีแก้

**ขั้นตอนที่ 1: ตรวจสอบ Image URLs**

1. ตรวจสอบ API Response ว่ามี `images` array หรือไม่
2. URLs ควรเป็น:
   ✓ `https://example.com/product.jpg`
   ✗ `http://...` (ขาด HTTPS)
   ✗ `/product.jpg` (Relative Path)

**ขั้นตอนที่ 2: ทดสอบ Download**

ไปที่ WordPress Admin:
1. **Media > Add New**
2. คลิก **Upload Files**
3. Paste Image URL
4. ถ้า Download ได้ = ปัญหาที่อื่น

**ขั้นตอนที่ 3: ตรวจสอบ wp-content/uploads Permissions**

File Permissions ต้องเป็น:
- Owner: www-data (หรือ Apache user)
- Permissions: 755-775

ถ้าไม่ถูก ติดต่อ Hosting Provider

**ขั้นตอนที่ 4: ปิด HTTPS Verification (Temporary)**

ถ้ายังไม่ได้ ลองแก้ไข `class-product-sync.php`:

```php
// ค้นหาบรรทัดที่ download_url ใช้
// ห้องแก้ opt_before_download เป็น true (Warning: แบบนี้ไม่ปลอดภัย)
```

สำหรับ Production ให้ติดต่อ API Provider เพื่อใช้ HTTPS certificate ที่ valid

---

### Issue 7: Categories Not Created

#### ปัญหา
- Products ถูกสร้าง แต่ ไม่มี Categories
- Category สร้างใหม่ แต่ไม่ Assign

#### สาเหตุ
- API ไม่มี `categories` field
- Category Names เป็น Array ว่าง
- Permissions ไม่ถูกต้อง

#### วิธีแก้

**ขั้นตอนที่ 1: ตรวจสอบ API Response**

API ต้องคืน categories:
```json
{
    "categories": ["Electronics", "Phones"]
}
```

ไม่ควรคืน:
```json
{
    "category_id": [1, 2, 3]  // ต้องใช้ names แทน
}
```

**ขั้นตอนที่ 2: ตรวจสอบ Permissions**

User ที่รัน Cron ต้องมี:
- `manage_product_terms` capability
- `edit_products` capability

---

## Debug Mode

### Enable WordPress Debug Logging

1. เปิด `wp-config.php`
2. เพิ่ม:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

3. บันทึก

4. Logs จะเก็บใน `/wp-content/debug.log`

5. เช็ค Debug Log:
   ```bash
   tail -f /wp-content/debug.log
   ```

### Check Plugin Logs

1. ไปที่ **WooCommerce > Stock Sync**
2. ดู **Recent Logs** section
3. ค้นหา Errors

### Check Database

```sql
-- ดู Recent Logs
SELECT * FROM wp_ssw_logs ORDER BY id DESC LIMIT 50;

-- ดู Last Sync Time
SELECT option_name, option_value FROM wp_options WHERE option_name LIKE 'ssw_%';
```

---

## Performance Issues

### Sync ช้า

#### ปัญหา
- Sync ใช้เวลา > 10 นาที
- Timeout errors

#### วิธีให้ย่อ:

1. **เพิ่ม PHP Execution Time**
   ```php
   // wp-config.php
   define('WP_MEMORY_LIMIT', '256M');
   set_time_limit(300); // 5 นาที
   ```

2. **ลดจำนวนสินค้า**
   - ขอให้ API ให้ 50 items ต่อหน้า (default)
   - ให้ Plugin Process แบบ Batch

3. **เพิ่ม Server Resources**
   - Upgrade เป็น Better Hosting Plan
   - เพิ่ม RAM
   - เพิ่ม CPU

---

## Getting Help

ถ้าแก้ไขไม่ได้ เก็บข้อมูลต่อไปนี้:

1. **ข้อความ Error ที่เต็ม**
2. **WordPress Version**
3. **WooCommerce Version**
4. **PHP Version**
5. **Logs จาก:** `/wp-content/debug.log`
6. **Logs จาก:** WooCommerce > Stock Sync > Recent Logs
7. **API Response Sample** (ลบ API Key ก่อน)

ติดต่อ Support: https://iwebxstudio.com/

---

## Preventive Maintenance

### ทำการ Backup Regularly
```bash
# Database backup
mysqldump -u user -p 'password' wordpress > backup.sql

# Files backup
tar -czf wordpress-backup.tar.gz /var/www/html/
```

### ตรวจสอบ Cron Jobs
```bash
# ดู Cron Logs
grep CRON /var/log/syslog

# ตรวจสอบ Cron History
grep "Stock Sync" /var/log/apache2/access.log
```

### Monitoring

ติดตั้ง Tools เพื่อ Monitor:
- PHP Uptime Monitoring
- API Availability Checker
- Cron Job Monitoring

---

## Additional Resources

- [WordPress Documentation](https://wordpress.org/support/)
- [WooCommerce Help](https://docs.woocommerce.com/documentation/)
- [PHP Manual](https://www.php.net/manual/)
