<?php
// 配置文件路径
$piracyDbFile = __DIR__ . '/../config/piracy_records.json';
$allowedDomainsFile = __DIR__ . '/../config/authorized_domains.php';

// 读取盗版记录数据
$piracyData = [];
if (file_exists($piracyDbFile)) {
    $jsonContent = file_get_contents($piracyDbFile);
    $piracyData = json_decode($jsonContent, true) ?: [];
}

// 读取授权域名数据
$allowedDomains = [];
if (file_exists($allowedDomainsFile)) {
    $allowedDomains = include $allowedDomainsFile;
    if (!is_array($allowedDomains)) {
        $allowedDomains = [];
    }
}

/**
 * 规范化域名处理
 */
function normalize_domain($domain) {
    if (empty($domain)) return '';
    $domain = strtolower(trim($domain));
    $domain = preg_replace('/^(www\.|http:\/\/|https:\/\/)/', '', $domain);
    $domain = explode(':', $domain)[0]; // 去除端口
    return trim($domain, '/');
}

/**
 * 检查域名状态（返回状态标识和CSS类）
 */
function get_domain_status($domain, $allowedDomains, $is_confirmed = false) {
    if (is_domain_authorized($domain, $allowedDomains)) {
        return ['text' => '已授权', 'class' => 'bg-success'];
    } elseif ($is_confirmed) {
        return ['text' => '盗版', 'class' => 'bg-danger'];
    } else {
        return ['text' => '未授权', 'class' => 'bg-warning'];
    }
}

/**
 * 检查域名是否授权
 */
function is_domain_authorized($domain, $allowedDomains) {
    $normalized = normalize_domain($domain);
    return isset($allowedDomains[$normalized]);
}

/**
 * 统计函数
 */
function calculateStatistics($data, $allowedDomains) {
    $stats = [
        'total_domains' => 0,
        'total_attempts' => 0,
        'authorized_domains' => 0,
        'unauthorized_domains' => 0,
        'confirmed_piracy' => 0,
        'active_ips' => [],
        'top_offenders' => [],
        'recent_activity' => [],
        'domain_types' => []
    ];
    
    foreach ($data as $entry) {
        if (!is_array($entry)) continue;
        
        $stats['total_domains']++;
        $stats['total_attempts'] += $entry['attempts'] ?? 0;
        
        // 统计授权状态
        if (is_domain_authorized($entry['domain'] ?? '', $allowedDomains)) {
            $stats['authorized_domains']++;
        } elseif (!empty($entry['confirmed'])) {
            $stats['confirmed_piracy']++;
        } else {
            $stats['unauthorized_domains']++;
        }
        
        // IP统计
        $ip = $entry['ip'] ?? '未知IP';
        $stats['active_ips'][$ip] = ($stats['active_ips'][$ip] ?? 0) + ($entry['attempts'] ?? 0);
        
        // 域名类型统计
        $domain = $entry['domain'] ?? '';
        if ($domain) {
            $parts = explode('.', $domain);
            if (count($parts) > 1) {
                $mainDomain = $parts[count($parts)-2] . '.' . $parts[count($parts)-1];
                $stats['domain_types'][$mainDomain] = ($stats['domain_types'][$mainDomain] ?? 0) + 1;
            }
        }
        
        // 最近活动
        if (isset($entry['last_seen'])) {
            $stats['recent_activity'][] = strtotime($entry['last_seen']);
        }
    }
    
    // 处理统计数据
    arsort($stats['active_ips']);
    $stats['top_offenders'] = array_slice($stats['active_ips'], 0, 5, true);
    rsort($stats['recent_activity']);
    
    return $stats;
}

