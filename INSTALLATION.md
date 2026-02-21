# คู่มือการติดตั้ง Stock Sync WooCommerce Plugin

## ข้อกำหนดเบื้องต้น

- WordPress 5.0 หรือสูงกว่า
- WooCommerce 4.0 หรือสูงกว่า
- PHP 7.4 หรือสูงกว่า
- cURL ต้องเปิดใช้งาน (extention)
- การเชื่อมต่ออินเทอร์เน็ต

## ข้อมูล API ที่ต้องการ

ก่อนที่คุณติดตั้งปลักอิน ให้เตรียมข้อมูลต่อไปนี้จากผู้ให้บริการ Stock API:

- **API Base URL** - ที่อยู่หลัก เช่น `https://api.yourstock.com/v1/`
- **API Key** - คีย์สำหรับตรวจสอบสิทธิ
- **API Secret** - Secret Key สำหรับการยืนยัน

## ขั้นตอนการติดตั้ง

### 1. อัปโหลดไฟล์ปลักอิน

**วิธีที่ 1: ผ่าน FTP/SFTP**
- ดาวน์โหลดโฟลเดอร์ `stock-sync-woocommerce` ทั้งหมด
- อัปโหลดไปยัง `/wp-content/plugins/` บนเซิร์ฟเวอร์ของคุณ

**วิธีที่ 2: ผ่าน WordPress Admin**
- เข้าไปที่ WordPress Admin Dashboard
- ไปที่ **Plugins > Add New**
- คลิก **Upload Plugin**
- เลือก ไฟล์ ZIP ของปลักอิน
- คลิก **Install Now**

### 2. เปิดใช้งานปลักอิน

1. หลังจากอัปโหลด ไปที่ **Plugins** page
2. มองหา "stock sync woocommerce"
3. คลิก **Activate**

### 3. ตรวจสอบว่าปลักอินถูกติดตั้งอย่างถูกต้อง

1. ไปที่ **WooCommerce** ในเมนูด้านข้าง
2. คุณควรเห็น **"Stock Sync"** option ปรากฏขึ้น
3. คลิก **Stock Sync** เพื่อเข้ากำหนดการตั้งค่า

## การตั้งค่าเบื้องต้น

### ขั้นตอนที่ 1: เข้าไปที่หน้าการตั้งค่า

1. ไปที่ **WooCommerce > Stock Sync**
2. คุณจะเห็นหน้าตั้งค่า

### ขั้นตอนที่ 2: กรอกข้อมูล API

ในส่วน **API Configuration** กรอก:

- **API Base URL**: `https://api.yourstock.com/v1/`
  - ตรวจสอบให้แน่ใจ domain ถูกต้อง
  - ตรวจสอบให้แน่ใจว่า URL ลงท้ายด้วย `/` หรือ `/v1/`

- **API Key**: ใส่ API Key ที่คุณได้รับ

- **API Secret**: ใส่ API Secret/Password

### ขั้นตอนที่ 3: บันทึกการตั้งค่า

คลิก **Save Changes** ทีนอกด้านล่างของไฟล์แบบฟอร์ม

### ขั้นตอนที่ 4: ทดสอบการเชื่อมต่อ

1. หลังจากบันทึก ไปที่ส่วน **Actions**
2. คลิก **Test API Connection**
3. ถ้าสำเร็จ ข้อความสีเขียว "Connection successful" จะปรากฏขึ้น

#### แก้ไข: ถ้าการเชื่อมต่อล้มเหลว

ให้ตรวจสอบ:
- ✓ URL ถูกต้อง
- ✓ API Key และ Secret ถูกต้อง  
- ✓ Server อนุญาต outbound connections
- ✓ API Server กำลังทำงาน

## การทำงานครั้งแรก

### Manual Sync ครั้งแรก

1. ไปที่ **WooCommerce > Stock Sync**
2. ไปที่ส่วน **Actions**
3. คลิก **Run Full Sync Now**
4. จอรอให้การทำงานเสร็จสิ้น (อาจใช้เวลาสักครู่ขึ้นอยู่กับจำนวนสินค้า)

### ตรวจสอบผลลัพธ์

1. ดู **Sync Status** section เพื่อดูสถานะ
2. ตรวจสอบ **Products** ใน WooCommerce เพื่อตรวจสอบว่าสินค้าถูกสร้างเรียบร้อย
3. ดู **Recent Logs** เพื่อตรวจสอบรายละเอียด

## Cron Jobs (การทำงานอัตโนมัติ)

