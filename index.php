<?php
session_start();

// 从配置文件读取授权域名列表
$config_file = __DIR__ . '/config/authorized_domains.php';

// 检查配置文件是否存在
if (!file_exists($config_file)) {
    die('<div class="error-box"><i class="fa fa-exclamation-triangle"></i> 错误：授权配置文件不存在</div>');
}

// 包含配置文件获取域名列表
$authorized_domains = include $config_file;

// 处理查询请求
$search_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['domain'])) {
    $domain = trim($_POST['domain']);
    $domain = strtolower($domain);
    
    // 移除协议和www前缀
    $domain = str_replace(['http://', 'https://', 'www.'], '', $domain);
    
    // 提取主域名
    $domain = explode('/', $domain)[0];
    
    // 更严格的域名验证
    if (!preg_match('/^(?!\-)(?:(?:[a-z0-9][a-z0-9\-]{0,61}[a-z0-9])\.)+(?:[a-z]{2,63})$/i', $domain)) {
        $search_result = [
            'error' => '域名格式不正确',
            'domain' => $domain,
            'icon' => 'exclamation-triangle',
            'color' => 'orange'
        ];
    } else {
        // 检查域名是否授权
        $is_authorized = array_key_exists($domain, $authorized_domains);
        $expiry_info = '';
        
        if ($is_authorized) {
            $expires = $authorized_domains[$domain]['expires'];
            if ($expires === 'permanent') {
                $expiry_info = '（永久有效）';
            } else {
                $expiry_date = strtotime($expires);
                $current_date = time();
                if ($expiry_date < $current_date) {
                    $expiry_info = '（已过期）';
                    $is_authorized = false; // 过期视为未授权
                } else {
                    $expiry_info = '（有效期至 '.date('Y-m-d', $expiry_date).'）';
                }
            }
        }
        
        $search_result = [
            'domain' => $domain,
            'status' => $is_authorized ? '已授权' : '未授权',
            'message' => $is_authorized 
                ? '该域名在授权列表中'.$expiry_info 
                : '该域名未在授权列表中',
            'icon' => $is_authorized ? 'check-circle' : 'times-circle',
            'color' => $is_authorized ? 'green' : 'red',
            'expires' => $is_authorized ? $expires : null
        ];
    }
    
    // 存储结果到SESSION
    $_SESSION['search_result'] = $search_result;
    
    // 重定向到当前页面，避免刷新时重新提交表单
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// 从SESSION获取结果（如果存在）
if (isset($_SESSION['search_result'])) {
    $search_result = $_SESSION['search_result'];
    unset($_SESSION['search_result']);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>域名授权查询 - 授权中心</title>
    <meta name="description" content="域名授权查询系统">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
  
    <style>
        :root {
            --primary-color: #3a86ff;
            --primary-dark: #2667cc;
            --secondary-color: #8338ec;
            --success-color: #06d6a0;
            --danger-color: #ef476f;
            --warning-color: #ffd166;
            --dark-color: #1a1a2e;
            --light-bg: #f8f9fa;
            --gradient: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', 'Microsoft YaHei', sans-serif;
        }
        
        body {
            background-color: #f5f7ff;
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            background-image: url('https://example.com/shark-bg-pattern.png');
            background-attachment: fixed;
            background-size: cover;
            background-position: center;
        }
        
        .container {
            max-width: 900px;
            margin: 30px auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        header {
            text-align: center;
            padding: 40px 20px;
            background: var(--gradient);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        header::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
            animation: shine 8s infinite linear;
        }
        
        @keyframes shine {
            0% { transform: rotate(30deg) translate(-10%, -10%); }
            100% { transform: rotate(30deg) translate(10%, 10%); }
        }
        
        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .logo-icon {
            font-size: 2.5rem;
            margin-right: 15px;
            color: white;
            text-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .logo-text {
            font-size: 2rem;
            font-weight: 700;
            text-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .tagline {
            font-size: 1rem;
            opacity: 0.9;
            margin-top: 10px;
            font-weight: 300;
        }
        
        .content {
            padding: 40px;
        }
        
        .search-form {
            margin-bottom: 40px;
            position: relative;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-control {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
            background-color: rgba(255,255,255,0.8);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(58, 134, 255, 0.2);
            outline: none;
            background-color: white;
        }
        
        .input-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
        }
        
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: var(--gradient);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s;
            text-align: center;
            box-shadow: 0 4px 15px rgba(58, 134, 255, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(255,255,255,0.2), rgba(255,255,255,0));
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(58, 134, 255, 0.4);
        }
        
        .btn:hover::after {
            opacity: 1;
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .result-container {
            margin-top: 40px;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .result-box {
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            border-left: 5px solid;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        
        .result-box:hover {
            transform: translateY(-5px);
        }
        
        .success-box {
            border-left-color: var(--success-color);
            background: linear-gradient(to right, rgba(6, 214, 160, 0.05), white);
        }
        
        .danger-box {
            border-left-color: var(--danger-color);
            background: linear-gradient(to right, rgba(239, 71, 111, 0.05), white);
        }
        
        .warning-box {
            border-left-color: var(--warning-color);
            background: linear-gradient(to right, rgba(255, 209, 102, 0.05), white);
        }
        
        .result-icon {
            font-size: 2.5rem;
            margin-right: 25px;
            flex-shrink: 0;
            min-width: 60px;
            text-align: center;
        }
        
        .result-content {
            flex-grow: 1;
        }
        
        .result-content h3 {
            margin-bottom: 10px;
            font-size: 1.3rem;
            color: var(--dark-color);
            display: flex;
            align-items: center;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
            background-color: var(--color);
            color: white;
        }
        
        .result-content p {
            margin-bottom: 8px;
            color: #555;
        }
        
        .domain-name {
            font-weight: bold;
            word-break: break-all;
            color: var(--dark-color);
            font-size: 1.1rem;
            display: inline-block;
            background: rgba(0,0,0,0.05);
            padding: 3px 10px;
            border-radius: 5px;
        }
        
        .nav-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 40px;
        }
        
        .nav-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-top: 4px solid var(--primary-color);
        }
        
        .nav-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .nav-card i {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 15px;
            display: block;
        }
        
        .nav-card h3 {
            margin-bottom: 15px;
            color: var(--dark-color);
        }
        
        .nav-card p {
            color: #666;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .nav-card .btn {
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        
        footer {
            text-align: center;
            padding: 25px;
            color: #666;
            font-size: 0.9rem;
            border-top: 1px solid rgba(0,0,0,0.05);
            background: rgba(255,255,255,0.7);
        }
        
        .social-links {
            display: flex;
            justify-content: center;
            margin-top: 15px;
        }
        
        .social-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(0,0,0,0.05);
            color: var(--dark-color);
            margin: 0 10px;
            transition: all 0.3s;
        }
        
        .social-link:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-3px);
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 15px;
                border-radius: 10px;
            }
            
            .content {
                padding: 25px;
            }
            
            .logo {
                flex-direction: column;
            }
            
            .logo-icon {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .nav-cards {
                grid-template-columns: 1fr;
            }
            
            .result-box {
                flex-direction: column;
                text-align: center;
            }
            
            .result-icon {
                margin-right: 0;
                margin-bottom: 20px;
            }
        }
        
        /* 鲨鱼主题元素 */
        .shark-wave {
            height: 15px;
            width: 100%;
            background: url('data:image/svg+xml;utf8,<svg viewBox="0 0 1200 120" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" fill="%233a86ff" opacity=".25"/><path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" fill="%233a86ff" opacity=".5"/><path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z" fill="%233a86ff"/></svg>');
            background-size: cover;
            margin-top: -1px;
        }
        
        .shark-icon {
            position: absolute;
            right: 30px;
            top: 20px;
            font-size: 3rem;
            opacity: 0.2;
            transform: rotate(30deg);
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(30deg); }
            50% { transform: translateY(-20px) rotate(35deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="shark-icon">
                <i class="fas fa-fish"></i>
            </div>
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="logo-text">授权中心</div>
            </div>
       
        </header>
        <div class="shark-wave"></div>
        
        <div class="content">
            <form class="search-form" method="post">
                <div class="form-group">
                    <input type="text" class="form-control" name="domain" placeholder="输入需要查询的域名" required>
                    <i class="fas fa-search input-icon"></i>
                </div>
                <button type="submit" class="btn btn-block">
                    <i class="fas fa-search"></i> 查询授权状态
                </button>
            </form>
            
            <?php if ($search_result): ?>
                <div class="result-container">
                    <div class="result-box <?php 
                        echo isset($search_result['error']) ? 'warning-box' : 
                            ($search_result['status'] === '已授权' ? 'success-box' : 'danger-box'); 
                    ?>">
                        <i class="fas fa-<?php echo $search_result['icon']; ?> result-icon" 
                           style="color: <?php echo $search_result['color']; ?>"></i>
                        <div class="result-content">
                            <h3>
                                <?php if (isset($search_result['error'])): ?>
                                    查询错误
                                <?php else: ?>
                                    查询结果: <span class="status-badge" style="--color: <?php echo $search_result['color']; ?>"><?php echo $search_result['status']; ?></span>
                                <?php endif; ?>
                            </h3>
                            <p><span class="domain-name"><?php echo htmlspecialchars($search_result['domain']); ?></span></p>
                            <?php if (isset($search_result['error'])): ?>
                                <p><?php echo htmlspecialchars($search_result['error']); ?></p>
                            <?php else: ?>
                                <p><?php echo $search_result['message']; ?></p>
                                <?php if (isset($search_result['expires']) && $search_result['expires'] !== 'permanent'): ?>
                                    <p><small>剩余有效期: <?php echo ceil((strtotime($search_result['expires']) - time()) / 86400); ?>天</small></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="nav-cards">
                <div class="nav-card">
                    <i class="fas fa-key"></i>
                    <h3>兑换授权</h3>
                    <p>使用授权码兑换新的域名授权</p>
                    <a href="redeem.php" class="btn">立即兑换</a>
                </div>
                
                <div class="nav-card">
                    <i class="fas fa-user-shield"></i>
                    <h3>程序下载</h3>
                    <p>程序验证下载包</p>
                    <a href="cxxz.php" class="btn">立即下载</a>
                </div>
                
                <div class="nav-card">
                    <i class="fas fa-exchange-alt"></i>
                    <h3>更换授权</h3>
                    <p>将授权从旧域名迁移到新域名</p>
                    <a href="zzgh.php" class="btn">更换授权</a>
                </div>
                
                
            </div>
        </div>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> 授权系统</p>
           
        </footer>
    </div>
    
    <script>
        // 表单提交后禁用按钮防止重复提交
        document.querySelector('form').addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 查询中...';
        });
        
        // 自动聚焦到输入框
        document.querySelector('input[name="domain"]').focus();
        
        // 添加波浪动画
        const wave = document.querySelector('.shark-wave');
        let offset = 0;
        function animateWave() {
            offset += 0.5;
            wave.style.backgroundPositionX = -offset + 'px';
            requestAnimationFrame(animateWave);
        }
        animateWave();
    </script>
</body>
</html>