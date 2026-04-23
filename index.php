<?php
// require_once("Auth.php");
require_once("config.php");
require_once("function-tools.php");
require_once("function-getData.php");



insertHistory($db_ip, $db_name, $db_user, $db_pass, getHistory(getLatestTradingDateWithTWSE()));