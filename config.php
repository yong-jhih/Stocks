<?php

$db_ip   = getenv('DB_HOST') || '127.0.0.1';
$db_user = getenv('DB_USER') || 'root';
$db_pass = getenv('DB_PASS') || '';
$db_name = getenv('DB_NAME') || 'somethin_tools';

if (!$db_ip) {
    die("錯誤：找不到資料庫環境變數。請檢查 GitHub Secrets 設定。");
}
$finmind_token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJkYXRlIjoiMjAyNi0wNC0xNSAxMTo1MTowNCIsInVzZXJfaWQiOiJ5ajE5ODgiLCJlbWFpbCI6IndhaXR5b3Vmb3JldmVyNzdAZ21haWwuY29tIiwiaXAiOiIxNzIuMTkyLjU1Ljg4In0.lXlbwlyyavUHz14QTWAhVP7rL_IZzQThc4c7-joA7CI';
$fugle_token = 'NDRhODcwM2UtYTU3OC00ZmU5LTllZjMtNTQ3Y2Q5ZjU0YTMwIDQzZWRkOTcxLWFhMzgtNDgwMS1hZjY0LWU4ODM0Y2NkYzNmMg==';