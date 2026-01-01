<?php
/**
 * Aaro API Proxy
 * CORS hatalarını aşmak için tarayıcı ile API arasında köprü görevi görür.
 */

// CORS ayarları (Geliştirme aşamasında her yerden erişime izin veriyoruz)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Accept");

// OPTIONS (Pre-flight) isteği gelirse doğrudan 200 dön
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// API Ayarları
$baseUrl = "https://erp.aaro.com.tr"; // Sabit tutmak daha güvenli

// Gelen endpoint'i al (ör: /api/Cari)
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

if (empty($endpoint)) {
    echo json_encode(["Sonuc" => false, "Mesajlar" => ["Mesaj" => "No endpoint provided"]]);
    exit;
}

// Query string'i koru (endpoint haricindekileri)
$queryParams = $_GET;
unset($queryParams['endpoint']);
$fullUrl = $baseUrl . $endpoint;
if (!empty($queryParams)) {
    $fullUrl .= '?' . http_build_query($queryParams);
}

// Log Fonksiyonu
function writeProxyLog($msg) {
    $date = date('Y-m-d H:i:s');
    $logMsg = "[$date] $msg" . PHP_EOL;
    file_put_contents('proxy_debug.log', $logMsg, FILE_APPEND);
}

// cURL İsteği Hazırla
writeProxyLog("İstek: " . $_SERVER['REQUEST_METHOD'] . " " . $fullUrl);
$ch = curl_init($fullUrl);

// Gelen header'ları topla (Authorization zorunlu)
$headers = [];
foreach (getallheaders() as $name => $value) {
    if (in_array(strtolower($name), ['authorization', 'content-type', 'accept'])) {
        $headers[] = "$name: $value";
    }
}

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

// POST/PUT verilerini ilet
$input = file_get_contents('php://input');
if (!empty($input)) {
    writeProxyLog("Payload: " . $input);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
}

// İsteği çalıştır
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $errorMsg = "Proxy Error: " . curl_error($ch);
    writeProxyLog("HATA: " . $errorMsg);
    http_response_code(500);
    echo json_encode(["Sonuc" => false, "Mesajlar" => ["Mesaj" => $errorMsg]]);
} else {
    writeProxyLog("Yanıt Kodu: $httpCode | Yanıt: " . substr($response, 0, 500) . (strlen($response) > 500 ? "..." : ""));
    http_response_code($httpCode);
    header("Content-Type: application/json");
    echo $response;
}

curl_close($ch);

