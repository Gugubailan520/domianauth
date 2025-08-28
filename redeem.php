<?php
session_start();
require_once __DIR__ . '/config/db.php';
$pdo = require __DIR__ . '/config/db.php';

// 初始化授权文件目录
if (!file_exists(__DIR__ . '/config')) {
    mkdir(__DIR__ . '/config', 0755, true);
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem'])) {
    $cardKey = strtoupper(str_replace('-', '', trim($_POST['card_key'])));
    $domain = trim($_POST['domain']);
    $bind_ip = trim($_POST['bind_ip'] ?? '');
    
    // 处理域名输入（如果填写了域名）
    if (!empty($domain)) {
        $domain = str_replace(['http://', 'https://', 'www.'], '', $domain);
        $domain = explode('/', $domain)[0];
        
        if (!preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/', $domain)) {
            $_SESSION['message'] = '请输入有效的域名';
            $_SESSION['success'] = false;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    
    // 如果填写了IP则验证格式，否则留空表示不限制IP
    if (!empty($bind_ip) && !filter_var($bind_ip, FILTER_VALIDATE_IP)) {
        $_SESSION['message'] = '请输入有效的IP地址或留空不限制';
        $_SESSION['success'] = false;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    if (empty($cardKey)) {
        $_SESSION['message'] = '请输入卡密';
        $_SESSION['success'] = false;
    } elseif (empty($domain) && empty($bind_ip)) {
        $_SESSION['message'] = '必须填写域名或IP至少一项';
        $_SESSION['success'] = false;
    } else {
        try {
            $pdo->beginTransaction();
            
            // 检查卡密是否存在且未使用
            $stmt = $pdo->prepare("SELECT * FROM card_keys WHERE card_key = ? AND status = 0 FOR UPDATE");
            $stmt->execute([$cardKey]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$card) {
                $_SESSION['message'] = '卡密无效或已被使用';
                $_SESSION['success'] = false;
            } else {
                // 计算有效期
                $expiresDate = date('Y-m-d', strtotime("+{$card['duration']} days"));
                
                // 生成授权标识（使用域名或直接使用IP）
                $authIdentifier = !empty($domain) ? $domain : $bind_ip;
                
                // 更新卡密状态
                $stmt = $pdo->prepare("UPDATE card_keys SET 
                    status = 1, 
                    use_time = NOW(), 
                    user_ip = ?, 
                    domain = ?, 
                    bind_ip = ?
                    WHERE id = ?");
                $stmt->execute([
                    $_SERVER['REMOTE_ADDR'], 
                    $domain, 
                    $bind_ip,
                    $card['id']
                ]);
                
                // 更新授权文件
                $authFile = __DIR__ . '/config/authorized_domains.php';
                $domains = file_exists($authFile) ? include $authFile : [];
                
                $domains[$authIdentifier] = [
                    'expires' => $expiresDate,
                    'created_at' => date('Y-m-d'),
                    'allowed_ips' => !empty($bind_ip) ? [$bind_ip] : [],
                    'bind_at' => date('Y-m-d H:i:s'),
                    'type' => !empty($domain) ? 'domain' : 'ip'
                ];
                
                $content = "<?php\nreturn [\n";
                foreach ($domains as $d => $info) {
                    $content .= "    '".addslashes($d)."' => [\n";
                    $content .= "        'expires' => '".addslashes($info['expires'])."',\n";
                    $content .= "        'created_at' => '".addslashes($info['created_at'])."',\n";
                    $content .= "        'allowed_ips' => [";
                    if (!empty($info['allowed_ips'])) {
                        $content .= "'".addslashes($info['allowed_ips'][0])."'";
                    }
                    $content .= "],\n";
                    $content .= "        'bind_at' => '".addslashes($info['bind_at'])."',\n";
                    $content .= "        'type' => '".addslashes($info['type'])."'\n";
                    $content .= "    ],\n";
                }
                $content .= "];\n";
                
                file_put_contents($authFile, $content);
                
                $pdo->commit();
                $_SESSION['message'] = "兑换成功！";
                if (!empty($domain)) {
                    $_SESSION['message'] .= "域名 {$domain} 已获得授权";
                }
                if (!empty($bind_ip)) {
                    $_SESSION['message'] .= (!empty($domain) ? "，" : "") . "绑定IP: {$bind_ip}";
                }
                $_SESSION['message'] .= "，有效期至 {$expiresDate}";
                $_SESSION['success'] = true;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['message'] = '兑换失败: ' . $e->getMessage();
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
    <title>卡密兑换</title>
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
                卡密兑换
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
                        <input type="text" id="card_key" name="card_key" placeholder="请输入您的卡密" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="domain">授权域名（可选）</label>
                        <div class="input-group">
                            <span class="input-group-text">https://</span>
                            <input type="text" id="domain" name="domain" placeholder="">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="bind_ip">绑定服务器IP（可选）</label>
                        <div class="ip-input-group">
                            <input type="text" id="bind_ip" name="bind_ip" placeholder="留空则不限制IP" 
                                   value="<?= htmlspecialchars($_SERVER['REMOTE_ADDR']) ?>">
                            <button type="button" id="use_current_ip">使用当前IP</button>
                        </div>
                        <div class="ip-hint">必须填写域名或IP至少一项</div>
                    </div>
                    
                    <button type="submit" name="redeem">兑换授权</button>
                </form>
            </div>
            
            <div class="instructions">
                <h3>兑换说明</h3>
                <ul>
                    <li>请输入卡密和需要授权的域名或IP</li>
                    <li>卡密区分大小写，请准确输入</li>
                    <li>每个卡密只能授权一个域名或IP</li>
                    <li>必须填写域名或IP至少一项</li>
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
        
        // 域名输入处理
        document.getElementById('domain').addEventListener('input', function(e) {
            let value = e.target.value
                .replace(/^https?:\/\//, '')
                .replace(/^www\./, '')
                .replace(/\/.*$/, '')
                .toLowerCase();
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
            if (value && !value.match(/^(\d{1,3}\.){3}\d{1,3}$/) && !value.match(/^([a-f0-9:]+:+)+[a-f0-9]+$/)) {
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