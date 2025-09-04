<?php
/**
 * 用户登录API
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
    $loginType = $_POST['login_type'] ?? 'password';
    
    // 验证手机号
    if (empty($phone) || !validatePhone($phone)) {
        throw new Exception('请输入正确的手机号');
    }
    
    $user = new User();
    
    if ($loginType === 'password') {
        // 密码登录
        $password = $_POST['password'] ?? '';
        
        if (empty($password)) {
            throw new Exception('请输入密码');
        }
        
        $userInfo = $user->login($phone, $password);
        
    } elseif ($loginType === 'sms') {
        // 短信登录
        $smsCode = trim($_POST['sms_code'] ?? '');
        
        if (empty($smsCode) || strlen($smsCode) !== 6) {
            throw new Exception('请输入6位验证码');
        }
        
        $userInfo = $user->smsLogin($phone, $smsCode);
        
    } else {
        throw new Exception('登录方式错误');
    }
    
    // 设置会话
    $_SESSION['user_id'] = $userInfo['id'];
    $_SESSION['phone'] = $userInfo['phone'];
    $_SESSION['login_time'] = time();
    
    // 记录操作日志
    $operationLog = new OperationLog();
    $operationLog->log('user', $userInfo['id'], 'login', 'user', $userInfo['id'], "用户登录({$loginType})");
    
    jsonResponse([
        'success' => true,
        'message' => '登录成功',
        'data' => [
            'user_id' => $userInfo['id'],
            'phone' => $userInfo['phone'],
            'nickname' => $userInfo['nickname'],
            'jingling_coins' => $userInfo['jingling_coins']
        ]
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}