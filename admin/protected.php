<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    die('拒绝访问：请先登录');
}
$username = $_SESSION['username'];

function getAuthorizedDomainsCount() {
    $configFile = __DIR__.'/../config/authorized_domains.php';
    if (file_exists($configFile)) {
        $domains = include $configFile;
        return count(array_filter(array_keys($domains), function($domain) {
            return !empty(trim($domain));
        }));
    }
    return 0;
}
$authorizedDomainsCount = getAuthorizedDomainsCount();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>域名授权系统</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --surface: #ffffff;
            --background: #f8fafc;
            --border: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.03);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --rounded-sm: 0.25rem;
            --rounded: 0.375rem;
            --rounded-lg: 0.5rem;
            --transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--background);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.5;
        }

        /* 头部样式 - 更简约的设计 */
        .app-header {
            background: var(--surface);
            box-shadow: var(--shadow-sm);
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
            height: 64px;
            border-bottom: 1px solid var(--border);
        }

        .app-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1.125rem;
        }

        .app-brand i {
            color: var(--primary);
            font-size: 1.25rem;
        }

        .mobile-menu-button {
            background: none;
            border: none;
            padding: 0.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 1.25rem;
            display: none;
            margin-right: 0.5rem;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            font-size: 1rem;
        }

        /* 主容器 */
        .app-container {
            display: flex;
            flex: 1;
            position: relative;
        }

        /* 侧边栏 - 更简约的设计 */
        .app-sidebar {
            background: var(--surface);
            border-right: 1px solid var(--border);
            width: 240px;
            height: calc(100vh - 64px);
            position: sticky;
            top: 64px;
            overflow-y: auto;
            padding: 1.5rem 0;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .nav-section {
            margin-bottom: 1.75rem;
        }

        .nav-section-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9375rem;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
            border-radius: var(--rounded-sm);
            margin: 0 0.5rem;
        }

        .nav-item:hover {
            color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
        }

        .nav-item.active {
            color: var(--primary);
            background: rgba(37, 99, 235, 0.1);
            font-weight: 600;
        }

        .nav-item i {
            width: 1.25rem;
            text-align: center;
            font-size: 1rem;
            opacity: 0.8;
        }

        .nav-item.active i {
            opacity: 1;
        }

        /* 主要内容 - 更简约大气的设计 */
        .app-main {
            flex: 1;
            padding: 2rem;
            min-height: calc(100vh - 64px);
            overflow-x: hidden;
            background: var(--background);
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .page-description {
            color: var(--text-secondary);
            font-size: 1rem;
            max-width: 800px;
        }

        /* 统计卡片 - 更简约的设计 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--rounded);
            padding: 1.5rem;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            border-top: 3px solid var(--primary);
        }

        .stat-card:hover {
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* iframe容器 */
        #content-iframe {
            width: 100%;
            height: calc(100vh - 64px - 4rem);
            border: none;
            border-radius: var(--rounded);
            background: var(--surface);
            box-shadow: var(--shadow-sm);
        }

        /* 工具类 */
        .hidden {
            display: none;
        }

        /* 响应式设计 */
        @media (max-width: 1024px) {
            .app-sidebar {
                position: fixed;
                top: 64px;
                left: -240px;
                width: 240px;
                height: calc(100vh - 64px);
                z-index: 40;
                box-shadow: var(--shadow-md);
            }

            .app-sidebar.active {
                left: 0;
            }

            .mobile-menu-button {
                display: block;
            }

            .app-main {
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .app-header {
                padding: 0 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .app-main {
                padding: 1rem;
            }

            .stat-card {
                padding: 1.25rem;
            }

            .stat-value {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div style="display: flex; align-items: center;">
            <button class="mobile-menu-button" id="mobileMenuButton">
                <i class="fas fa-bars"></i>
            </button>
            <div class="app-brand">
                <i class="fas fa-lock"></i>
                <span>域名授权系统</span>
            </div>
        </div>
        
        <div class="user-menu">
            <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
        </div>
    </header>

    <div class="app-container">
        <aside class="app-sidebar" id="appSidebar">
            <nav class="nav-section">
                <div class="nav-section-title">导航</div>
                <div class="nav-menu">
                    <a href="#" class="nav-item active" data-page="home">
                        <i class="fas fa-chart-pie"></i>
                        <span>仪表盘</span>
                    </a>
                    <a href="#" class="nav-item" data-page="profile">
                        <i class="fas fa-user"></i>
                        <span>个人中心</span>
                    </a>
                </div>
            </nav>
            
            <nav class="nav-section">
                <div class="nav-section-title">授权管理</div>
                <div class="nav-menu">
                    <a href="#" class="nav-item" data-page="auth_manage">
                        <i class="fas fa-sliders-h"></i>
                        <span>授权设置</span>
                    </a>
                    <a href="#" class="nav-item" data-page="dbrk">
                        <i class="fas fa-shield-alt"></i>
                        <span>盗版入库</span>
                    </a>
                    <a href="#" class="nav-item" data-page="km">
                        <i class="fas fa-key"></i>
                        <span>卡密管理</span>
                    </a>
                </div>
            </nav>
            
            <nav class="nav-section">
                <div class="nav-section-title">系统</div>
                <div class="nav-menu">
                    <a href="#" class="nav-item" data-page="proxy">
                        <i class="fas fa-users"></i>
                        <span>代理管理</span>
                    </a>
                    <a href="logout.php" class="nav-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>退出登录</span>
                    </a>
                </div>
            </nav>
        </aside>

        <main class="app-main">
            <div id="home-content">
                <div class="page-header">
                    <h1 class="page-title">欢迎回来，<?php echo htmlspecialchars($username); ?></h1>
                    <p class="page-description">以下是系统当前状态概览</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $authorizedDomainsCount; ?></div>
                        <div class="stat-label">已授权域名</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo date('Y-m-d'); ?></div>
                        <div class="stat-label">当前日期</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="live-clock"><?php echo date('H:i:s'); ?></div>
                        <div class="stat-label">实时时间</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo phpversion(); ?></div>
                        <div class="stat-label">PHP版本</div>
                    </div>
                </div>
            </div>
            <iframe id="content-iframe" class="hidden"></iframe>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const mobileMenuButton = document.getElementById('mobileMenuButton');
            const appSidebar = document.getElementById('appSidebar');
            const navItems = document.querySelectorAll('.nav-item[data-page]');
            const iframe = document.getElementById('content-iframe');
            const homeContent = document.getElementById('home-content');

            // 移动菜单切换
            mobileMenuButton.addEventListener('click', (e) => {
                e.stopPropagation();
                appSidebar.classList.toggle('active');
            });

            // 导航菜单点击
            navItems.forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    // 更新活动状态
                    document.querySelectorAll('.nav-item.active').forEach(el => {
                        el.classList.remove('active');
                    });
                    item.classList.add('active');
                    
                    const page = item.dataset.page;
                    if (page === 'home') {
                        homeContent.classList.remove('hidden');
                        iframe.classList.add('hidden');
                    } else {
                        homeContent.classList.add('hidden');
                        iframe.classList.remove('hidden');
                        iframe.src = `${page}.php`;
                    }

                    // 移动端自动关闭侧边栏
                    if (window.innerWidth < 1024) {
                        appSidebar.classList.remove('active');
                    }
                });
            });

            // 实时时钟
            function updateClock() {
                const now = new Date();
                const hours = now.getHours().toString().padStart(2, '0');
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const seconds = now.getSeconds().toString().padStart(2, '0');
                document.getElementById('live-clock').textContent = `${hours}:${minutes}:${seconds}`;
            }
            setInterval(updateClock, 1000);

            // 点击外部关闭侧边栏
            document.addEventListener('click', (e) => {
                if (window.innerWidth >= 1024) return;
                if (!appSidebar.contains(e.target) && !mobileMenuButton.contains(e.target)) {
                    appSidebar.classList.remove('active');
                }
            });

            // 窗口大小变化时调整布局
            window.addEventListener('resize', () => {
                if (window.innerWidth >= 1024) {
                    appSidebar.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>