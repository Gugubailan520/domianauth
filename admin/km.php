<?php
session_start();

// 检查登录状态
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    die('拒绝访问：请先登录');
}

// 使用绝对路径连接数据库
require_once '../config/db.php';

// 生成卡密
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $cardType = $_POST['card_type'];
    $duration = (int)$_POST['duration'];
    $quantity = (int)$_POST['quantity'];
    $prefix = $_POST['prefix'] ?? '';

    if ($quantity <= 0 || $quantity > 1000) {
        die('生成数量必须在1 - 1000之间');
    }

    $keys = [];
    $pdo->beginTransaction();

    try {
        for ($i = 0; $i < $quantity; $i++) {
            $key = $prefix . strtoupper(substr(md5(uniqid() . microtime() . rand(1000, 9999)), 0, 16));

            $stmt = $pdo->prepare("INSERT INTO card_keys (card_key, card_type, duration) VALUES (?, ?, ?)");
            $stmt->execute([$key, $cardType, $duration]);

            $keys[] = $key;
        }

        $pdo->commit();
        $success = "成功生成 {$quantity} 张卡密！";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "生成卡密失败: " . $e->getMessage();
    }
}

// 删除已使用卡密
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_used'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM card_keys WHERE status = 1");
        $stmt->execute();
        $success = "已成功删除所有已使用卡密！";
    } catch (PDOException $e) {
        $error = "删除已使用卡密失败: " . $e->getMessage();
    }
}

// 删除所有卡密
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM card_keys");
        $stmt->execute();
        $success = "已成功删除所有卡密！";
    } catch (PDOException $e) {
        $error = "删除所有卡密失败: " . $e->getMessage();
    }
}

