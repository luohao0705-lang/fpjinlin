<?php
/**
 * 生成兑换码API
 */
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 检查管理员登录
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('请先登录');
    }
    
    // 检查请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('请求方法错误');
    }
    
    // 获取POST数据
    $count = (int)($_POST['count'] ?? 0);
    $value = (int)($_POST['value'] ?? 0);
    $expiresAt = $_POST['expires_at'] ?? null;
    $adminId = $_SESSION['admin_id'];
    
    // 验证输入
    if ($count < 1 || $count > 1000) {
        throw new Exception('生成数量必须在1-1000之间');
    }
    
    if ($value < 1) {
        throw new Exception('请输入有效的面值');
    }
    
    // 验证过期时间
    if (!empty($expiresAt)) {
        $expiresTimestamp = strtotime($expiresAt);
        if ($expiresTimestamp === false || $expiresTimestamp <= time()) {
            throw new Exception('过期时间必须大于当前时间');
        }
        $expiresAt = date('Y-m-d H:i:s', $expiresTimestamp);
    } else {
        $expiresAt = null;
    }
    
    // 生成兑换码
    $exchangeCode = new ExchangeCode();
    $result = $exchangeCode->generateCodes($count, $value, $expiresAt, $adminId);
    
    // 记录操作日志
    $operationLog = new OperationLog();
    $operationLog->log('admin', $adminId, 'generate_codes', 'exchange_code', null, 
                      "生成兑换码：批次{$result['batchNo']}，数量{$count}，面值{$value}");
    
    jsonResponse([
        'success' => true,
        'message' => '兑换码生成成功',
        'data' => $result
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}