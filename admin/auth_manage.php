<?php
// 开启会话
session_start();

// 检查登录状态
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    die('拒绝访问：请先登录');
}

// 配置路径
define('CONFIG_FILE', __DIR__.'/../config/authorized_domains.php');

// 获取当前授权域名列表
function getAuthorizedDomains() {
    if (file_exists(CONFIG_FILE)) {
        return include CONFIG_FILE;
    }
    return [];
}

// 保存域名列表
function saveAuthorizedDomains($domains) {
    $content = "<?php\nreturn [\n";
    foreach ($domains as $domain => $info) {
        $content .= "    '".addslashes($domain)."' => ['expires' => '".addslashes($info['expires'])."'],\n";
    }
    $content .= "];\n";
    
    return file_put_contents(CONFIG_FILE, $content) !== false;
}

// 处理表单提交
$message = null;
$messageType = 'success';
$currentDomains = getAuthorizedDomains();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 处理AJAX请求
    if (isset($_POST['action'])) {
        header('Content-Type: application/json');
        try {
            switch ($_POST['action']) {
                case 'add-domain':
                    $newDomain = strtolower(trim(str_replace('www.', '', $_POST['domain'])));
                    if (empty($newDomain)) {
                        throw new Exception('域名不能为空');
                    }
                    
                    $expires = isset($_POST['expires']) ? trim($_POST['expires']) : 'permanent';
                    if ($expires !== 'permanent' && !strtotime($expires)) {
                        throw new Exception('无效的过期日期格式');
                    }
                    
                    if (array_key_exists($newDomain, $currentDomains)) {
                        throw new Exception('域名已存在');
                    }
                    
                    $currentDomains[$newDomain] = ['expires' => $expires];
                    if (!saveAuthorizedDomains($currentDomains)) {
                        throw new Exception('保存失败');
                    }
                    
                    echo json_encode([
                        'status' => 'success', 
                        'domain' => $newDomain,
                        'expires' => $expires,
                        'count' => count($currentDomains)
                    ]);
                    exit;
                    
                case 'delete-domain':
                    $domainToDelete = strtolower(trim(str_replace('www.', '', $_POST['domain'])));
                    if (!array_key_exists($domainToDelete, $currentDomains)) {
                        throw new Exception('域名不存在');
                    }
                    
                    unset($currentDomains[$domainToDelete]);
                    if (!saveAuthorizedDomains($currentDomains)) {
                        throw new Exception('删除失败');
                    }
                    
                    echo json_encode([
                        'status' => 'success',
                        'count' => count($currentDomains)
                    ]);
                    exit;
                    
                case 'update-expiry':
                    $domain = strtolower(trim(str_replace('www.', '', $_POST['domain'])));
                    if (!array_key_exists($domain, $currentDomains)) {
                        throw new Exception('域名不存在');
                    }
                    
                    $expires = isset($_POST['expires']) ? trim($_POST['expires']) : 'permanent';
                    if ($expires !== 'permanent' && !strtotime($expires)) {
                        throw new Exception('无效的过期日期格式');
                    }
                    
                    $currentDomains[$domain]['expires'] = $expires;
                    if (!saveAuthorizedDomains($currentDomains)) {
                        throw new Exception('更新失败');
                    }
                    
                    echo json_encode([
                        'status' => 'success',
                        'domain' => $domain,
                        'expires' => $expires
                    ]);
                    exit;
                    
                default:
                    throw new Exception('无效的操作');
            }
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>授权域名管理</title>
    <style>
        :root {
            --primary-color: #4361ee;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --light-bg: #f8f9fa;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            line-height: 1.6;
            background-color: #f0f2f5;
            color: #333;
            padding: 15px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        h1, h2 {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        h1 {
            font-size: 1.5rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-top: 0;
        }
        
        h2 {
            font-size: 1.2rem;
            margin: 15px 0 10px;
        }
        
        .message {
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }
        
        .message.success {
            background-color: rgba(76, 201, 240, 0.1);
            color: #0e7490;
            border-left: 4px solid var(--success-color);
        }
        
        .message.error {
            background-color: rgba(247, 37, 133, 0.1);
            color: #be123c;
            border-left: 4px solid var(--danger-color);
        }
        
        .message i {
            margin-right: 8px;
            font-size: 1rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 15px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #3a56d4;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #e11d74;
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #e07f0e;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        .btn i {
            margin-right: 5px;
            font-size: 0.9rem;
        }
        
        .domain-list {
            margin-top: 20px;
        }
        
        .domain-item {
            display: flex;
            flex-direction: column;
            padding: 12px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #eee;
        }
        
        .domain-info {
            flex: 1;
            margin-bottom: 8px;
        }
        
        .domain-name {
            font-weight: 500;
            margin-bottom: 5px;
            word-break: break-all;
        }
        
        .domain-expiry {
            font-size: 0.8rem;
            color: #666;
        }
        
        .expiry-permanent {
            color: var(--success-color);
        }
        
        .expiry-active {
            color: var(--primary-color);
        }
        
        .expiry-expired {
            color: var(--danger-color);
        }
        
        .domain-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        
        .add-domain-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .add-domain-form input, 
        .add-domain-form select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            width: 100%;
        }
        
        .expiry-controls {
            display: flex;
            gap: 10px;
        }
        
        .expiry-type {
            flex: 1;
        }
        
        .expiry-date {
            flex: 2;
            display: none;
        }
        
        .domain-count {
            font-size: 0.8rem;
            color: #666;
            margin-left: 8px;
        }
        
        @media (min-width: 768px) {
            body {
                padding: 20px;
            }
            
            h1 {
                font-size: 1.8rem;
            }
            
            h2 {
                font-size: 1.4rem;
            }
            
            .message {
                padding: 12px 15px;
                font-size: 1rem;
            }
            
            .btn {
                padding: 10px 20px;
                font-size: 1rem;
            }
            
            .domain-item {
                flex-direction: row;
                align-items: center;
                padding: 12px 15px;
            }
            
            .domain-info {
                margin-bottom: 0;
            }
            
            .add-domain-form {
                flex-direction: row;
            }
            
            .expiry-controls {
                flex-direction: row;
            }
        }
    </style>
</head>
<body>
    <div class="container">
       
           
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <span style="font-size:1.2em;margin-right:8px;"><?php echo $messageType === 'success' ? '✓' : '⚠'; ?></span>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2><span style="font-size:1.2em;margin-right:8px;">➕</span> 添加新域名</h2>
            <div class="add-domain-form">
                <input type="text" id="new-domain" placeholder="输入新域名，例如：example.com">
                <div class="expiry-controls">
                    <select id="expiry-type" class="expiry-type">
                        <option value="permanent">永久有效</option>
                        <option value="custom">自定义日期</option>
                    </select>
                    <input type="date" id="expiry-date" class="expiry-date">
                </div>
                <button id="add-domain-btn" class="btn btn-primary"><span style="font-size:1.2em;margin-right:5px;">➕</span> 添加</button>
            </div>
            
            <div class="domain-list">
                <h2>
                    <span style="font-size:1.2em;margin-right:8px;">🌐</span> 当前授权域名 
                    <span class="domain-count">(共 <?php echo count($currentDomains); ?> 个)</span>
                </h2>
                <div id="domains-container">
                    <?php foreach ($currentDomains as $domain => $info): 
                        $expiry = $info['expires'];
                        $isExpired = $expiry !== 'permanent' && strtotime($expiry) < time();
                        $expiryClass = $expiry === 'permanent' ? 'expiry-permanent' : 
                                      ($isExpired ? 'expiry-expired' : 'expiry-active');
                    ?>
                        <div class="domain-item" data-domain="<?php echo htmlspecialchars($domain); ?>">
                            <div class="domain-info">
                                <div class="domain-name"><?php echo htmlspecialchars($domain); ?></div>
                                <div class="domain-expiry <?php echo $expiryClass; ?>">
                                    <span style="font-size:1.2em;margin-right:5px;">📅</span>
                                    <?php echo $expiry === 'permanent' ? '永久有效' : 
                                          ($isExpired ? '已过期 (' . $expiry . ')' : '有效期至 ' . $expiry); ?>
                                </div>
                            </div>
                            <div class="domain-actions">
                                <button class="btn btn-warning btn-sm edit-expiry-btn" data-domain="<?php echo htmlspecialchars($domain); ?>">
                                    <span style="font-size:1.2em;margin-right:5px;">✏️</span> <span class="action-text">修改</span>
                                </button>
                                <button class="btn btn-danger btn-sm delete-btn" data-domain="<?php echo htmlspecialchars($domain); ?>">
                                    <span style="font-size:1.2em;margin-right:5px;">🗑️</span> <span class="action-text">删除</span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 切换有效期类型
            const expiryType = document.getElementById('expiry-type');
            const expiryDate = document.getElementById('expiry-date');
            
            expiryType.addEventListener('change', function() {
                expiryDate.style.display = this.value === 'custom' ? 'block' : 'none';
            });
            
            // 简单的弹窗实现
            const showAlert = (options) => {
                const { icon, title, text, timer, showConfirmButton } = options;
                const alertDiv = document.createElement('div');
                alertDiv.className = `message ${icon === 'error' ? 'error' : 'success'}`;
                alertDiv.innerHTML = `
                    <span style="font-size:1.2em;margin-right:8px;">${icon === 'error' ? '⚠' : '✓'}</span>
                    <strong>${title}</strong> ${text}
                `;
                
                document.querySelector('.container').prepend(alertDiv);
                
                if (timer) {
                    setTimeout(() => {
                        alertDiv.remove();
                    }, timer);
                }
            };
            
            // 确认对话框
            const showConfirm = (options) => {
                return new Promise((resolve) => {
                    const { title, text, confirmText, cancelText } = options;
                    const overlay = document.createElement('div');
                    overlay.style.position = 'fixed';
                    overlay.style.top = '0';
                    overlay.style.left = '0';
                    overlay.style.right = '0';
                    overlay.style.bottom = '0';
                    overlay.style.backgroundColor = 'rgba(0,0,0,0.5)';
                    overlay.style.display = 'flex';
                    overlay.style.justifyContent = 'center';
                    overlay.style.alignItems = 'center';
                    overlay.style.zIndex = '1000';
                    
                    const dialog = document.createElement('div');
                    dialog.style.backgroundColor = 'white';
                    dialog.style.padding = '20px';
                    dialog.style.borderRadius = '10px';
                    dialog.style.maxWidth = '400px';
                    dialog.style.width = '90%';
                    
                    dialog.innerHTML = `
                        <h3 style="margin-top:0;color:var(--primary-color)">${title}</h3>
                        <p style="margin-bottom:20px">${text}</p>
                        <div style="display:flex;justify-content:flex-end;gap:10px">
                            <button id="confirm-cancel" class="btn" style="background-color:#6c757d">${cancelText}</button>
                            <button id="confirm-ok" class="btn btn-primary">${confirmText}</button>
                        </div>
                    `;
                    
                    overlay.appendChild(dialog);
                    document.body.appendChild(overlay);
                    
                    const confirmOk = document.getElementById('confirm-ok');
                    const confirmCancel = document.getElementById('confirm-cancel');
                    
                    confirmOk.addEventListener('click', () => {
                        overlay.remove();
                        resolve(true);
                    });
                    
                    confirmCancel.addEventListener('click', () => {
                        overlay.remove();
                        resolve(false);
                    });
                });
            };
            
            // 添加域名
            const addDomain = () => {
                const domainInput = document.getElementById('new-domain');
                const domain = domainInput.value.trim().toLowerCase().replace('www.', '');
                
                if (!domain) {
                    showAlert({
                        icon: 'error',
                        title: '错误',
                        text: '请输入有效的域名'
                    });
                    return;
                }
                
                let expires = 'permanent';
                if (expiryType.value === 'custom' && expiryDate.value) {
                    expires = expiryDate.value;
                }
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=add-domain&domain=${encodeURIComponent(domain)}&expires=${encodeURIComponent(expires)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // 添加到DOM
                        const domainItem = document.createElement('div');
                        domainItem.className = 'domain-item';
                        domainItem.dataset.domain = domain;
                        
                        const isExpired = data.expires !== 'permanent' && new Date(data.expires) < new Date();
                        const expiryClass = data.expires === 'permanent' ? 'expiry-permanent' : 
                                          (isExpired ? 'expiry-expired' : 'expiry-active');
                        const expiryText = data.expires === 'permanent' ? '永久有效' : 
                                          (isExpired ? '已过期 (' + data.expires + ')' : '有效期至 ' + data.expires);
                        
                        domainItem.innerHTML = `
                            <div class="domain-info">
                                <div class="domain-name">${domain}</div>
                                <div class="domain-expiry ${expiryClass}">
                                    <span style="font-size:1.2em;margin-right:5px;">📅</span> ${expiryText}
                                </div>
                            </div>
                            <div class="domain-actions">
                                <button class="btn btn-warning btn-sm edit-expiry-btn" data-domain="${domain}">
                                    <span style="font-size:1.2em;margin-right:5px;">✏️</span> <span class="action-text">修改</span>
                                </button>
                                <button class="btn btn-danger btn-sm delete-btn" data-domain="${domain}">
                                    <span style="font-size:1.2em;margin-right:5px;">🗑️</span> <span class="action-text">删除</span>
                                </button>
                            </div>
                        `;
                        
                        document.getElementById('domains-container').appendChild(domainItem);
                        
                        // 清空输入框
                        domainInput.value = '';
                        expiryType.value = 'permanent';
                        expiryDate.style.display = 'none';
                        expiryDate.value = '';
                        
                        // 更新计数
                        document.querySelector('.domain-count').textContent = `(共 ${data.count} 个)`;
                        
                        // 绑定事件
                        domainItem.querySelector('.delete-btn').addEventListener('click', deleteDomain);
                        domainItem.querySelector('.edit-expiry-btn').addEventListener('click', editExpiry);
                        
                        showAlert({
                            icon: 'success',
                            title: '成功',
                            text: '域名已添加',
                            timer: 1500
                        });
                    } else {
                        showAlert({
                            icon: 'error',
                            title: '错误',
                            text: data.message || '添加失败'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert({
                        icon: 'error',
                        title: '错误',
                        text: '请求失败'
                    });
                });
            };
            
            // 删除域名
            const deleteDomain = function() {
                const domain = this.dataset.domain;
                const domainItem = this.closest('.domain-item');
                
                showConfirm({
                    title: '确认删除',
                    text: `确定要删除域名 ${domain} 吗？`,
                    confirmText: '删除',
                    cancelText: '取消'
                }).then((confirmed) => {
                    if (confirmed) {
                        fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=delete-domain&domain=${encodeURIComponent(domain)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                // 从DOM移除
                                domainItem.remove();
                                
                                // 更新计数
                                document.querySelector('.domain-count').textContent = `(共 ${data.count} 个)`;
                                
                                showAlert({
                                    icon: 'success',
                                    title: '成功',
                                    text: '域名已删除',
                                    timer: 1500
                                });
                            } else {
                                showAlert({
                                    icon: 'error',
                                    title: '错误',
                                    text: data.message || '删除失败'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showAlert({
                                icon: 'error',
                                title: '错误',
                                text: '请求失败'
                            });
                        });
                    }
                });
            };
            
            // 修改有效期
            const editExpiry = function() {
                const domain = this.dataset.domain;
                const domainItem = this.closest('.domain-item');
                const currentExpiry = domainItem.querySelector('.domain-expiry').textContent.trim();
                
                let currentExpiryType = 'permanent';
                let currentExpiryDate = '';
                
                if (!currentExpiry.includes('永久')) {
                    currentExpiryType = 'custom';
                    // 提取日期部分 (可能包含"已过期"或"有效期至"前缀)
                    const dateMatch = currentExpiry.match(/(\d{4}-\d{2}-\d{2})/);
                    if (dateMatch) {
                        currentExpiryDate = dateMatch[1];
                    }
                }
                
                // 创建自定义对话框
                const overlay = document.createElement('div');
                overlay.style.position = 'fixed';
                overlay.style.top = '0';
                overlay.style.left = '0';
                overlay.style.right = '0';
                overlay.style.bottom = '0';
                overlay.style.backgroundColor = 'rgba(0,0,0,0.5)';
                overlay.style.display = 'flex';
                overlay.style.justifyContent = 'center';
                overlay.style.alignItems = 'center';
                overlay.style.zIndex = '1000';
                
                const dialog = document.createElement('div');
                dialog.style.backgroundColor = 'white';
                dialog.style.padding = '20px';
                dialog.style.borderRadius = '10px';
                dialog.style.maxWidth = '500px';
                dialog.style.width = '90%';
                
                dialog.innerHTML = `
                    <h3 style="margin-top:0;color:var(--primary-color)">修改域名 ${domain} 的有效期</h3>
                    <div style="margin-bottom:15px">
                        <label style="display:block;margin-bottom:5px">有效期类型:</label>
                        <select id="dialog-expiry-type" style="width:100%;padding:8px;border-radius:5px;border:1px solid #ddd">
                            <option value="permanent" ${currentExpiryType === 'permanent' ? 'selected' : ''}>永久有效</option>
                            <option value="custom" ${currentExpiryType === 'custom' ? 'selected' : ''}>自定义日期</option>
                        </select>
                    </div>
                    <div id="dialog-expiry-date-container" style="margin-bottom:20px;${currentExpiryType === 'permanent' ? 'display:none' : ''}">
                        <label style="display:block;margin-bottom:5px">有效期至:</label>
                        <input id="dialog-expiry-date" type="date" style="width:100%;padding:8px;border-radius:5px;border:1px solid #ddd" value="${currentExpiryDate}">
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:10px">
                        <button id="dialog-cancel" class="btn" style="background-color:#6c757d">取消</button>
                        <button id="dialog-save" class="btn btn-primary">保存</button>
                    </div>
                `;
                
                overlay.appendChild(dialog);
                document.body.appendChild(overlay);
                
                // 切换有效期类型时的显示/隐藏
                const expiryTypeSelect = document.getElementById('dialog-expiry-type');
                const expiryDateContainer = document.getElementById('dialog-expiry-date-container');
                
                expiryTypeSelect.addEventListener('change', function() {
                    expiryDateContainer.style.display = this.value === 'custom' ? 'block' : 'none';
                });
                
                // 处理保存按钮点击
                document.getElementById('dialog-save').addEventListener('click', function() {
                    let expires = 'permanent';
                    if (expiryTypeSelect.value === 'custom') {
                        const date = document.getElementById('dialog-expiry-date').value;
                        if (!date) {
                            showAlert({
                                icon: 'error',
                                title: '错误',
                                text: '请选择有效日期'
                            });
                            return;
                        }
                        expires = date;
                    }
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=update-expiry&domain=${encodeURIComponent(domain)}&expires=${encodeURIComponent(expires)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // 更新DOM
                            const isExpired = data.expires !== 'permanent' && new Date(data.expires) < new Date();
                            const expiryClass = data.expires === 'permanent' ? 'expiry-permanent' : 
                                              (isExpired ? 'expiry-expired' : 'expiry-active');
                            const expiryText = data.expires === 'permanent' ? '永久有效' : 
                                              (isExpired ? '已过期 (' + data.expires + ')' : '有效期至 ' + data.expires);
                            
                            const expiryElement = domainItem.querySelector('.domain-expiry');
                            expiryElement.className = `domain-expiry ${expiryClass}`;
                            expiryElement.innerHTML = `<span style="font-size:1.2em;margin-right:5px;">📅</span> ${expiryText}`;
                            
                            overlay.remove();
                            
                            showAlert({
                                icon: 'success',
                                title: '成功',
                                text: '有效期已更新',
                                timer: 1500
                            });
                        } else {
                            showAlert({
                                icon: 'error',
                                title: '错误',
                                text: data.message || '更新失败'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert({
                            icon: 'error',
                            title: '错误',
                            text: '请求失败'
                        });
                    });
                });
                
                // 处理取消按钮点击
                document.getElementById('dialog-cancel').addEventListener('click', function() {
                    overlay.remove();
                });
            };
            
            // 事件绑定
            document.getElementById('add-domain-btn').addEventListener('click', addDomain);
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', deleteDomain);
            });
            document.querySelectorAll('.edit-expiry-btn').forEach(btn => {
                btn.addEventListener('click', editExpiry);
            });
            
            // 回车键添加域名
            document.getElementById('new-domain').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addDomain();
                }
            });
            
            // 移动设备优化 - 隐藏按钮文字
            function checkScreenSize() {
                const actionTexts = document.querySelectorAll('.action-text');
                if (window.innerWidth < 768) {
                    actionTexts.forEach(text => {
                        text.style.display = 'none';
                    });
                } else {
                    actionTexts.forEach(text => {
                        text.style.display = 'inline';
                    });
                }
            }
            
            // 初始检查和窗口大小变化时检查
            checkScreenSize();
            window.addEventListener('resize', checkScreenSize);
        });
    </script>
</body>
</html>