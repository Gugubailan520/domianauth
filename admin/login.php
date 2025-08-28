<?php
// login.php

// 开启会话
session_start();

// 引入数据库连接文件
require_once '../config/db.php';

// 初始化错误消息
$error = '';

// 检查表单是否提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取用户输入的用户名和密码
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    try {
        // 获取数据库连接
        $pdo = require '../config/db.php';

        // 查询数据库中的用户
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 验证用户名和密码
        if ($user && md5($password) === $user['password_hash']) {
            // 登录成功，设置会话变量
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $user['username'];
            $_SESSION['userid'] = $user['id'];

            // 重定向到受保护的页面
            header('Location: protected.php');
            exit;
        } else {
            // 登录失败，设置错误消息
            $error = '用户名或密码错误！';
        }
    } catch (PDOException $e) {
        // 数据库连接或查询错误
        $error = '数据库错误：' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录页面</title>
    <style>
         body {
            font-family: 'Helvetica Neue', Arial, 'PingFang SC', 'Microsoft YaHei', sans-serif;
            background: url('https://cdn-hsyq-static.shanhutech.cn/bizhi/staticwp/202506/7af87d9be960868da4db84e711ae1890--1912782428.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            position: relative;
        }
        .login-container {
            background-color: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
            animation: fadeIn 1s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
        }
        input[type="text"],
        input[type="password"] {
            width: 80%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #ff69b4;
            outline: none;
        }
        input[type="submit"] {
            width: 80%;
            padding: 12px;
            background-color: #4285f4;
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        input[type="submit"]:hover {
            background-color: #3367d6;
        }
        .error-message {
            color: #ff4757;
            text-align: center;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .login-container img {
            width: 100px;
            margin-bottom: 20px;
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        @media (max-width: 480px) {
            .login-container {
                padding: 20px;
            }
            h2 {
                font-size: 20px;
            }
            input[type="text"],
            input[type="password"],
            input[type="submit"] {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="https://cdn-icons-png.flaticon.com/512/5087/5087579.png" alt="Login Icon">
        <h2>登录</h2>
        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="text" name="username" placeholder="用户名" required>
            <input type="password" name="password" placeholder="密码" required>
            <input type="submit" value="登录">
        </form>
    </div>
</body>
</html>