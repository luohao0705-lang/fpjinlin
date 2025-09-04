<?php
/**
 * 使用兑换码API
 */
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 检查用户登录
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('请先登录');
    }
    
    // 检查请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('请求方法错误');
    }
    
    // 获取POST数据
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $userId = $_SESSION['user_id'];
    
    // 验证兑换码格式
    if (empty($code) || strlen($code) !== 12) {
        throw new Exception('请输入正确的12位兑换码');
    }
    
    if (!preg_match('/^[A-Z]{4}[0-9]{8}$/', $code)) {
        throw new Exception('兑换码格式错误，应为4位字母+8位数字');
    }
    
    // 使用兑换码
    $exchangeCode = new ExchangeCode();
    $result = $exchangeCode->useExchangeCode($code, $userId);
    
    // 记录操作日志
    $operationLog = new OperationLog();
    $operationLog->log('user', $userId, 'use_exchange_code', 'exchange_code', null, "使用兑换码：{$code}");
    
    jsonResponse([
        'success' => true,
        'message' => $result['message'],
        'data' => [
            'value' => $result['value'],
            'code' => $code
        ]
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}