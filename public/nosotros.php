<?php
require_once __DIR__ . '/../config/config.php';
header('HTTP/1.1 301 Moved Permanently');
header('Location: ' . PUBLIC_URL . '/el-parque.php');
exit;
