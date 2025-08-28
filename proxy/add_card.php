<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// 检查代理登录状态
if (!isset($_SESSION['proxy_logged_in'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// 处理生成卡密请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $amount = intval($_POST['amount']);
    $duration = intval($_POST['duration']);
    
    if ($amount <= 0 || $amount > 100) {
        $error = "生成数量必须在1-100之间";
    } elseif ($duration <= 0) {
        $error = "有效期必须大于0天";
    } else {
        try {
            $pdo->beginTransaction();
            
            // 生成卡密
            $cards = [];
            for ($i = 0; $i < $amount; $i++) {
                $cardKey = strtoupper(bin2hex(random_bytes(8))); // 16位随机卡密
                $cards[] = $cardKey;
                
                // 插入数据库
                $stmt = $pdo->prepare("INSERT INTO card_keys (card_key, duration, proxy_id, create_time) 
                                      VALUES (?, ?, ?, NOW())");
                $stmt->execute([$cardKey, $duration, $_SESSION['proxy_id']]);
            }
            
            $pdo->commit();
            $success = "成功生成 {$amount} 张卡密";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "生成卡密失败: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>生成卡密</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container mt-4">
        <h2>生成卡密</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
            
            <?php if (isset($cards)): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>生成的卡密列表</h5>
                    </div>
                    <div class="card-body">
                        <textarea class="form-control" rows="10" readonly><?= implode("\n", $cards) ?></textarea>
                        <button class="btn btn-secondary mt-2" onclick="copyCards()">复制卡密</button>
                    </div>
                </div>
                
                <script>
                function copyCards() {
                    const textarea = document.querySelector('textarea');
                    textarea.select();
                    document.execCommand('copy');
                    alert('卡密已复制到剪贴板');
                }
                </script>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="card mt-3">
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">生成数量 (1-100)</label>
                        <input type="number" name="amount" class="form-control" min="1" max="100" value="10" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">有效期(天)</label>
                        <input type="number" name="duration" class="form-control" min="1" value="30" required>
                    </div>
                    <button type="submit" name="generate" class="btn btn-primary">生成卡密</button>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>