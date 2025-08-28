<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// 模拟数据库中的版本信息
$versions = [
    '1.2.0' => [
        'version' => '1.2.0',
        'date' => '2025-05-15',
        'changes' => [
            '新增用户行为分析功能',
            '优化后台响应速度',
            '修复3个安全漏洞'
        ],
        'critical' => true,
        'download_url' => 'https://example.com/updates/v1.2.0.zip',
        'size' => '15.2 MB'
    ],
    
    '1.1.5' => [
        'version' => '1.1111.5',
        'date' => '2023-04-10',
        'changes' => [
            '增加VIP会员管理模块',
            '改进数据统计展示',
            '修复部分UI显示问题'
        ],
        'critical' => false
    ]
];








// 获取客户端当前版本
$currentVersion = $_GET['current_version'] ?? '1.0.0';

// 确定最新版本
$latestVersion = '2.1';
$updateAvailable = version_compare($currentVersion, $latestVersion, '<');

// 构建响应数据
$response = [
    'success' => true,
    'current_version' => $currentVersion,
    'latest_version' => $latestVersion,
    'update_available' => $updateAvailable,
    'is_critical' => $versions[$latestVersion]['critical'] ?? false,
    'changelog' => implode(', ', $versions[$latestVersion]['changes'] ?? ['常规更新']),
    'changelogs' => array_values($versions),
    'download_url' => $versions[$latestVersion]['download_url'] ?? '',
    'update_size' => $versions[$latestVersion]['size'] ?? '0 MB',
    'estimated_time' => '约3分钟'
];

echo json_encode($response);