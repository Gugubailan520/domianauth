<?php
// change_password.php

// 开启会话
session_start();

// 检查登录状态
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    die('拒绝访问：请先登录');
}

// 引入数据库连接文件
require_once '../config/db.php';

// 初始化消息
$error = '';
$success = '';

// 获取当前登录用户的用户名和用户ID
$current_username = $_SESSION['username'];
$current_userid = $_SESSION['userid'];

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 获取表单数据
        $new_username = trim($_POST['new_username'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        // 验证用户名（如果提供了新用户名）
        if (!empty($new_username)) {
            if (strlen($new_username) < 4) {
                throw new Exception('用户名至少需要4个字符');
            }
            
            // 检查用户名是否已存在
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :id");
            $stmt->execute(['username' => $new_username, 'id' => $current_userid]);
            if ($stmt->fetch()) {
                throw new Exception('该用户名已被使用');
            }
        }

        // 验证密码（如果提供了新密码）
        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                throw new Exception('密码至少需要6个字符');
            }

            if ($new_password !== $confirm_password) {
                throw new Exception('两次输入的密码不一致');
            }
        }

        // 如果没有提供新用户名，则使用当前用户名
        if (empty($new_username)) {
            $new_username = $current_username;
        }

        // 准备更新语句
        $sql = "UPDATE users SET username = :username";
        $params = ['username' => $new_username, 'id' => $current_userid];

        // 如果有新密码，更新密码
        if (!empty($new_password)) {
            // 使用md5进行加密
            $new_password_hash = md5($new_password);
            $sql .= ", password_hash = :password_hash";
            $params['password_hash'] = $new_password_hash;
        }

        $sql .= " WHERE id = :id";

        // 更新数据库中的用户名和密码
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // 更新会话中的用户名
        $_SESSION['username'] = $new_username;

        // 提示修改成功
        $success = '账号信息更新成功！';
        $current_username = $new_username; // 更新显示的当前用户名
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改账号密码</title>
    
    <style>
        :root {
            --blue: #0d6efd;
            --blue-dark: #0b5ed7;
        }
        
        body {
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1rem;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            line-height: 1.5;
        }
        
        .account-container {
            background: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            margin: auto;
        }
        
        h2 {
            text-align: center;  
            margin-bottom: 1.5rem;
            color: var(--blue);  
            font-weight: 600;
        }
        
        .current-info i {
            color: var(--blue);
            margin-right: 0.5rem;
        }
        
        .btn-purple {
            background-color: var(--blue);
            border-color: var(--blue);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 1rem;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .btn-purple:hover {
            background-color: var(--blue-dark);
            border-color: var(--blue-dark);
            color: white;
        }
        
        .form-control:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .form-control {
            display: block;
            width: 100%;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            border-radius: 0.375rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-label {
            margin-bottom: 0.5rem;
            display: inline-block;
        }
        
        .mb-3 {
            margin-bottom: 1rem !important;
        }
        
        .d-grid {
            display: grid !important;
        }
        
        .gap-2 {
            gap: 0.5rem !important;
        }
        
        .btn-lg {
            padding: 0.5rem 1rem;
            font-size: 1.25rem;
            border-radius: 0.3rem;
        }
        
        .alert {
            position: relative;
            padding: 1rem 1rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.375rem;
        }
        
        .alert-danger {
            color: #842029;
            background-color: #f8d7da;
            border-color: #f5c2c7;
        }
        
        .alert-success {
            color: #0f5132;
            background-color: #d1e7dd;
            border-color: #badbcc;
        }
        
        .alert-dismissible {
            padding-right: 3rem;
        }
        
        .btn-close {
            box-sizing: content-box;
            width: 1em;
            height: 1em;
            padding: 0.25em 0.25em;
            color: #000;
            background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23000'%3e%3cpath d='M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat;
            border: 0;
            border-radius: 0.375rem;
            opacity: 0.5;
            float: right;
        }
        
        .fade {
            transition: opacity 0.15s linear;
        }
        
        @media (prefers-reduced-motion: reduce) {
            .fade {
                transition: none;
            }
        }
        
        .fade:not(.show) {
            opacity: 0;
        }
        
        .bi {
            display: inline-block;
            vertical-align: -.125em;
            font-size: 1rem;
        }
        
        @media (max-width: 576px) {
            h2 {
                font-size: 1.5rem;
            }
            
            .account-container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="account-container">
        <h2>修改账号信息</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="current-info">
            <i class="bi">👤</i> 当前用户名: <?= htmlspecialchars($current_username) ?>
        </div>

        <form method="post">
            <div class="mb-3">
                <label for="new_username" class="form-label">新用户名 (留空则不修改)</label>
                <input type="text" class="form-control" id="new_username" name="new_username" 
                       minlength="4" placeholder="至少4个字符"
                       value="<?= isset($_POST['new_username']) ? htmlspecialchars($_POST['new_username']) : '' ?>">
            </div>

            <div class="mb-3">
                <label for="new_password" class="form-label">新密码 (留空则不修改)</label>
                <input type="password" class="form-control" id="new_password" name="new_password" 
                       minlength="6" placeholder="至少6个字符">
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label">确认新密码</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                       minlength="6" placeholder="再次输入新密码">
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-purple btn-lg">保存修改</button>
            </div>
        </form>
    </div>

    <script>
        // 自动关闭警告消息
        document.addEventListener('DOMContentLoaded', function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.classList.remove('show');
                    setTimeout(function() {
                        alert.remove();
                    }, 150);
                }, 5000);
            });
            
            // 添加关闭按钮功能
            document.querySelectorAll('.btn-close').forEach(function(button) {
                button.addEventListener('click', function() {
                    var alert = this.closest('.alert');
                    alert.classList.remove('show');
                    setTimeout(function() {
                        alert.remove();
                    }, 150);
                });
            });
        });
    </script>
</body>
</html>