# Stock Sync WooCommerce Plugin

## รายละเอียด
ปลักอิน Stock Sync สำหรับ WooCommerce ช่วยให้คุณสามารถทำการ Sync ข้อมูลสินค้าจาก Stock API ต้นทางของคุณมายัง WooCommerce แบบ Pull Method (WooCommerce ไปดึงข้อมูลเอง) ผ่านระบบ Cron Job

## ฟีเจอร์หลัก

### 1. Full Product Sync
- ดึงข้อมูลสินค้าทั้งหมดจาก API
- สร้างสินค้าใหม่หรืออัปเดตสินค้าที่มีอยู่แล้ว
- สนับสนุน Simple Products และ Variable Products
- ทำงานทุก 6 ชั่วโมง

### 2. Stock Update Sync
- อัปเดตเฉพาะปริมาณสต็อกเท่านั้น (เร็วกว่า)
- ทำงานทุก 15 นาที
- เหมาะสำหรับการจัดการสต็อกแบบเรียลไทม์

### 3. Batch Processing
- ประมวลผลสินค้าจำนวนมากแบบเป็นรุ่น
- ไม่มี Timeout
- ลดภาระบน Server

### 4. Automatic Logging
- บันทึก Log ทุกครั้งที่ Sync
- เก็บใน Database และไฟล์
- ลบ Log เก่ากว่า 30 วันอัตโนมัติ

### 5. Admin Dashboard
- ตั้งค่า API Credentials
- ทดสอบการเชื่อมต่อ API
- รัน Manual Sync
- ดู Status และ Recent Logs

## หน้าที่ของไฟล์

```
stock-sync-woocommerce/
├── stock-sync-woocommerce.php      ← Main Plugin File (จุดเข้าต้นหลัก)
├── uninstall.php                    ← ล้างข้อมูลเมื่อลบปลักอิน
├── includes/
│   ├── class-api-client.php         ← ติดต่อ Stock API (ดึงข้อมูล)
│   ├── class-product-sync.php       ← จัดการการ Sync สินค้า
│   ├── class-cron-manager.php       ← จัดการ Cron Jobs
│   ├── class-logger.php             ← บันทึก Log
│   └── class-admin.php              ← Admin Dashboard
├── assets/
│   ├── css/                         ← Stylesheet
│   └── js/                          ← JavaScript
└── README.md                        ← ไฟล์นี้
```

## การติดตั้ง

1. ถ้อให้ปลักอินไปยัง `/wp-content/plugins/stock-sync-woocommerce/`
2. เปิดใช้งานปลักอิน
3. ไปที่ WooCommerce → Stock Sync → ตั้งค่า settings

## การตั้งค่า

### ขั้นตอนที่ 1: ตั้งค่า API Connection
1. ไปที่ **WooCommerce > Stock Sync** ใน WordPress Admin
2. กรอกข้อมูลต่อไปนี้:
   - **API Base URL**: `https://api.yourstock.com/v1/`
   - **API Key**: คีย์ API ของคุณ
   - **API Secret**: Secret Key ของคุณ

### ขั้นตอนที่ 2: ทดสอบการเชื่อมต่อ
1. คลิกปุ่ม "Test API Connection"
2. ถ้าสำเร็จ ข้อความ "Connection successful" จะปรากฏขึ้น

### ขั้นตอนที่ 3: เรียกใช้ Sync แรก
1. คลิกปุ่ม "Run Full Sync Now"
2. จอรอให้เสร็จสิ้น
3. ตรวจสอบสถานะใน "Sync Status"

## Cron Schedules

ปลักอินนี้ใช้ Cron Jobs ของ WordPress โดยอัตโนมัติ:

| Task | Interval | Purpose |
|------|----------|---------|
| Full Sync | ทุก 6 ชั่วโมง | ดึงข้อมูลและสร้าง/อัปเดตสินค้า |
| Stock Sync | ทุก 15 นาที | อัปเดตเฉพาะสต็อก |
| Cleanup | ทุกวัน | ลบ Log เก่า |

