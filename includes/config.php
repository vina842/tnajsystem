<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Manila');

const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'tnajart';
const SHOP_NAME = 'Tindahan ni Aling Jorhina';
const LOYALTY_PESOS_PER_POINT = 100;

$docRoot = rtrim(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$appRoot = rtrim(str_replace('\\', '/', (string) realpath(__DIR__ . '/..')), '/');
define('APP_ROOT', $appRoot);
define('BASE_URL', ($docRoot !== '' && strncasecmp($appRoot, $docRoot, strlen($docRoot)) === 0) ? substr($appRoot, strlen($docRoot)) : '');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    mysqli_set_charset($conn, 'utf8mb4');
    mysqli_query($conn, "SET time_zone = '+08:00'");
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Database not ready</title>';
    echo '<div style="font-family:system-ui,sans-serif;max-width:560px;margin:15vh auto;padding:0 20px">';
    echo '<h2>Cannot open the database "' . htmlspecialchars(DB_NAME) . '"</h2>';
    echo '<p>1. Open the XAMPP Control Panel and start <b>MySQL</b>.<br>';
    echo '2. In phpMyAdmin, import <code>db/tnajart.sql</code>.<br>';
    echo '3. Check the settings at the top of <code>includes/config.php</code>.</p>';
    echo '<p style="color:#666">MySQL said: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
    exit;
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/orders.php';