$stats = calculateStatistics($piracyData, $allowedDomains);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>盗版域名监控系统</title>
   <style>
    /* 基础设置 */
    :root {
        --primary-color: #4361ee;
        --secondary-color: #3f37c9;
        --danger-color: #f72585;
        --warning-color: #f8961e;
        --success-color: #28a745;
        --info-color: #4895ef;
        --light-color: #f8f9fa;
        --dark-color: #212529;
        --gray-color: #6c757d;
        --border-radius: 0.375rem;
        --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
        --transition: all 0.3s ease;
    }
    
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    
    body {
        font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        line-height: 1.6;
        color: var(--dark-color);
        background-color: #f5f7fa;
        padding: 0;
        min-height: 100vh;
    }
    
    /* 布局容器 */
    .container-fluid {
        width: 100%;
        padding: 1rem;
        margin: 0 auto;
    }
    
    @media (min-width: 768px) {
        .container-fluid {
            padding: 1.5rem;
            max-width: 1200px;
        }
    }
    
    /* 标题样式 */
    h1, h2, h3, h4, h5, h6 {
        margin-bottom: 1rem;
        font-weight: 600;
        line-height: 1.2;
    }
    
    h1 {
        font-size: 2rem;
        color: var(--primary-color);
        padding-bottom: 0.5rem;
        border-bottom: 2px solid rgba(67, 97, 238, 0.1);
    }
    
    /* 卡片基础样式 */
    .card {
        background: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        margin-bottom: 1.5rem;
        border: none;
        overflow: hidden;
        transition: var(--transition);
    }
    
    .card:hover {
        box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1);
    }
    
    .card-header {
        background-color: var(--primary-color);
        color: white;
        font-weight: 600;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .card-body {
        padding: 1.25rem;
    }
    
    /* 统计卡片 */
    .stat-card {
        border-left: 4px solid var(--primary-color);
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .stat-card .card-title {
        font-size: 0.875rem;
        color: var(--gray-color);
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .stat-card .card-text {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--dark-color);
    }
    
    .stat-card .text-muted {
        font-size: 0.75rem;
    }
    
    /* 特殊卡片样式 */
    .top-offenders {
        border-left: 4px solid var(--danger-color);
    }
    
    .top-offenders .card-header {
        background-color: var(--danger-color);
    }
    
    .recent-activity {
        border-left: 4px solid var(--success-color);
    }
    
    .recent-activity .card-header {
        background-color: var(--success-color);
    }
    
    /* 表格样式 */
    .table-responsive {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }
    
    th {
        background-color: rgba(67, 97, 238, 0.05);
        font-weight: 600;
        padding: 0.75rem;
        text-align: left;
        color: var(--primary-color);
        border-bottom: 2px solid rgba(67, 97, 238, 0.1);
        white-space: nowrap;
    }
    
    td {
        padding: 0.75rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        vertical-align: middle;
    }
    
    tr:hover {
        background-color: rgba(67, 97, 238, 0.03);
    }
    
    /* 徽章样式 */
    .badge {
        display: inline-block;
        padding: 0.35em 0.65em;
        font-size: 0.75em;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 50rem;
        margin-right: 0.25rem;
        margin-bottom: 0.25rem;
    }
    
    .bg-primary {
        background-color: var(--primary-color) !important;
        color: white !important;
    }
    
    .bg-danger {
        background-color: var(--danger-color) !important;
        color: white !important;
    }
    
    .bg-warning {
        background-color: var(--warning-color) !important;
        color: var(--dark-color) !important;
    }
    
    .bg-success {
        background-color: var(--success-color) !important;
        color: white !important;
    }
    
    .bg-info {
        background-color: var(--info-color) !important;
        color: white !important;
    }
    
    .bg-secondary {
        background-color: var(--gray-color) !important;
        color: white !important;
    }
    
    /* 列表组 */
    .list-group {
        display: flex;
        flex-direction: column;
        padding-left: 0;
        margin-bottom: 0;
        border-radius: var(--border-radius);
    }
    
    .list-group-item {
        position: relative;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1.25rem;
        background-color: #fff;
        border: 1px solid rgba(0, 0, 0, 0.05);
        border-left: none;
        border-right: none;
    }
    
    .list-group-item:first-child {
        border-top: none;
    }
    
    .list-group-item:last-child {
        border-bottom: none;
    }
    
    /* 响应式网格 */
    .row {
        display: flex;
        flex-wrap: wrap;
        margin: -0.75rem;
    }
    
    .row > [class^="col-"] {
        padding: 0.75rem;
    }
    
    .col-12 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .col-md-6 {
        flex: 0 0 50%;
        max-width: 50%;
    }
    
    .col-md-3 {
        flex: 0 0 25%;
        max-width: 25%;
    }
    
    .col-lg-8 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .col-lg-4 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    @media (min-width: 768px) {
        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
        }
        
        .col-md-3 {
            flex: 0 0 25%;
            max-width: 25%;
        }
    }
    
    @media (min-width: 992px) {
        .col-lg-8 {
            flex: 0 0 66.666667%;
            max-width: 66.666667%;
        }
        
        .col-lg-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }
    }
    
    /* 工具提示 */
    [data-tooltip] {
        position: relative;
        cursor: pointer;
    }
    
    [data-tooltip]:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: var(--dark-color);
        color: #fff;
        padding: 0.5rem 1rem;
        border-radius: var(--border-radius);
        font-size: 0.75rem;
        white-space: nowrap;
        z-index: 100;
        pointer-events: none;
    }
    
    /* 图表容器 */
    .chart-container {
        position: relative;
        height: 200px;
        min-height: 200px;
    }
    
    /* 警告框 */
    .alert {
        padding: 0.75rem 1.25rem;
        margin-bottom: 1rem;
        border: 1px solid transparent;
        border-radius: var(--border-radius);
    }
    
    .alert-warning {
        color: #856404;
        background-color: #fff3cd;
        border-color: #ffeeba;
    }
    
    /* 文本样式 */
    .text-muted {
        color: var(--gray-color) !important;
    }
    
    .fw-bold {
        font-weight: 600 !important;
    }
    
    .small {
        font-size: 0.75rem !important;
    }
    
    /* 移动设备优化 */
    @media (max-width: 767.98px) {
        .card-header {
            padding: 0.75rem;
        }
        
        .card-body {
            padding: 1rem;
        }
        
        .stat-card .card-text {
            font-size: 1.5rem;
        }
        
        th, td {
            padding: 0.5rem;
            font-size: 0.8125rem;
        }
        
        .row {
            margin: -0.5rem;
        }
        
        .row > [class^="col-"] {
            padding: 0.5rem;
        }
        
        .col-md-3, .col-md-6 {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }
</style>
</head>
<body>
    <div class="container">
        <!-- 统计卡片 -->
        <div class="row">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="card-title">总域名数</div>
                        <div class="value"><?= $stats['total_domains'] ?></div>
                        <small class="text-muted">监控中的域名</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="card-title">已授权域名</div>
                        <div class="value"><?= $stats['authorized_domains'] ?></div>
                        <small class="text-muted">合法授权</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="card-title">盗版域名</div>
                        <div class="value"><?= $stats['confirmed_piracy'] ?></div>
                        <small class="text-muted">确认盗版</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="card-title">尝试次数</div>
                        <div class="value"><?= $stats['total_attempts'] ?></div>
                        <small class="text-muted">非法访问</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 主表格 -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-list-ul"></i> 域名访问记录
            </div>
            <div class="card-body">
                <?php if (empty($piracyData)): ?>
                    <div class="alert alert-warning">没有找到域名记录数据</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>域名</th>
                                <th>IP地址</th>
                                <th>首次出现</th>
                                <th>最后出现</th>
                                <th>尝试次数</th>
                                <th>状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($piracyData as $entry): ?>
                            <?php 
                                $status = get_domain_status(
                                    $entry['domain'] ?? '', 
                                    $allowedDomains, 
                                    !empty($entry['confirmed'])
                                );
                            ?>
                            <tr>
                                <td>
                                    <?= !empty($entry['domain']) ? htmlspecialchars($entry['domain']) : '<span class="text-muted">无域名</span>' ?>
                                    <?php if (!empty($entry['user_agent'])): ?>
                                        <small class="text-muted d-block"><?= htmlspecialchars($entry['user_agent']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($entry['ip'] ?? '未知') ?></td>
                                <td><?= htmlspecialchars($entry['first_seen'] ?? '未知') ?></td>
                                <td><?= htmlspecialchars($entry['last_seen'] ?? '未知') ?></td>
                                <td><span class="badge bg-secondary"><?= $entry['attempts'] ?? 0 ?></span></td>
                                <td>
                                    <span class="badge <?= $status['class'] ?>"><?= $status['text'] ?></span>
                                    <?php if (!empty($entry['history_status']) && $status['text'] !== $entry['history_status']): ?>
                                        <small class="text-muted d-block">之前: <?= $entry['history_status'] ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 侧边栏 -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <i class="bi bi-exclamation-triangle"></i> 恶意IP排名
                    </div>
                    <div class="card-body">
                        <?php if (empty($stats['top_offenders'])): ?>
                            <div class="alert alert-warning">没有恶意IP记录</div>
                        <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($stats['top_offenders'] as $ip => $count): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?= htmlspecialchars($ip) ?></span>
                                <span class="badge bg-primary"><?= $count ?>次</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-clock-history"></i> 最近活动
                    </div>
                    <div class="card-body">
                        <?php 
                        usort($piracyData, function($a, $b) {
                            return strtotime($b['last_seen'] ?? 0) - strtotime($a['last_seen'] ?? 0);
                        });
                        $recent = array_slice($piracyData, 0, 5);
                        ?>
                        
                        <?php if (empty($recent)): ?>
                            <div class="alert alert-warning">没有活动记录</div>
                        <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($recent as $entry): ?>
                            <?php 
                                $status = get_domain_status(
                                    $entry['domain'] ?? '', 
                                    $allowedDomains, 
                                    !empty($entry['confirmed'])
                                );
                            ?>
                            <li class="list-group-item">
                                <div class="fw-bold"><?= htmlspecialchars($entry['domain'] ?? $entry['ip'] ?? '未知') ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($entry['last_seen'] ?? '未知') ?></div>
                                <div class="mt-2">
                                    <span class="badge bg-secondary"><?= $entry['attempts'] ?? 0 ?>次</span>
                                    <span class="badge <?= $status['class'] ?>"><?= $status['text'] ?></span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // 域名类型图表
        <?php if (!empty($stats['domain_types'])): ?>
        var domainTypes = <?= json_encode($stats['domain_types']) ?>;
        var ctx = document.getElementById('domainChart').getContext('2d');
        var domainChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(domainTypes),
                datasets: [{
                    data: Object.values(domainTypes),
                    backgroundColor: [
                        '#4e73df',
                        '#1cc88a',
                        '#36b9cc',
                        '#f6c23e',
                        '#e74a3b',
                        '#858796'
                    ],
                    hoverBackgroundColor: [
                        '#2e59d9',
                        '#17a673',
                        '#2c9faf',
                        '#dda20a',
                        '#be2617',
                        '#6c757d'
                    ]
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.label || '';
                                var value = context.raw || 0;
                                var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                var percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '70%',
            },
        });
        <?php endif; ?>
    </script>
</body>
</html>