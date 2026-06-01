<?php
/**
 * includes/db.php — การเชื่อมต่อฐานข้อมูล (อ่านค่าจาก config)
 *   db()  -> PDO    (ใช้ในหน้าทั่วไป + save/cancel)
 *   dbm() -> mysqli (ใช้ใน teacher_page.php)
 */
require_once __DIR__ . '/../config/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

function dbm(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            http_response_code(500);
            die('Database connection failed: ' . $conn->connect_error);
        }
        $conn->set_charset(DB_CHARSET);
    }
    return $conn;
}
