<?php
require_once("init.php");
        lineNotification($pdo, getenv('LINE_TARGET'),'今日盤後篩選及評分排行已完成, 請稍候佈署 - https://yong-jhih.github.io/Stocks/');
