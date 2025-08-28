<?php
// 必须在文件最开头启动会话
session_start();

// 引入数据库配置文件
require_once __DIR__ . '/config/db.php';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);

    if (!empty($username)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM proxy_users WHERE username = ?");
            $stmt->execute([$username]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['result_message'] = "用户 {$username} 是代理用户。";
            } else {
                $_SESSION['result_message'] = "用户 {$username} 不是代理用户。";
            }
        } catch (PDOException $e) {
            $_SESSION['result_message'] = "数据库错误: " . $e->getMessage();
        }
    } else {
        $_SESSION['result_message'] = "请输入用户名。";
    }

    // 重定向到GET请求
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// 从会话中获取结果消息
$resultMessage = $_SESSION['result_message'] ?? '';
unset($_SESSION['result_message']);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- 添加视口元数据 -->
    <title>代理用户查询</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">代理用户查询</div>
                    <div class="card-body">
                        <?php if (!empty($resultMessage)): ?>
                            <div class="alert alert-info"><?= htmlspecialchars($resultMessage) ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label for="username" class="form-label">用户名</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <button type="submit" class="btn btn-primary">查询</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>