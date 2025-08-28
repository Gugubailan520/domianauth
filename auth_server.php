<?php
// auth_server.php - 授权服务器端代码（支持域名或IP单独授权）
header('Content-Type: application/json');

// 安全配置
define('AUTH_SECRET', '113300'); // 授权密钥(应存储在安全位置)
define('TOKEN_EXPIRY', 3600);    // 令牌有效期1小时
define('PIRACY_DB_FILE', __DIR__.'/config/piracy_records.json'); // 盗版记录存储位置
define('PIRACY_THRESHOLD', 3);   // 同一域名/IP尝试3次视为盗版
define('ADMIN_KEY', 'your_admin_secret_key_here'); // 管理员密钥
define('ALLOWED_ADMIN_IPS', ['127.0.0.1', '192.168.1.100']); // 允许的管理IP

// 初始化盗版记录文件
if (!file_exists(PIRACY_DB_FILE)) {
    file_put_contents(PIRACY_DB_FILE, json_encode([]));
}

/**
 * 记录盗版行为
 */
function record_piracy($domain, $ip, $reason) {
    $records = file_exists(PIRACY_DB_FILE) ? json_decode(file_get_contents(PIRACY_DB_FILE), true) : [];
    if (!is_array($records)) $records = [];
    
    $key = md5($domain . $ip);
    
    if (!isset($records[$key])) {
        $records[$key] = [
            'domain' => $domain,
            'ip' => $ip,
            'first_seen' => date('Y-m-d H:i:s'),
            'last_seen' => date('Y-m-d H:i:s'),
            'attempts' => 1,
            'reasons' => [$reason],
            'confirmed' => false,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? ''
        ];
    } else {
        $records[$key]['attempts']++;
        $records[$key]['last_seen'] = date('Y-m-d H:i:s');
        if (!in_array($reason, $records[$key]['reasons'])) {
            $records[$key]['reasons'][] = $reason;
        }
        
        if ($records[$key]['attempts'] >= PIRACY_THRESHOLD) {
            $records[$key]['confirmed'] = true;
            notify_admin("确认盗版: 域名 {$domain} IP {$ip} 尝试次数 {$records[$key]['attempts']}");
        }
    }
    
    file_put_contents(PIRACY_DB_FILE, json_encode($records, JSON_PRETTY_PRINT));
}

/**
 * 获取授权配置
 */
function get_auth_config() {
    $configFile = __DIR__ . '/config/authorized_domains.php';
    return file_exists($configFile) ? include $configFile : [];
}

/**
 * 验证授权（支持单独IP或域名授权）
 */
function check_auth_authorization($domain, $client_ip) {
    $auth_config = get_auth_config();
    
    // 1. 检查是否为IP授权
    $ip_auth = false;
    foreach ($auth_config as $auth_item) {
        if (isset($auth_item['allowed_ips']) && in_array($client_ip, $auth_item['allowed_ips'])) {
            $ip_auth = [
                'status' => true,
                'type' => 'ip',
                'expires' => $auth_item['expires'] ?? 'permanent'
            ];
            break;
        }
    }
    
    // 2. 检查是否为域名授权
    $domain_auth = false;
    if (array_key_exists($domain, $auth_config)) {
        $domain_info = $auth_config[$domain];
        $is_valid_domain = ($domain_info['expires'] == 'permanent') || 
                          (strtotime($domain_info['expires']) > time());
        
        if ($is_valid_domain) {
            $domain_auth = [
                'status' => true,
                'type' => 'domain',
                'expires' => $domain_info['expires']
            ];
        }
    }
    
    // 3. 只要IP或域名其中一项授权通过即可
    if ($ip_auth || $domain_auth) {
        $auth_result = $ip_auth ?: $domain_auth;
        return [
            'status' => true,
            'type' => $auth_result['type'],
            'expires' => $auth_result['expires']
        ];
    }
    
    // 4. 检查域名是否存在但已过期
    if (array_key_exists($domain, $auth_config)) {
        return [
            'status' => false,
            'error' => 'domain_expired',
            'message' => '域名授权已过期'
        ];
    }
    
    return [
        'status' => false,
        'error' => 'not_authorized',
        'message' => '域名或IP未授权'
    ];
}

/**
 * 生成授权令牌（绑定IP）
 */
