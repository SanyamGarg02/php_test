<?php
$esHost = "https://localhost:9200";
$esUser = "elastic";
$esPass = "YOUR PASSWORD";
$testIndex = "competitor_offers_test";

// Index mapping
$body = json_encode([
    "mappings" => [
        "properties" => [
            "name" => ["type" => "text"],
            "price" => ["type" => "integer"],
            "url" => ["type" => "keyword"],
            "stone_type" => ["type" => "keyword"],
            "stone_shape" => ["type" => "keyword"],
            "stone_clarity" => ["type" => "keyword"],
            "stone_color" => ["type" => "keyword"],
            "stone_carat_weight" => ["type" => "float"],
            "metal_type" => ["type" => "keyword"],
            "metal_color" => ["type" => "keyword"],
            "gold_karat" => ["type" => "integer"],
            "ring_size" => ["type" => "float"],
            "source" => ["type" => "keyword"]
        ]
    ]
]);

$ch = curl_init("$esHost/$testIndex");
curl_setopt($ch, CURLOPT_USERPWD, "$esUser:$esPass");
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