// 获取卡密列表
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(card_key LIKE ? OR card_type LIKE ? OR domain LIKE ? OR bind_ip LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM card_keys $whereClause");
$stmt->execute($params);
$total = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM card_keys $whereClause ORDER BY id DESC LIMIT $offset, $perPage");
$stmt->execute($params);
$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPages = ceil($total / $perPage);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>卡密管理</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 基础样式 */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* 弹窗样式 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        /* 表单样式 */
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            font-weight: 500;
        }
        
        .btn-primary {
            background-color: #4285f4;
            color: white;
        }
        
        .btn-secondary {
            background-color: #f1f1f1;
            color: #333;
            margin-right: 10px;
        }
        
        /* 卡片列表样式 */
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: #f9f9f9;
            font-weight: 500;
        }
        
        /* 响应式调整 */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
            }
            
            table {
                display: block;
            }
            
            thead {
                display: none;
            }
            
            tr {
                display: block;
                margin-bottom: 15px;
                border: 1px solid #eee;
                border-radius: 4px;
            }
            
            td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 15px;
            }
            
            td::before {
                content: attr(data-label);
                font-weight: 500;
                color: #666;
                margin-right: 10px;
            }
        }
        
        /* 状态标签 */
        .status-used {
            color: #e53935;
        }
        
        .status-unused {
            color: #43a047;
        }
        
        /* 操作按钮 */
        .action-btns {
            display: flex;
            gap: 10px;
        }
        
        /* 分页样式 */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .page-item {
            margin: 0 5px;
        }
        
        .page-link {
            display: block;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #333;
            text-decoration: none;
        }
        
        .page-link:hover {
            background-color: #f5f5f5;
        }
        
        .page-item.active .page-link {
            background-color: #4285f4;
            color: white;
            border-color: #4285f4;
        }
        
        /* 浮动按钮 */
        .float-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #4285f4;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            z-index: 999;
        }
        
        .float-btn i {
            font-size: 24px;
        }
        
        /* 提示框样式 */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            display: flex;
            align-items: center;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .alert i {
            margin-right: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>
        
        <!-- 生成卡密按钮 -->
        <button id="generateBtn" class="btn btn-primary">
            <i class="fas fa-plus"></i> 生成新卡密
        </button>
        
        <!-- 卡密列表 -->
        <div class="card">
            <div class="card-header">
                <h2>卡密列表</h2>
                <form method="get" class="search-form">
                    <input type="text" name="search" placeholder="搜索卡密或类型" value="<?= htmlspecialchars($search) ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <div class="card-body">
                <div class="action-btns">
                    <form method="post">
                        <button type="submit" name="delete_used" class="btn btn-secondary">
                            <i class="fas fa-trash-alt"></i> 删除已使用
                        </button>
                    </form>
                    <form method="post">
                        <button type="submit" name="delete_all" class="btn btn-secondary">
                            <i class="fas fa-broom"></i> 删除全部
                        </button>
                    </form>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>卡密</th>
                                <th>类型</th>
                                <th>有效期(天)</th>
                                <th>状态</th>
                                <th>创建时间</th>
                                <th>使用时间</th>
                                <th>兑换域名</th>
                                <th>绑定IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cards)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center;">暂无卡密数据</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cards as $card): ?>
                                    <tr>
                                        <td data-label="卡密"><?= $card['card_key'] ?></td>
                                        <td data-label="类型"><?= htmlspecialchars($card['card_type']) ?></td>
                                        <td data-label="有效期"><?= $card['duration'] ?></td>
                                        <td data-label="状态">
                                            <span class="<?= $card['status'] ? 'status-used' : 'status-unused' ?>">
                                                <?= $card['status'] ? '已使用' : '未使用' ?>
                                            </span>
                                        </td>
                                        <td data-label="创建时间"><?= $card['create_time'] ?></td>
                                        <td data-label="使用时间"><?= $card['use_time'] ?? '-' ?></td>
                                        <td data-label="兑换域名"><?= $card['domain'] ?? '-' ?></td>
                                        <td data-label="绑定IP"><?= $card['bind_ip'] ?? '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="page-item">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="page-item">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- 生成卡密弹窗 -->
    <div id="generateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>生成新卡密</h3>
                <button class="close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" id="generateForm">
                    <div class="form-group">
                        <label for="card_type">会员类型</label>
                        <input type="text" id="card_type" name="card_type" class="form-control" required placeholder="例如：VIP会员">
                    </div>
                    
                    <div class="form-group">
                        <label for="duration">有效期(天)</label>
                        <input type="number" id="duration" name="duration" class="form-control" min="1" value="30" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">生成数量</label>
                        <input type="number" id="quantity" name="quantity" class="form-control" min="1" max="1000" value="10" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="prefix">前缀(可选)</label>
                        <input type="text" id="prefix" name="prefix" class="form-control" maxlength="4" placeholder="例如：VIP-">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-btn">取消</button>
                <button type="submit" form="generateForm" name="generate" class="btn btn-primary">生成</button>
            </div>
        </div>
    </div>
    
    <!-- 浮动按钮（移动端使用） -->
    <div class="float-btn" id="mobileGenerateBtn">
        <i class="fas fa-plus"></i>
    </div>
    
    <script>
        // 弹窗控制逻辑
        const generateBtn = document.getElementById('generateBtn');
        const mobileGenerateBtn = document.getElementById('mobileGenerateBtn');
        const generateModal = document.getElementById('generateModal');
        const closeBtns = document.querySelectorAll('.close-btn');
        
        function openModal() {
            generateModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            generateModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        generateBtn.addEventListener('click', openModal);
        mobileGenerateBtn.addEventListener('click', openModal);
        
        closeBtns.forEach(btn => {
            btn.addEventListener('click', closeModal);
        });
        
        // 点击模态框外部关闭
        generateModal.addEventListener('click', (e) => {
            if (e.target === generateModal) {
                closeModal();
            }
        });
        
        // 表单验证
        document.getElementById('generateForm').addEventListener('submit', function(e) {
            const quantity = parseInt(document.getElementById('quantity').value);
            if (quantity <= 0 || quantity > 1000) {
                alert('生成数量必须在1 - 1000之间');
                e.preventDefault();
            }
        });
    </script>
</body>
</html>