<?php
$token = "MyStockSecretToken_2026";
if (!isset($_POST['token']) || !$_POST['token'] === $token) {
    http_response_code(403);
    exit("Unauthorized");
}
