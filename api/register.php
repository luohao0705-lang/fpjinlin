<?php
/**
 * 用户注册API
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
    $smsCode = trim($_POST['sms_code'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // 验证输入
    if (empty($phone) || !validatePhone($phone)) {
        throw new Exception('请输入正确的手机号');
    }
    
    if (empty($smsCode) || strlen($smsCode) !== 6) {
        throw new Exception('请输入6位验证码');
    }
    
    if (empty($password) || strlen($password) < PASSWORD_MIN_LENGTH) {
        throw new Exception('密码长度至少' . PASSWORD_MIN_LENGTH . '位');
    }
    
    // 创建用户
    $user = new User();
    $userId = $user->register($phone, $password, $smsCode);
    
    // 获取用户信息
    $userInfo = $user->getUserById($userId);
    
    // 安全登录
    SessionManager::login($userId, 'user', [
        'phone' => $phone,
        'nickname' => $userInfo['nickname']
    ]);
    
    // 记录操作日志
    $operationLog = new OperationLog();
    $operationLog->log('user', $userId, 'register', 'user', $userId, '用户注册');
    
    jsonResponse([
        'success' => true,
        'message' => '注册成功',
        'data' => [
            'user_id' => $userId,
            'phone' => $phone,
            'nickname' => $userInfo['nickname'],
            'jingling_coins' => $userInfo['jingling_coins']
        ]
    ]);
    
} catch (Exception $e) {
    ErrorHandler::apiError($e->getMessage());
}