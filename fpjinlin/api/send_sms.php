<?php
/**
 * 发送短信验证码API
 * 复盘精灵系统
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/User.php';

header('Content-Type: application/json; charset=utf-8');

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('请求方法不允许', 405);
}

try {
    // 获取请求数据
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        errorResponse('请求数据格式错误');
    }
    
    $phone = sanitizeInput($input['phone'] ?? '');
    $type = sanitizeInput($input['type'] ?? 'login');
    
    // 验证参数
    if (empty($phone)) {
        errorResponse('手机号不能为空');
    }
    
    if (!isValidPhone($phone)) {
        errorResponse('手机号格式不正确');
    }
    
    if (!in_array($type, ['register', 'login', 'reset_password'])) {
        errorResponse('验证码类型不正确');
    }
    
    // 如果是注册验证码，检查用户是否已存在
    if ($type === 'register') {
        $db = Database::getInstance();
        $existingUser = $db->fetchOne(
            "SELECT id FROM users WHERE phone = ?",
            [$phone]
        );
        
        if ($existingUser) {
            errorResponse('该手机号已注册，请直接登录');
        }
    }
    
    // 发送验证码
    $user = new User();
    $result = $user->sendSMSCode($phone, $type);
    
    if ($result) {
        successResponse(null, '验证码已发送，请注意查收');
    } else {
        errorResponse('验证码发送失败，请重试');
    }
    
} catch (Exception $e) {
    errorResponse($e->getMessage());
}
?>