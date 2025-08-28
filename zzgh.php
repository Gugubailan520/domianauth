<?php
session_start();
require_once __DIR__ . '/config/db.php';
$pdo = require __DIR__ . '/config/db.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_auth'])) {
    $cardKey = strtoupper(str_replace('-', '', trim($_POST['card_key'])));
    $oldAuth = trim($_POST['old_auth']);
    $newAuth = trim($_POST['new_auth']);
    $bindIp = trim($_POST['bind_ip'] ?? '');
    
    // 处理旧授权标识（可能是域名或IP）
    if (filter_var($oldAuth, FILTER_VALIDATE_IP)) {
        $oldType = 'ip';
    } else {
        $oldAuth = str_replace(['http://', 'https://', 'www.'], '', $oldAuth);
        $oldAuth = explode('/', $oldAuth)[0];
        $oldType = 'domain';
    }
    
    // 处理新授权标识（可能是域名或IP）
    if (filter_var($newAuth, FILTER_VALIDATE_IP)) {
        $newType = 'ip';
    } else {
        $newAuth = str_replace(['http://', 'https://', 'www.'], '', $newAuth);
        $newAuth = explode('/', $newAuth)[0];
        $newType = 'domain';
    }
    
    // 验证IP格式（如果填写了）
    if (!empty($bindIp) && !filter_var($bindIp, FILTER_VALIDATE_IP)) {
        $_SESSION['message'] = '请输入有效的IP地址（留空则不限制IP）';
        $_SESSION['success'] = false;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    if (empty($cardKey)) {
        $_SESSION['message'] = '请输入卡密';
        $_SESSION['success'] = false;
    } elseif ($oldType === 'domain' && !preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/', $oldAuth)) {
        $_SESSION['message'] = '请输入有效的原授权标识（域名或IP）';
        $_SESSION['success'] = false;
    } elseif ($newType === 'domain' && !preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/', $newAuth)) {
        $_SESSION['message'] = '请输入有效的新授权标识（域名或IP）';
        $_SESSION['success'] = false;
    } else {
        try {
            $pdo->beginTransaction();
            
            // 检查卡密和原授权是否匹配
            $stmt = $pdo->prepare("SELECT * FROM card_keys WHERE card_key = ? AND (domain = ? OR bind_ip = ?) AND status = 1 FOR UPDATE");
            $stmt->execute([$cardKey, $oldAuth, $oldAuth]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$card) {
                $_SESSION['message'] = '卡密无效或与原授权不匹配';
                $_SESSION['success'] = false;
            } else {
                // 检查新授权是否已存在
                $authFile = __DIR__ . '/config/authorized_domains.php';
                $authData = file_exists($authFile) ? include $authFile : [];
                
                if (isset($authData[$newAuth])) {
                    $_SESSION['message'] = '新授权标识已被使用，请使用其他标识';
                    $_SESSION['success'] = false;
                } else {
                    // 获取原授权的授权信息
                    $authInfo = $authData[$oldAuth];
                    
                    // 更新IP绑定（如果填写了）
                    if (!empty($bindIp)) {
                        $authInfo['allowed_ips'] = [$bindIp];
                    }
                    
                    // 更新卡密表中的授权信息
                    if ($newType === 'domain') {
                        $stmt = $pdo->prepare("UPDATE card_keys SET domain = ?, bind_ip = NULL WHERE id = ?");
                    } else {
                        $stmt = $pdo->prepare("UPDATE card_keys SET bind_ip = ?, domain = NULL WHERE id = ?");
                    }
                    $stmt->execute([$newAuth, $card['id']]);
                    
                    // 从授权文件中移除原授权
                    unset($authData[$oldAuth]);
                    
                    // 添加新授权
                    $authData[$newAuth] = $authInfo;
                    $authData[$newAuth]['type'] = $newType;
                    
                    // 更新授权文件
                    $content = "<?php\nreturn [\n";
                    foreach ($authData as $auth => $info) {
                        $content .= "    '".addslashes($auth)."' => [\n";
                        $content .= "        'expires' => '".addslashes($info['expires'])."',\n";
                        $content .= "        'created_at' => '".addslashes($info['created_at'])."',\n";
                        $content .= "        'type' => '".addslashes($info['type'])."'";
                        
                        // 添加IP绑定信息（如果有）
                        if (!empty($info['allowed_ips'])) {
                            $content .= ",\n        'allowed_ips' => ['".addslashes($info['allowed_ips'][0])."']";
                        }
                        
                        $content .= "\n    ],\n";
                    }
                    $content .= "];\n";
                    
                    file_put_contents($authFile, $content);
                    
                    $pdo->commit();
                    
                    $ipMessage = !empty($bindIp) ? "，绑定IP: {$bindIp}" : "";
                    $_SESSION['message'] = "更换成功！新授权标识 {$newAuth} 已获得授权，有效期至 {$authInfo['expires']}{$ipMessage}";
                    $_SESSION['success'] = true;
                }
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['message'] = '更换失败: ' . $e->getMessage();
            $_SESSION['success'] = false;
        }
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$message = $_SESSION['message'] ?? '';
$success = $_SESSION['success'] ?? false;
unset($_SESSION['message'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>授权更换</title>
    <style>
        /* 保持原有样式不变 */
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
        
        .ip-input-group {
            display: flex;
            align-items: center;
        }
        
        .ip-input-group input {
            flex: 1;
            border-radius: 4px 0 0 4px;
        }
        
        .ip-input-group button {
            width: auto;
            padding: 12px 15px;
            border-radius: 0 4px 4px 0;
            background: #f1f8fe;
            color: var(--primary-color);
            border: 1px solid var(--border-color);
            border-left: none;
            cursor: pointer;
        }
        
        .ip-input-group button:hover {
            background: #e3f2fd;
            transform: none;
            box-shadow: none;
        }
        
        .ip-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
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
                授权更换
            </h2>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-<?= $success ? 'success' : 'danger' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <form method="post">
                    <div class="form-group">
                        <label for="card_key">授权卡密</label>
                        <input type="text" id="card_key" name="card_key" placeholder="请输入您的16位授权卡密" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="old_auth">原授权标识</label>
                        <input type="text" id="old_auth" name="old_auth" placeholder="请输入原域名或IP地址" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_auth">新授权标识</label>
                        <input type="text" id="new_auth" name="new_auth" placeholder="请输入新域名或IP地址" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="bind_ip">绑定服务器IP（可选）</label>
                        <div class="ip-input-group">
                            <input type="text" id="bind_ip" name="bind_ip" placeholder="留空则不限制IP" 
                                   value="<?= htmlspecialchars($_SERVER['REMOTE_ADDR']) ?>">
                            <button type="button" id="use_current_ip">使用当前IP</button>
                        </div>
                        <div class="ip-hint">如需更换IP，请输入IP地址</div>
                    </div>
                    
                    <button type="submit" name="change_auth">更换授权</button>
                </form>
            </div>
            
            <div class="instructions">
                <h3>更换说明</h3>
                <ul>
                    <li>请输入原卡密、原授权标识（域名或IP）和需要更换的新授权标识</li>
                    <li>更换后，原授权标识将立即失效</li>
                    <li>IP绑定为可选功能，留空则不限制访问IP</li>
                    <li>授权标识可以是域名或IP地址</li>
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
        document.getElementById('old_auth').addEventListener('input', function(e) {
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
        
        document.getElementById('new_auth').addEventListener('input', function(e) {
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
        
        // 使用当前IP
        document.getElementById('use_current_ip').addEventListener('click', function() {
            fetch('https://api.ipify.org?format=json')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('bind_ip').value = data.ip;
                })
                .catch(() => {
                    document.getElementById('bind_ip').value = '<?= $_SERVER['REMOTE_ADDR'] ?>';
                    alert('获取当前IP失败，已使用检测到的IP');
                });
        });
        
        // IP输入验证
        document.getElementById('bind_ip').addEventListener('input', function(e) {
            let value = e.target.value.trim();
            if (value && !value.match(/^(\d{1,3}\.){3}\d{1,3}$/)) {
                e.target.style.borderColor = 'var(--danger-color)';
            } else {
                e.target.style.borderColor = 'var(--border-color)';
            }
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