เมื่อปลักอินเปิดใช้งาน ระบบจะตั้ง Cron Jobs เหล่านี้โดยอัตโนมัติ:

| Task | เวลา | ระหว่าง |
|------|------|----------|
| Full Product Sync | ทุก 6 ชั่วโมง | ดึงข้อมูลและสร้าง/อัปเดตสินค้า |
| Stock Update Sync | ทุก 15 นาที | อัปเดตปริมาณสต็อก |
| Log Cleanup | ทุกวัน | ลบ Log เก่า |

### ตรวจสอบ Cron Jobs

WordPress ใช้ Pseudo-Cron โดยค่าเริ่มต้น ซึ่งจะทำงานเมื่อมีผู้เข้าชม

ถ้าคุณต้องการให้ Cron ทำงานแม่นยำมากขึ้น สามารถการตั้งค่า System Cron:

1. เพิ่มบรรทัดนี้ใน `wp-config.php`:
   ```php
   define('DISABLE_WP_CRON', true);
   ```

2. จากนั้น ตั้ง System Cron (ผ่าน cPanel หรือ Terminal):
   ```bash
   */5 * * * * curl https://yoursite.com/wp-admin/admin-ajax.php?action=wp_cron
   ```

## การป้องกัน และ Security

### ล้างข้อมูล API

ปลักอินเก็บ API Credentials ใน WordPress Options

⚠️ **ระวัง**: อย่าแบ่งปันข้อมูลนี้กับใคร!

### Backup ก่อนการ Sync แรกครั้ง

1. ทำการ Backup ฐานข้อมูล WordPress
2. ตั้ง Test environment หากเป็นไปได้
3. เรียกใช้การ Sync ที่จำกัด (เช่น 10 สินค้า) ก่อน

## การแก้ไขปัญหา

### ปัญหา: "WooCommerce is required!"

**สาเหตุ**: WooCommerce ไม่ได้ติดตั้ง หรือไม่ได้เปิดใช้งาน

**วิธีแก้**:
1. ติดตั้ง WooCommerce จาก Plugin Marketplace
2. เปิดใช้งาน WooCommerce

### ปัญหา: "Connection failed"

**สาเหตุ**: API Settings ไม่ถูกต้อง หรือ Network ไม่ได้รับอนุญาต

**วิธีแก้**:
1. ตรวจสอบ API URL, Key, Secret อีกครั้ง
2. ตรวจสอบว่า firewall อนุญาต outbound requests
3. ลองติดต่อ ISP หรือ Hosting Provider เพื่อตรวจสอบ firewall

### ปัญหา: Sync ไม่ทำงาน

**สาเหตุ**: Cron Jobs ไม่ทำงาน หรือ Setup ไม่ถูกต้อง

**วิธีแก้**:
1. ตรวจสอบใน WordPress Debug Log (`/wp-content/debug.log`)
2. ตรวจสอบ Sync Status เพื่อดูเวลา sync ล่าสุด
3. รัน Manual Sync เพื่อทดสอบ
4. ตั้ง System Cron (ดูข้างบน)

### ปัญหา: Memory Exceeded

**สาเหตุ**: มีสินค้าจำนวนมากเกินไป

**วิธีแก้**:
1. เพิ่ม PHP Memory Limit ใน `wp-config.php`:
   ```php
   define('WP_MEMORY_LIMIT', '256M');
   ```
2. ใช้ Batch Processing (ปลักอินใช้แล้ว)
3. ลดจำนวนสินค้าต่อครั้ง (ถ้า API อนุญาต)

## ไฟล์ละเอียดบันทึก

### Database Table
- `wp_ssw_logs` - บันทึก Sync

### Options ใน wp_options
- `ssw_api_base_url` - URL ของ API
- `ssw_api_key` - API Key
- `ssw_api_secret` - API Secret
- `ssw_last_sync_time` - เวลา Sync ครั้งล่าสุด
- `ssw_last_sync_count` - จำนวนสินค้าที่ sync ครั้งล่าสุด

### Meta ของ Product
- `_ssw_external_id` - ID จากระบบ Stock ต้นทาง
- `_ssw_last_sync` - เวลา sync ล่าสุดของสินค้านี้

## ความช่วยเหลือเพิ่มเติม

ถ้ามีปัญหา โปรดดู:
- [README.md](./README.md) - คู่มือทั่วไป
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WooCommerce Documentation](https://docs.woocommerce.com/)

หรือติดต่อ: https://iwebxstudio.com/