## คาดว่า API Response Format

API ของคุณควรคืนข้อมูลในรูปแบบต่อไปนี้:

### Get Products
```json
{
    "data": [
        {
            "id": "12345",
            "sku": "PROD001",
            "name": "Product Name",
            "description": "Product Description",
            "short_description": "Short desc",
            "regular_price": "100.00",
            "sale_price": "80.00",
            "stock_quantity": 50,
            "categories": ["Category 1", "Category 2"],
            "images": ["https://example.com/image1.jpg"],
            "weight": "1.5",
            "attributes": {
                "color": ["Red", "Blue"],
                "size": ["S", "M", "L"]
            }
        }
    ],
    "has_more": false
}
```

### Get Stock Updates
```json
[
    {
        "sku": "PROD001",
        "quantity": 45
    },
    {
        "sku": "PROD002",
        "quantity": 120
    }
]
```

## Troubleshooting

### ปัญหา: Connection Failed
- ตรวจสอบ API URL ว่าถูกต้อง
- ตรวจสอบ API Key และ Secret
- ตรวจสอบว่า Server อนุญาต outbound requests

### ปัญหา: Cron Jobs ไม่ทำงาน
- ตรวจสอบว่า WordPress Cron enabled
- ทำให้ `DISABLE_WP_CRON` เป็น `false` ใน wp-config.php
- หรือตั้งค่า Cron ของ Server เอง:
  ```bash
  */5 * * * * curl https://yoursite.com/wp-admin/admin-ajax.php?action=wp-cron
  ```

### ปัญหา: Sync ช้า
- ลดจำนวนสินค้าต่อครั้งในการทำ Batch Processing
- ตรวจสอบความเร็วของ Internet Connection
- ทำให้ Memory Limit สูงขึ้นใน wp-config.php

## Logs

Log จะถูกบันทึกในสองสถานที่:

1. **Database**: ตาราง `wp_ssw_logs` (เก็บ 30 วัน)
2. **File**: `/wp-content/uploads/ssw-logs/ssw-debug-{hash}.log` (protected by .htaccess)

ดูล่าสุด 20 log ได้จากหน้า Settings

## Function Hooks

### Actions
```php
// Manual Full Sync
do_action('ssw_full_sync_cron');

// Manual Stock Sync
do_action('ssw_stock_sync_cron');

// Process single product via queue
do_action('ssw_process_product_sync', $product_data);

// Batch Sync
do_action('ssw_batch_sync', $batch_number);
```

### Filters
```php
// Customize headers ก่อนส่ง API Request
apply_filters('ssw_api_headers', $headers);

// Customize Product Data ก่อน Save
apply_filters('ssw_product_data', $product_data);
```

## Advanced Usage

### Manual Queue Products
```php
$sync = new SSW_Product_Sync();
$sync->queue_product_sync($product_data);
```

### Manual Batch Sync
```php
$sync = new SSW_Product_Sync();
$sync->sync_batch(0); // batch number 0
```

### Get Logger Instance
```php
$logger = SSW_Logger::getInstance();
$logger->info('Some message', array('key' => 'value'));
$logger->error('Error message');
$logger->success('Success message');
```

## ข้อจำกัด

- ต้องติดตั้ง WooCommerce
- API ต้องเชื่อมต่อได้อย่างสม่ำเสมอ
- Server ต้อง Support การดาวน์โหลดรูปภาพจากอินเทอร์เน็ต

## สนับสนุนและข้อมูลเพิ่มเติม

สำหรับความช่วยเหลือและข้อมูลเพิ่มเติม โปรดติดต่อ: https://iwebxstudio.com/

## Security

ดูนโยบายความปลอดภัยและวิธีรายงานช่องโหว่ได้ที่ [SECURITY.md](SECURITY.md)

## License

This plugin is provided as-is for use with WooCommerce.
