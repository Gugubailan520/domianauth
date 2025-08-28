<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// 检查代理登录状态
if (!isset($_SESSION['proxy_logged_in'])) {
    header('Location: login.php');
    exit;
}

// 处理回收卡密请求
if (isset($_GET['recycle']) && isset($_GET['card_key'])) {
    $cardKey = $_GET['card_key'];
    try {
        $stmt = $pdo->prepare("UPDATE card_keys SET status = 0 WHERE card_key = ? AND proxy_id = ?");
        $stmt->execute([$cardKey, $_SESSION['proxy_id']]);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } catch (PDOException $e) {
        die("回收卡密失败: " . $e->getMessage());
    }
}

// 处理导出未使用卡密请求
if (isset($_GET['export'])) {
    try {
        $stmt = $pdo->prepare("SELECT card_key FROM card_keys WHERE proxy_id = ? AND status = 0");
        $stmt->execute([$_SESSION['proxy_id']]);
        $unusedCards = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $filename = 'unused_card_keys_' . date('YmdHis') . '.txt';
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        foreach ($unusedCards as $cardKey) {
            echo $cardKey . "\n";
        }
        exit;
    } catch (PDOException $e) {
        die("导出未使用卡密失败: " . $e->getMessage());
    }
}

// 获取当前页码，默认为第1页
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
// 每页显示的记录数
$limit = 20;
// 计算偏移量
$offset = ($page - 1) * $limit;

// 获取当前代理生成的卡密总数
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM card_keys WHERE proxy_id = ?");
    $countStmt->execute([$_SESSION['proxy_id']]);
    $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $total = $totalResult['total'];
} catch (PDOException $e) {
    die("获取卡密总数失败: " . $e->getMessage());
}

// 计算总页数
$totalPages = ceil($total / $limit);

// 获取当前页的卡密
try {
    $stmt = $pdo->prepare("SELECT * FROM card_keys 
                          WHERE proxy_id = ? 
                          ORDER BY create_time DESC
                          LIMIT $limit OFFSET $offset");
    $stmt->execute([$_SESSION['proxy_id']]);
    $cards = $stmt->fetchAll();
} catch (PDOException $e) {
    die("获取卡密列表失败: " . $e->getMessage());
}

// 包含头部文件
include __DIR__ . '/includes/header.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
    <title>我的卡密</title>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-gray-800">我的卡密</h2>
            <a href="?export=1" class="px-3 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600">导出未使用卡密</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($cards as $card): ?>
            <div class="bg-white shadow-md rounded-lg overflow-hidden p-4">
                <div class="flex flex-col space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-semibold"><?= htmlspecialchars($card['card_key']) ?></span>
                        <?php if ($card['status'] == 0): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            未使用
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            已使用
                        </span>
                        <a href="?recycle=1&card_key=<?= urlencode($card['card_key']) ?>&page=<?= $page ?>" class="ml-2 text-blue-500 hover:underline">回收</a>
                        <?php endif; ?>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>有效期: <?= $card['duration'] ?> 天</span>
                        <span>生成时间: <?= $card['create_time'] ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- 分页导航 -->
        <div class="flex justify-center mt-4">
            <ul class="flex space-x-2">
                <?php if ($page > 1): ?>
                <li>
                    <a href="?page=<?= $page - 1 ?>" class="px-3 py-1 border rounded-md text-gray-700 hover:bg-gray-200">上一页</a>
                </li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li>
                    <a href="?page=<?= $i ?>" class="px-3 py-1 border rounded-md text-gray-700 hover:bg-gray-200 <?= $i === $page ? 'bg-gray-200' : '' ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                <li>
                    <a href="?page=<?= $page + 1 ?>" class="px-3 py-1 border rounded-md text-gray-700 hover:bg-gray-200">下一页</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <?php
    // 包含底部文件
    include __DIR__ . '/includes/footer.php';
    ?>
</body>

</html>    