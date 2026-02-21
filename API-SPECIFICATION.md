# Stock Sync API Specification

แอปพลิเคชันนี้ระบุรูปแบบของ API ที่ Stock Sync Plugin คาดว่าจะได้รับ

## Authentication

ปลักอินใช้ **Bearer Token Authentication**

```
Authorization: Bearer {API_KEY}
Content-Type: application/json
Accept: application/json
```

แนะนำให้ API ของคุณจำกัดการใช้งาน API Key ต่อ IP Address และ Rate Limiting

## Endpoints ที่ต้องการ

### 1. Get All Products

**Endpoint**: `GET /products`

**Query Parameters**:
```
page=1              // หน้า (เริ่มจาก 1)
limit=100           // จำนวนผลต่อหน้า
updated_since=2024-01-01 12:00:00 // (ตัวเลือก) ดึงเฉพาะ updated after this
```

**Response (200 OK)**:
```json
{
    "data": [
        {
            "id": "12345",
            "sku": "PROD001",
            "name": "Product Name",
            "description": "Long description of the product",
            "short_description": "Short description",
            "regular_price": "100.00",
            "sale_price": "80.00",
            "stock_quantity": 50,
            "categories": [
                "Category 1",
                "Category 2"
            ],
            "images": [
                "https://example.com/images/product1.jpg",
                "https://example.com/images/product2.jpg"
            ],
            "weight": "1.5",
            "attributes": {
                "color": ["Red", "Blue", "Green"],
                "size": ["S", "M", "L", "XL"]
            },
            "variations": {
                "attributes": {
                    "color": ["Red", "Blue"],
                    "size": ["S", "M", "L"]
                },
                "items": [
                    {
                        "sku": "PROD001-RED-S",
                        "price": "100.00",
                        "stock": 10,
                        "attributes": {
                            "color": "Red",
                            "size": "S"
                        }
                    },
                    {
                        "sku": "PROD001-RED-M",
                        "price": "100.00",
                        "stock": 15,
                        "attributes": {
                            "color": "Red",
                            "size": "M"
                        }
                    }
                ]
            }
        },
        {
            "id": "12346",
            "sku": "PROD002",
            "name": "Another Product",
            // ... more fields
        }
    ],
    "meta": {
        "total": 250,
        "page": 1,
        "limit": 100,
        "pages": 3
    },
    "has_more": true
}
```

**Error Response (400 Bad Request)**:
```json
{
    "success": false,
    "error": "Invalid page number",
    "code": "INVALID_PAGE"
}
```

### 2. Get Single Product

**Endpoint**: `GET /products/{sku}`

**Response (200 OK)**:
```json
{
    "id": "12345",
    "sku": "PROD001",
    "name": "Product Name",
    "description": "Full description",
    "short_description": "Short desc",
    "regular_price": "100.00",
    "sale_price": "80.00",
    "stock_quantity": 50,
    "categories": ["Electronics"],
    "images": ["https://example.com/image.jpg"],
    "weight": "1.5"
}
```

### 3. Get Stock Updates

**Endpoint**: `GET /stock/updates`

**Query Parameters**:
```
since=2024-01-01 12:00:00  // (ตัวเลือก) ดึงเฉพาะ updates after this time
```

**Response (200 OK)**:
```json
[
    {
        "sku": "PROD001",
        "quantity": 45,
        "updated_at": "2024-01-15 10:30:00"
    },
    {
        "sku": "PROD002",
        "quantity": 120,
        "updated_at": "2024-01-15 10:30:00"
    },
    {
        "sku": "PROD003",
        "quantity": 0,
        "updated_at": "2024-01-15 10:30:00"
    }
]
```

## Field Specifications

### Product Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| id | string/number | Yes | Unique identifier in your system |
| sku | string | Yes | Stock Keeping Unit (must be unique) |
| name | string | Yes | Product name (max 255 characters) |
| description | string | No | Long description (HTML allowed) |
| short_description | string | No | Brief description (max 255 characters) |
| regular_price | string/number | Yes | Price as decimal (e.g., "100.00") |
| sale_price | string/number | No | Sale price (leave empty if no sale) |
| stock_quantity | number | Yes | Available stock (integer >= 0) |
| categories | array | No | Array of category names or IDs |
| images | array | No | Array of image URLs (HTTPS recommended) |
| weight | string/number | No | Weight in KG |
| attributes | object | No | Key-value pairs of attributes |
| variations | object | No | See Variations section |

### Attributes Format

```json
{
    "color": ["Red", "Blue", "Green"],
    "size": ["S", "M", "L", "XL"],
    "brand": ["Nike", "Adidas"]
}
```

### Variations Format

ใช้สำหรับ Variable Products (สินค้าที่มีตัวเลือก)

```json
{
    "attributes": {
        "color": ["Red", "Blue"],
        "size": ["S", "M", "L"]
    },
    "items": [
        {
            "sku": "PROD001-RED-S",
            "price": "100.00",
            "stock": 10,
            "attributes": {
                "color": "Red",
                "size": "S"
            }
        },
        {
            "sku": "PROD001-BLUE-L",
            "price": "110.00",
            "stock": 5,
            "attributes": {
                "color": "Blue",
                "size": "L"
            }
        }
    ]
}
```

