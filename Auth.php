<?php
$headers = getallheaders();
$secret_token = "MyStockSecretToken_2026";
$provided_token = $headers['X-Stock-Token'] ?? '';
if ($provided_token !== $secret_token) {
    http_response_code(403);
    exit("Unauthorized");
}