<?php
echo "<h2>🧪 TEST API ADD TO CART</h2>";

// Test data
$item_id = 1; // Thay đổi item_id nếu cần
$user_id = 1; // Thay đổi user_id nếu cần

echo "<h3>Test với item_id: $item_id, user_id: $user_id</h3>";

// Simulate POST request
$postData = json_encode([
    'item_id' => $item_id,
    'quantity' => 1
]);

echo "<h4>POST Data:</h4>";
echo "<pre>" . $postData . "</pre>";

// Test API
$url = "http://localhost/Cap%201%20-%202/api/add-to-cart.php";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($postData)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=test'); // Cần session

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h4>Response (HTTP $httpCode):</h4>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Parse response
$data = json_decode($response, true);
if ($data) {
    echo "<h4>Parsed Response:</h4>";
    echo "<pre>" . print_r($data, true) . "</pre>";
    
    if (isset($data['success']) && $data['success']) {
        echo "<p style='color: green;'>✅ API hoạt động đúng!</p>";
    } else {
        echo "<p style='color: red;'>❌ API lỗi: " . ($data['message'] ?? 'Unknown error') . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Không thể parse JSON response</p>";
}

echo "<h3>Lưu ý:</h3>";
echo "<ul>";
echo "<li>API cần user đăng nhập (session)</li>";
echo "<li>Item phải tồn tại và có sẵn</li>";
echo "<li>Kiểm tra database có dữ liệu không</li>";
echo "</ul>";
?>