## Error Handling

API ควรคืน HTTP Status Codes ที่เหมาะสม:

| Code | Status | Description |
|------|--------|-------------|
| 200 | OK | Request successful |
| 400 | Bad Request | Invalid parameters |
| 401 | Unauthorized | Invalid API credentials |
| 403 | Forbidden | Access denied |
| 404 | Not Found | Resource not found |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Server Error | Internal server error |

### Error Response Format

```json
{
    "success": false,
    "error": "Invalid API key",
    "code": "UNAUTHORIZED",
    "timestamp": "2024-01-15T10:30:00Z"
}
```

## Rate Limiting

แนะนำให้ API มี Rate Limiting:

- Maximum: 100 requests per minute per API Key
- Limit should be per IP Address (recommended)
- Return `X-RateLimit-Remaining` header

## Pagination

สำหรับ Endpoints ที่ Return หลายรายการ:

**Query Parameters**:
- `page`: หน้าที่ต้องการ (default: 1)
- `limit`: จำนวนรายการต่อหน้า (default: 100, max: 1000)

**Response Meta**:
```json
{
    "meta": {
        "total": 1000,
        "page": 2,
        "limit": 100,
        "pages": 10
    },
    "has_more": true
}
```

## DateTime Format

ทุกวันที่และเวลาต้องใช้ ISO 8601 format:

```
YYYY-MM-DD HH:MM:SS
หรือ
2024-01-15T10:30:00Z
```

## CORS (Cross-Origin Requests)

ถ้า API ของคุณใช้ CORS ให้ set headers:

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS
Access-Control-Allow-Headers: Authorization, Content-Type, Accept
```

## Example Implementations

### Using cURL

```bash
# Get products
curl -X GET "https://api.yourstock.com/v1/products?page=1&limit=100" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json"

# Get stock updates
curl -X GET "https://api.yourstock.com/v1/stock/updates?since=2024-01-01%2010:00:00" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

### Using Python

```python
import requests
from datetime import datetime, timedelta

BASE_URL = "https://api.yourstock.com/v1"
API_KEY = "your_api_key"

headers = {
    "Authorization": f"Bearer {API_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json"
}

# Get all products
response = requests.get(
    f"{BASE_URL}/products",
    headers=headers,
    params={
        "page": 1,
        "limit": 100,
        "updated_since": datetime.now() - timedelta(days=1)
    }
)

products = response.json()
print(products)

# Get stock updates
response = requests.get(
    f"{BASE_URL}/stock/updates",
    headers=headers,
    params={"since": datetime.now() - timedelta(minutes=15)}
)

updates = response.json()
print(updates)
```

### Using Node.js

```javascript
const axios = require('axios');

const BASE_URL = 'https://api.yourstock.com/v1';
const API_KEY = 'your_api_key';

const headers = {
    'Authorization': `Bearer ${API_KEY}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json'
};

// Get products
axios.get(`${BASE_URL}/products`, {
    headers: headers,
    params: {
        page: 1,
        limit: 100
    }
})
.then(response => console.log(response.data))
.catch(error => console.error(error));
```

## Testing Your API

ทำการทดสอบ API ของคุณก่อนติดตั้ง Plugin:

```bash
# 1. ทดสอบ Authentication
curl -X GET "https://api.yourstock.com/v1/products?limit=1" \
  -H "Authorization: Bearer YOUR_API_KEY"

# 2. ตรวจสอบ Response Format
# - Response code ควรเป็น 200
# - JSON ต้องได้ parse ได้
# - ต้องมี "data" field เป็น array

# 3. ทดสอบ Pagination
curl -X GET "https://api.yourstock.com/v1/products?page=1&limit=10"
curl -X GET "https://api.yourstock.com/v1/products?page=2&limit=10"
# ตรวจสอบว่า data ต่างกัน

# 4. ทดสอบ Stock Updates
curl -X GET "https://api.yourstock.com/v1/stock/updates"
```

## Performance Tips

1. **Indexing**: ทำ Index ที่ Updated_at, SKU field สำหรับ Query ได้เร็ว
2. **Caching**: Cache ผลลัพธ์ถ้า Query เดิมถูกถาม บ่อย
3. **Optimize Queries**: ลดจำนวน Database Query ถ้าเป็นไปได้
4. **Compression**: Enable GZIP Compression ใน Response
5. **CDN**: ใช้ CDN สำหรับ Images
6. **Batch Operations**: อนุญาตให้ Batch ได้ หรือ Pagination ที่ดี

## Security Considerations

1. **HTTPS Only**: API ต้อง HTTPS เท่านั้น
2. **API Key Rotation**: ให้ Admin สามารถ Rotate API Keys ได้
3. **Rate Limiting**: ป้องกัน DDoS และ Brute Force
4. **Input Validation**: Validate ทุก Input
5. **SQL Injection**: Use parameterized queries
6. **CORS**: Set CORS headers ให้เหมาะสม

## Support

สำหรับคำถามเกี่ยวกับ Integration ติดต่อทีม Support ของคุณ

หมายเหตุ: Plugin นี้ตั้งใจให้ทำงานกับ API ที่เป็น REST API มาตรฐาน