function generate_auth_token($auth_type, $expiry, $client_ip, $domain = null) {
    $token_data = [
        'auth_type' => $auth_type,
        'allowed_ip' => $client_ip,
        'domain' => $domain,
        'expires' => time() + TOKEN_EXPIRY,
        'timestamp' => time(),
        'nonce' => bin2hex(random_bytes(8)),
        'server_time' => time()
    ];
    
    $token = base64_encode(json_encode($token_data));
    $signature = hash_hmac('sha256', $token, AUTH_SECRET);
    
    error_log("生成的Token数据: ".json_encode([
        'auth_type' => $auth_type,
        'token_data' => $token_data,
        'signature' => $signature
    ]));
    
    return [
        'token' => $token,
        'signature' => $signature,
        'expires' => $token_data['expires'],
        'server_time' => $token_data['server_time']
    ];
}

/**
 * 检查管理员权限
 */
function check_admin_access() {
    $adminKey = $_GET['admin_key'] ?? '';
    $clientIp = $_SERVER['REMOTE_ADDR'];
    return ($adminKey === ADMIN_KEY) && in_array($clientIp, ALLOWED_ADMIN_IPS);
}

/**
 * 通知管理员
 */
function notify_admin($message) {
    error_log("ADMIN NOTIFICATION: " . $message);
}

/**
 * 请求频率限制
 */
function check_rate_limit($domain, $ip) {
    static $requests = [];
    $now = time();
    $key = md5($domain . $ip);
    
    // 清除60秒前的记录
    foreach ($requests as $k => $v) {
        if ($v['time'] < $now - 60) unset($requests[$k]);
    }
    
    // 检查频率(每分钟最多10次)
    if (isset($requests[$key]) && $requests[$key]['count'] > 10) {
        return false;
    }
    
    $requests[$key] = [
        'count' => isset($requests[$key]) ? $requests[$key]['count'] + 1 : 1,
        'time' => $now
    ];
    
    return true;
}

// 主处理逻辑
$action = $_GET['action'] ?? 'check_auth';
$requested_domain = isset($_GET['domain']) ? 
    strtolower(trim(str_replace(['http://', 'https://', 'www.'], '', $_GET['domain']))) : '';
$client_ip = $_SERVER['REMOTE_ADDR'];

switch ($action) {
    case 'list_domains':
        // 列出所有授权域名(需要管理员权限)
        if (!check_admin_access()) {
            echo json_encode(['status' => 'error', 'message' => 'Access denied']);
            exit;
        }
        
        $domains = [];
        foreach (get_auth_config() as $domain => $info) {
            $domains[$domain] = [
                'expires' => $info['expires'],
                'allowed_ips' => $info['allowed_ips'] ?? [],
                'status' => ($info['expires'] == 'permanent' || strtotime($info['expires']) > time()) 
                          ? 'active' : 'expired'
            ];
        }
        
        echo json_encode([
            'status' => 'success',
            'domains' => $domains
        ]);
        break;
        
    case 'check_auth':
    default:
        // 1. 参数验证
        if (empty($requested_domain) && empty($client_ip)) {
            echo json_encode(['status' => 'error', 'message' => '请输入域名或IP']);
            exit;
        }
        
        // 2. 频率限制检查
        if (!check_rate_limit($requested_domain, $client_ip)) {
            record_piracy($requested_domain, $client_ip, '请求频率过高');
            echo json_encode([
                'status' => 'error',
                'message' => '请求过于频繁，请稍后再试'
            ]);
            exit;
        }
        
        // 3. 验证授权（支持单独IP或域名授权）
        $auth_result = check_auth_authorization($requested_domain, $client_ip);
        
        if ($auth_result['status']) {
            // 生成令牌（根据授权类型）
            $token_info = generate_auth_token(
                $auth_result['type'], 
                $auth_result['expires'], 
                $client_ip,
                $requested_domain
            );
            
            echo json_encode([
                'status' => 'success',
                'auth_type' => $auth_result['type'],
                'token' => $token_info['token'],
                'signature' => $token_info['signature'],
                'expires' => $token_info['expires'],
                'auth_expiry' => $auth_result['expires']
            ]);
        } else {
            // 记录具体的错误类型
            if (!empty($requested_domain)) {
                record_piracy($requested_domain, $client_ip, $auth_result['message']);
            }
            
            $response = [
                'status' => 'error',
                'message' => $auth_result['message'],
                'error_code' => $auth_result['error']
            ];
            
            echo json_encode($response);
        }
        break;
}