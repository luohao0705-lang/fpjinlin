<?php
/**
 * AI服务使用量查询API
 * 复盘精灵系统 - 后台管理
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/classes/AIServiceQuota.php';

// 启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

try {
    // 检查管理员登录
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('请先登录');
    }
    
    $orderId = intval($_GET['order_id'] ?? 0);
    if (!$orderId) {
        throw new Exception('订单ID不能为空');
    }
    
    $quotaService = new AIServiceQuota();
    $usage = $quotaService->getOrderUsage($orderId);
    
    jsonResponse([
        'success' => true,
        'data' => $usage
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
?>
