<?php
// index.php

// 开启会话
session_start();

// 检查用户是否已登录
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    // 如果已登录，重定向到 protected.php
    header('Location: protected.php');
    exit;
} else {
    // 如果未登录，重定向到 login.php
    header('Location: login.php');
    exit;
}
?>