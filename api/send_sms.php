<?php
/**
 * 发送短信验证码API
 */
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 检查请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('请求方法错误');
    }
    
    // 获取POST数据
    $phone = trim($_POST['phone'] ?? '');
    $type = $_POST['type'] ?? 'register';
    
    // 验证输入
    if (empty($phone) || !validatePhone($phone)) {
        throw new Exception('请输入正确的手机号');
    }
    
    if (!in_array($type, ['register', 'login', 'reset_password'])) {
        throw new Exception('验证码类型错误');
    }
    
    // 对于注册，检查手机号是否已存在
    if ($type === 'register') {
        $user = new User();
        if ($user->getUserByPhone($phone)) {
            throw new Exception('该手机号已注册，请直接登录');
        }
    }
    
    // 对于登录，检查手机号是否存在
    if ($type === 'login') {
        $user = new User();
        if (!$user->getUserByPhone($phone)) {
            throw new Exception('该手机号未注册，请先注册');
        }
    }
    
    // 发送短信验证码
    $smsService = new SmsService();
    $smsService->sendVerificationCode($phone, $type);
    
    jsonResponse([
        'success' => true,
        'message' => '验证码发送成功，请注意查收'
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}