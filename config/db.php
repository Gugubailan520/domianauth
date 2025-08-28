<?php
// 数据库连接信息
$host = 'localhost';
$dbname = 'auth';
$user = 'auth';
$pass = 'auth123456';

try {
    // 创建 PDO 实例
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    return $pdo;
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}