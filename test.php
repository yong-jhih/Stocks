<?php

require_once("init.php");

dbClean($pdo, 'system_logs', 'log_time', 1);
