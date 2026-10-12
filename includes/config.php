<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Manila');

const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'tnajart';
const SHOP_NAME = 'Tindahan ni Aling Jorhina';
const LOYALTY_PESOS_PER_POINT = 100;

$docRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$appRoot = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
define('APP_ROOT', $appRoot);

if ($docRoot !== '' && str_starts_with($appRoot, $docRoot)) {
    define('BASE_URL', substr($appRoot, strlen($docRoot)));
} else {
    define('BASE_URL', '');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    mysqli_query($conn, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    mysqli_query($conn, "SET time_zone = '+08:00'");
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo '<h2>Cannot open the database "' . htmlspecialchars(DB_NAME) . '"</h2>';
    echo '<p>Start MySQL in XAMPP, import db/tnajart.sql in phpMyAdmin, and check the settings at the top of includes/config.php.</p>';
    echo '<p>MySQL said: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

include(__DIR__ . '/functions.php');
include(__DIR__ . '/orders.php');