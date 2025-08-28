<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['proxy_logged_in'])) {
    header('Location: login.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM proxy_users WHERE id = ?");
    $stmt->execute([$_SESSION['proxy_id']]);
    $proxyInfo = $stmt->fetch();
} catch (PDOException $e) {
    die("获取代理信息失败: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>代理中心</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
        }
        
        body {
            background: #f8f9fc;
        }
        
        .navbar {
            background: var(--primary-color) !important;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .stat-card {
            border-left: 0.25rem solid var(--primary-color);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .card-title {
                font-size: 1.1rem;
            }
            
            .navbar-brand {
                font-size: 1rem;
            }
            
            .btn {
                font-size: 0.9rem;
                padding: 0.375rem 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">代理中心</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">首页</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cards.php">卡密管理</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_card.php">生成卡密</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <?= htmlspecialchars($_SESSION['proxy_username']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="logout.php">退出登录</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- 欢迎标题 -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">欢迎回来，<?= htmlspecialchars($_SESSION['proxy_username']) ?></h3>
            <small class="text-muted">最后登录：<?= date('Y-m-d H:i') ?></small>
        </div>

      
            
         
        <!-- 主内容区 -->
        <div class="row mt-4 g-4">
           
            </div>

            <div class="col-12 col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">账户信息</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>用户名：</span>
                                <span><?= htmlspecialchars($proxyInfo['username']) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>注册时间：</span>
                                <span><?= $proxyInfo['created_at'] ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>邮箱：</span>
                                <span><?= htmlspecialchars($proxyInfo['email']) ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>