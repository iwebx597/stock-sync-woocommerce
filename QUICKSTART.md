# Quick Start Guide

สัตรนี้จะช่วยให้คุณเริ่มใช้ Stock Sync Plugin ได้อย่างรวดเร็ว

## 5 นาทีเพื่อให้ Plugin ทำงาน

### ขั้นตอนที่ 1: ติดตั้ง Plugin (1 min)

1. **อัปโหลดไฟล์**
   - จากนั้นให้ WordPress > Plugins
   - คลิก "Activate" สำหรับ Stock Sync plugin

2. **ตรวจสอบการติดตั้ง**
   - ไปที่ WooCommerce menu
   - คุณควรเห็น "Stock Sync" ปรากฏขึ้น

### ขั้นตอนที่ 2: ได้ API Credentials (1 min)

จากผู้ให้บริการ Stock API ของคุณ ให้เก็บ:

```
API Base URL: https://api.yourcompany.com/v1/
API Key: abc123xyz789
API Secret: secret_key_here
```

### ขั้นตอนที่ 3: ตั้งค่า Settings (1 min)

1. เข้าไป **WooCommerce > Stock Sync**
2. กรอกข้อมูล:
   - **API Base URL**: `https://api.yourcompany.com/v1/`
   - **API Key**: `abc123xyz789`
   - **API Secret**: `secret_key_here`
3. คลิก **Save Changes**

### ขั้นตอนที่ 4: ทดสอบ Connection (1 min)

1. คลิก **Test API Connection**
2. รอข้อความ "Connection successful"
3. ถ้าเกิด Error ดู [Troubleshooting](./TROUBLESHOOTING.md)

### ขั้นตอนที่ 5: รัน Sync ครั้งแรก (1 min)

1. คลิก **Run Full Sync Now**
2. รอให้เสร็จสิ้น (ขึ้นอยู่กับจำนวนสินค้า)
3. ดู Products จาก WooCommerce > Products

## เสร็จแล้ว! ✅

Plugin จะทำงานอัตโนมัติตามนำ:

| Task | Interval |
|------|----------|
| Full Sync | ทุก 6 ชั่วโมง |
| Stock Update | ทุก 15 นาที |

## ตรวจสอบสถานะ

ไปที่ **WooCommerce > Stock Sync** เพื่อดู:
- ✓ Last Sync Time
- ✓ Products Synced Count
- ✓ Errors (if any)
- ✓ Recent Logs

## ลิงก์ที่มีประโยชน์

| Topic | Link |
|-------|------|
| Full Documentation | [README.md](./README.md) |
| API Spec | [API-SPECIFICATION.md](./API-SPECIFICATION.md) |
| Installation | [INSTALLATION.md](./INSTALLATION.md) |
| Troubleshooting | [TROUBLESHOOTING.md](./TROUBLESHOOTING.md) |

## Common Errors

### "Connection failed"
→ ตรวจสอบ API URL, Key, Secret ว่า ถูกต้องหรือไม่

### "WooCommerce is required"
→ ติดตั้งและเปิดใช้ WooCommerce

### "Products not syncing"
→ ตรวจสอบ WordPress Cron Jobs (ดู [TROUBLESHOOTING.md](./TROUBLESHOOTING.md))

## Tips

💡 **Dry Run**: ทำ Manual Sync ทีหนึ่งเพื่อตรวจสอบ

💡 **Backup**: ทำ Backup ฐานข้อมูลก่อนทำ Full Sync ครั้งแรก

💡 **Debug**: ถ้ามีปัญหา ให้ Enable Debug Mode (ดู [TROUBLESHOOTING.md](./TROUBLESHOOTING.md))

## Support

ถ้าติดปัญหา:
1. ดู [TROUBLESHOOTING.md](./TROUBLESHOOTING.md)
2. ตรวจสอบ Recent Logs
3. ติดต่อ Support

---

🎉 ยินดีต้อนรับสู่ Stock Sync!
