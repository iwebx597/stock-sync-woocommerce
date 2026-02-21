<?php
/**
 * Mock API Server สำหรับทดสอบ
 * 
 * WARNING: ไฟล์นี้ใช้สำหรับทดสอบเท่านั้น!
 * ต้องลบหรือปิดการเข้าถึงไฟล์นี้ในเซิร์ฟเวอร์ Production
 * 
 * ทดสอบด้วย: http://localhost/wp-content/plugins/stock-sync-woocommerce/mock-api.php?action=products&page=1
 */

// Production Guard: บล็อกโดย default — อนุญาตเฉพาะเมื่อ WP_DEBUG = true เท่านั้น
// ถ้าเข้าตรงจาก browser (ไม่ผ่าน WP): อ่าน wp-config.php เพื่อตรวจ WP_DEBUG
// ถ้าผ่าน WP: ใช้ constant WP_DEBUG ตรง ๆ
$ssw_mock_allowed = false;
if (defined('ABSPATH')) {
    // Loaded through WordPress
    $ssw_mock_allowed = defined('WP_DEBUG') && WP_DEBUG;
} else {
    // Direct browser access — parse wp-config.php to check WP_DEBUG
    $ssw_config_path = dirname(dirname(dirname(__DIR__))) . '/wp-config.php';
    if (file_exists($ssw_config_path)) {
        $ssw_config_content = file_get_contents($ssw_config_path);
        if (preg_match("/define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*true\s*\)/i", $ssw_config_content)) {
            $ssw_mock_allowed = true;
        }
    }
}

if (!$ssw_mock_allowed) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(array('error' => 'Forbidden: mock API is disabled in production'));
    exit;
}
unset($ssw_mock_allowed, $ssw_config_path, $ssw_config_content);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Sanitize: standalone PHP — ไม่มี WP functions จึงใช้ native PHP แทน
$action = isset($_GET['action']) ? preg_replace('/[^a-z0-9_-]/', '', strtolower($_GET['action'])) : 'products';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

if ($action === 'products') {
    // Mock Products Data
    $all_products = array(
        array(
            'id' => '1',
            'sku' => 'SHIRT-RED-S',
            'name' => 'Red T-Shirt - Small',
            'description' => 'Comfortable red cotton t-shirt perfect for casual wear',
            'short_description' => 'Red T-Shirt',
            'regular_price' => '25.00',
            'sale_price' => '19.99',
            'stock_quantity' => 150,
            'categories' => array('Clothing', 'Men'),
            'images' => array(
                'https://via.placeholder.com/300?text=Red+Shirt'
            ),
            'weight' => '0.5'
        ),
        array(
            'id' => '2',
            'sku' => 'SHIRT-BLUE-M',
            'name' => 'Blue T-Shirt - Medium',
            'description' => 'Cool blue cotton t-shirt with modern design',
            'short_description' => 'Blue T-Shirt',
            'regular_price' => '25.00',
            'sale_price' => '0.00',
            'stock_quantity' => 200,
            'categories' => array('Clothing', 'Men'),
            'images' => array(
                'https://via.placeholder.com/300?text=Blue+Shirt'
            ),
            'weight' => '0.5'
        ),
        array(
            'id' => '3',
            'sku' => 'JEANS-BLACK',
            'name' => 'Black Jeans - Regular Fit',
            'description' => 'Classic black denim jeans with durability and comfort',
            'short_description' => 'Black Jeans',
            'regular_price' => '49.99',
            'sale_price' => '39.99',
            'stock_quantity' => 80,
            'categories' => array('Clothing', 'Men', 'Pants'),
            'images' => array(
                'https://via.placeholder.com/300?text=Black+Jeans'
            ),
            'weight' => '0.8'
        ),
        array(
            'id' => '4',
            'sku' => 'SHOES-RUNNING',
            'name' => 'Running Shoes - Athletic',
            'description' => 'Professional running shoes with advanced cushioning technology',
            'short_description' => 'Running Shoes',
            'regular_price' => '89.99',
            'sale_price' => '69.99',
            'stock_quantity' => 45,
            'categories' => array('Footwear', 'Sports'),
            'images' => array(
                'https://via.placeholder.com/300?text=Running+Shoes'
            ),
            'weight' => '0.35'
        ),
        array(
            'id' => '5',
            'sku' => 'CAP-BASEBALL',
            'name' => 'Baseball Cap - Cotton',
            'description' => 'Adjustable cotton baseball cap perfect for outdoor activities',
            'short_description' => 'Baseball Cap',
            'regular_price' => '15.00',
            'sale_price' => '0.00',
            'stock_quantity' => 300,
            'categories' => array('Accessories'),
            'images' => array(
                'https://via.placeholder.com/300?text=Baseball+Cap'
            ),
            'weight' => '0.15'
        )
    );

    // Pagination (5 products per page)
    $per_page = 5;
    $total_products = count($all_products);
    $total_pages = ceil($total_products / $per_page);
    $start_index = ($page - 1) * $per_page;
    $end_index = $start_index + $per_page;

    $products_on_page = array_slice($all_products, $start_index, $per_page);

    $response = array(
        'data' => $products_on_page,
        'meta' => array(
            'total' => $total_products,
            'page' => $page,
            'limit' => $per_page,
            'pages' => $total_pages
        ),
        'has_more' => $page < $total_pages
    );

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Default error response
http_response_code(400);
echo json_encode(array('error' => 'Invalid action'));
?>
