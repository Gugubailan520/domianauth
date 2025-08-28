<?php
// 开启会话
session_start();

// 检查登录状态
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    die('拒绝访问：请先登录');
}

require_once __DIR__ . '/../config/db.php';

// 添加代理用户
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_proxy'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $email = trim($_POST['email']);
    
    if (empty($username) || empty($password) || empty($email)) {
        $_SESSION['error'] = "请填写所有字段";
    } else {
        try {
            // 检查用户名是否已存在
            $stmt = $pdo->prepare("SELECT id FROM proxy_users WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['error'] = "用户名已存在";
            } else {
                // 插入新代理用户
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO proxy_users (username, password, email) VALUES (?, ?, ?)");
                $stmt->execute([$username, $hashedPassword, $email]);
                $_SESSION['success'] = "代理用户添加成功";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "数据库错误: " . $e->getMessage();
        }
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// 删除代理用户
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = (int)$_POST['user_id'];
    try {
        $pdo->beginTransaction();
        
        // 先删除关联的卡密
        $stmt = $pdo->prepare("DELETE FROM card_keys WHERE proxy_id = ?");
        $stmt->execute([$userId]);
        
        // 再删除代理用户
        $stmt = $pdo->prepare("DELETE FROM proxy_users WHERE id = ?");
        $stmt->execute([$userId]);
        
        $pdo->commit();
        $_SESSION['success'] = "代理用户删除成功";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "删除失败: " . $e->getMessage();
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// 获取代理用户列表
$proxyUsers = [];
try {
    $stmt = $pdo->query("SELECT id, username, email, created_at FROM proxy_users ORDER BY id DESC");
    $proxyUsers = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "获取用户列表失败: " . $e->getMessage();
}

// 显示消息后清除
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>代理用户管理</title>
    <style>
        /* 基础样式 */
        :root {
            --primary-color: #4e73df;
            --primary-dark: #3a5bc7;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --danger-dark: #d62c1a;
            --secondary-color: #6c757d;
            --light-color: #f8f9fc;
            --border-color: #e3e6f0;
            --text-color: #212529;
            --text-muted: #6c757d;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.5;
            color: var(--text-color);
            background-color: var(--light-color);
            padding-bottom: 20px;
        }
        
        a {
            text-decoration: none;
            color: inherit;
        }
        
        /* 布局样式 */
        .container {
            width: 100%;
            padding: 0 15px;
            margin: 0 auto;
        }
        
        /* 卡片样式 */
        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 15px;
            background-color: var(--light-color);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 0;
        }
        
        /* 表格样式 */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .table th, .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table th {
            font-weight: 600;
            color: var(--text-muted);
            background-color: var(--light-color);
            white-space: nowrap;
        }
        
        .table tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        /* 按钮样式 */
        .btn {
            display: inline-block;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 400;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
            border: 1px solid transparent;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: #fff;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            color: #fff;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: #fff;
        }
        
        .btn-danger:hover {
            background-color: var(--danger-dark);
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 13px;
        }
        
        /* 表单样式 */
        .form-control {
            display: block;
            width: 100%;
            padding: 8px 12px;
            font-size: 14px;
            line-height: 1.5;
            color: var(--text-color);
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: 4px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-control:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
        }
        
        /* 工具类 */
        .d-flex {
            display: flex;
        }
        
        .justify-content-between {
            justify-content: space-between;
        }
        
        .align-items-center {
            align-items: center;
        }
        
        .mb-3 {
            margin-bottom: 16px;
        }
        
        .py-3 {
            padding-top: 16px;
            padding-bottom: 16px;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-muted {
            color: var(--text-muted);
        }
        
        /* 搜索框 */
        .search-box {
            position: relative;
            width: 250px;
        }
        
        .search-box input {
            padding-left: 35px;
        }
        
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        
        /* 提示信息 */
        .alert {
            padding: 12px 16px;
            margin-bottom: 16px;
            border-radius: 4px;
            position: relative;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .close-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: inherit;
        }
        
        /* 模态框 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .modal.show {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding-top: 30px;
        }
        
        .modal-dialog {
            width: 100%;
            max-width: 500px;
            background: #fff;
            border-radius: 8px;
            margin: 0 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .modal-header {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .modal-body {
            padding: 16px;
        }
        
        .modal-footer {
            padding: 12px 16px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
        }
        
        /* 响应式设计 */
        @media (min-width: 576px) {
            .container {
                max-width: 540px;
            }
        }
        
        @media (min-width: 768px) {
            .container {
                max-width: 720px;
            }
        }
        
        @media (min-width: 992px) {
            .container {
                max-width: 960px;
            }
        }
        
        @media (min-width: 1200px) {
            .container {
                max-width: 1140px;
            }
        }
        
        @media (max-width: 767px) {
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-box {
                width: 100%;
                margin-top: 10px;
            }
            
            .table th, .table td {
                padding: 8px 10px;
            }
            
            .modal-dialog {
                margin: 15px;
            }
        }
        
        @media (max-width: 575px) {
            .container {
                padding: 0 10px;
            }
            
            .table-responsive {
                border: 1px solid var(--border-color);
                border-radius: 4px;
            }
            
            .table {
                min-width: 500px;
            }
        }
    </style>
</head>
<body>
    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          
            <button class="btn btn-primary" id="addUserBtn">添加代理</button>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
                <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
                <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2 class="m-0">代理用户列表</h2>
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="searchInput" class="form-control" placeholder="搜索用户名或邮箱...">
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table" id="proxyTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>用户名</th>
                                <th>邮箱</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proxyUsers as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['id']) ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <form method="post" style="display:inline-block;" onsubmit="return confirm('确定要删除此代理吗？所有关联卡密也将被删除！');">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" name="delete_user" class="btn btn-danger btn-sm">删除</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($proxyUsers)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">暂无代理用户数据</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 添加用户模态框 -->
    <div class="modal" id="addUserModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">添加代理用户</h5>
                    <button type="button" class="close-btn" onclick="closeModal()">&times;</button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="username" class="form-label">用户名</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">密码</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">邮箱</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">取消</button>
                        <button type="submit" name="add_proxy" class="btn btn-primary">确认添加</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // 显示模态框
        document.getElementById('addUserBtn').addEventListener('click', function() {
            document.getElementById('addUserModal').classList.add('show');
        });
        
        // 关闭模态框
        function closeModal() {
            document.getElementById('addUserModal').classList.remove('show');
        }
        
        // 点击模态框外部关闭
        window.addEventListener('click', function(event) {
            if (event.target === document.getElementById('addUserModal')) {
                closeModal();
            }
        });
        
        // 搜索功能
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#proxyTable tbody tr');
            
            rows.forEach(row => {
                const username = row.cells[1].textContent.toLowerCase();
                const email = row.cells[2].textContent.toLowerCase();
                
                if (username.includes(searchValue) || email.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // 如果有错误消息且是添加操作，显示模态框
        <?php if ($error && isset($_POST['add_proxy'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('addUserModal').classList.add('show');
            });
        <?php endif; ?>
    </script>
</body>
</html>