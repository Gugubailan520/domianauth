<?php
session_start();
require_once __DIR__ . '/config/db.php';
$pdo = require __DIR__ . '/config/db.php';

$message = '';
$success = false;
$showDownloadLink = false;

// 定义ZIP文件路径
define('DOWNLOAD_FILE', __DIR__ . '/downloads/package.zip');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_auth'])) {
    $cardKey = strtoupper(str_replace('-', '', trim($_POST['card_key'])));
    $authIdentifier = trim($_POST['auth_identifier']);
    
    // 处理授权标识（可能是域名或IP）
    if (filter_var($authIdentifier, FILTER_VALIDATE_IP)) {
        $authType = 'ip';
    } else {
        $authIdentifier = str_replace(['http://', 'https://', 'www.'], '', $authIdentifier);
        $authIdentifier = explode('/', $authIdentifier)[0];
        $authType = 'domain';
    }
    
    if (empty($cardKey)) {
        $_SESSION['message'] = '请输入卡密';
        $_SESSION['success'] = false;
    } elseif ($authType === 'domain' && !preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/', $authIdentifier)) {
        $_SESSION['message'] = '请输入有效的域名或IP地址';
        $_SESSION['success'] = false;
    } else {
        try {
            $pdo->beginTransaction();
            
            // 检查卡密和授权标识是否匹配且有效
            $stmt = $pdo->prepare("SELECT * FROM card_keys WHERE card_key = ? AND (domain = ? OR bind_ip = ?) AND status = 1");
            $stmt->execute([$cardKey, $authIdentifier, $authIdentifier]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$card) {
                $_SESSION['message'] = '卡密无效或与授权标识不匹配，或已过期';
                $_SESSION['success'] = false;
            } else {
                // 检查授权文件中是否存在该授权标识
                $authFile = __DIR__ . '/config/authorized_domains.php';
                $authData = file_exists($authFile) ? include $authFile : [];
                
                if (!isset($authData[$authIdentifier])) {
                    $_SESSION['message'] = '该授权标识未获得授权';
                    $_SESSION['success'] = false;
                } else {
                    // 验证成功，生成下载链接
                    $downloadToken = bin2hex(random_bytes(16));
                    $_SESSION['download_token'] = $downloadToken;
                    $_SESSION['download_expire'] = time() + 3600; // 1小时有效
                    
                    $pdo->commit();
                    $_SESSION['message'] = "验证成功！您可以下载文件";
                    $_SESSION['success'] = true;
                    $_SESSION['download_link'] = true;
                }
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['message'] = '验证失败: ' . $e->getMessage();
            $_SESSION['success'] = false;
        }
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 处理下载请求
if (isset($_GET['download']) && $_GET['download'] === '1') {
    if (isset($_SESSION['download_token'], $_SESSION['download_expire']) && 
        $_SESSION['download_expire'] > time()) {
        
        if (file_exists(DOWNLOAD_FILE)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="'.basename(DOWNLOAD_FILE).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize(DOWNLOAD_FILE));
            readfile(DOWNLOAD_FILE);
            
            // 清除下载token
            unset($_SESSION['download_token'], $_SESSION['download_expire']);
            exit;
        } else {
            $message = '下载文件不存在';
            $success = false;
        }
    } else {
        $message = '下载链接已过期或无效';
        $success = false;
    }
}

$message = $_SESSION['message'] ?? '';
$success = $_SESSION['success'] ?? false;
$showDownloadLink = $_SESSION['download_link'] ?? false;
unset($_SESSION['message'], $_SESSION['success'], $_SESSION['download_link']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>授权验证系统 </title>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --light-color: #ffffff;
            --bg-color: #f8f9fa;
            --text-color: #333333;
            --border-color: #e0e0e0;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Helvetica Neue', Arial, 'Microsoft YaHei', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .container {
            max-width: 800px;
            margin: 40px auto;
            background: var(--light-color);
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: 25px;
            text-align: center;
            color: white;
        }
        
        .header h2 {
            font-size: 26px;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .card {
            background: var(--light-color);
            border-radius: 6px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
            font-size: 14px;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            background: var(--light-color);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: var(--text-color);
            font-size: 15px;
            transition: all 0.3s;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .input-group {
            display: flex;
            align-items: center;
        }
        
        .input-group-text {
            padding: 12px 15px;
            background: #f1f8fe;
            border: 1px solid var(--border-color);
            border-right: none;
            border-radius: 4px 0 0 4px;
            color: var(--primary-color);
            font-size: 14px;
        }
        
        .input-group input {
            border-radius: 0 4px 4px 0;
        }
        
        button {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 14px;
            width: 100%;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        button:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(52, 152, 219, 0.3);
        }
        
        .btn-download {
            background: linear-gradient(135deg, var(--success-color), #27ae60);
            display: block;
            text-align: center;
            text-decoration: none;
            padding: 14px;
            margin-top: 20px;
            border-radius: 4px;
            color: white;
            font-weight: 500;
        }
        
        .btn-download:hover {
            background: linear-gradient(135deg, #27ae60, var(--success-color));
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(46, 204, 113, 0.3);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 4px;
            font-size: 14px;
            display: flex;
            align-items: center;
            border: 1px solid transparent;
        }
        
        .alert-success {
            background-color: #e8f8f0;
            color: var(--success-color);
            border-color: #d4edda;
        }
        
        .alert-danger {
            background-color: #fcebea;
            color: var(--danger-color);
            border-color: #f5c6cb;
        }
        
        .alert::before {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            margin-right: 10px;
            background-size: contain;
            background-repeat: no-repeat;
        }
        
        .alert-success::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%232ecc71'%3E%3Cpath d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z'/%3E%3C/svg%3E");
        }
        
        .alert-danger::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23e74c3c'%3E%3Cpath d='M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z'/%3E%3C/svg%3E");
        }
        
        .instructions {
            margin-top: 30px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }
        
        .instructions h3 {
            font-size: 16px;
            margin-bottom: 15px;
            color: var(--primary-color);
            position: relative;
            padding-left: 25px;
        }
        
        .instructions h3::before {
            content: '';
            position: absolute;
            left: 0;
            top: 2px;
            width: 18px;
            height: 18px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%233498db'%3E%3Cpath d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'/%3E%3C/svg%3E");
            background-size: contain;
        }
        
        .instructions ul {
            list-style: none;
            padding-left: 0;
        }
        
        .instructions li {
            position: relative;
            margin-bottom: 10px;
            padding-left: 25px;
            font-size: 14px;
            line-height: 1.6;
            color: #555;
        }
        
        .instructions li::before {
            content: '';
            position: absolute;
            left: 5px;
            top: 8px;
            width: 6px;
            height: 6px;
            background-color: var(--primary-color);
            border-radius: 50%;
        }
        
        .footer {
            text-align: center;
            padding: 15px;
            font-size: 12px;
            color: #95a5a6;
            border-top: 1px solid var(--border-color);
        }
        
        .logo {
            display: inline-block;
            width: 30px;
            height: 30px;
            margin-right: 10px;
            vertical-align: middle;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ffffff'%3E%3Cpath d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z'/%3E%3C/svg%3E");
            background-size: contain;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 20px;
                border-radius: 6px;
            }
            
            .content {
                padding: 20px;
            }
            
            .header h2 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>
                <span class="logo"></span>
                文件下载
            </h2>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-<?= $success ? 'success' : 'danger' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <?php if (!$showDownloadLink): ?>
                <form method="post">
                    <div class="form-group">
                        <label for="card_key">授权卡密</label>
                        <input type="text" id="card_key" name="card_key" placeholder="请输入您的卡密" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="auth_identifier">授权标识（域名或IP）</label>
                        <div class="input-group">
                            <span class="input-group-text">https://</span>
                            <input type="text" id="auth_identifier" name="auth_identifier" placeholder="输入域名或IP地址" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="verify_auth">立即验证</button>
                </form>
                <?php else: ?>
                    <div class="form-group">
                        <h3 style="margin-bottom: 15px; color: var(--success-color);">验证成功！</h3>
                        <p style="margin-bottom: 20px; color: #555;">您的授权信息已验证通过，现在可以下载授权文件。</p>
                        <a href="http://inhuaym.cn" class="btn-download">立即下载授权文件</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="instructions">
                <h3>使用说明</h3>
                <ul>
                    <li>请输入卡密和授权标识（域名或IP）进行验证</li>
                    <li>授权标识可以是域名或IP地址</li>
                    <li>验证通过后，您有1小时时间下载文件</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // 卡密输入格式化
        document.getElementById('card_key').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^a-zA-Z0-9]/g, '');
            if (value.length > 16) value = value.substr(0, 16);
            
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) formatted += '-';
                formatted += value[i];
            }
            
            e.target.value = formatted.toUpperCase();
        });
        
        // 授权标识输入处理
        document.getElementById('auth_identifier').addEventListener('input', function(e) {
            let value = e.target.value;
            // 如果是域名，则处理格式
            if (!value.match(/^(\d{1,3}\.){3}\d{1,3}$/)) {
                value = value
                    .replace(/^https?:\/\//, '')
                    .replace(/^www\./, '')
                    .replace(/\/.*$/, '')
                    .toLowerCase();
            }
            e.target.value = value;
        });
        
        // 添加按钮点击效果
        document.querySelector('button[type="submit"]').addEventListener('click', function() {
            this.style.transform = 'translateY(1px)';
            setTimeout(() => {
                this.style.transform = 'translateY(-1px)';
            }, 100);
        });
    </script>
</body>
</html>