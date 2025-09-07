<?php
/**
 * 订单状态查询API
 */
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 检查用户登录
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('请先登录');
    }
    
    // 获取订单ID
    $orderId = (int)($_GET['id'] ?? 0);
    if (!$orderId) {
        throw new Exception('订单ID不能为空');
    }
    
    $userId = $_SESSION['user_id'];
    
    // 获取订单信息
    $analysisOrder = new AnalysisOrder();
    $order = $analysisOrder->getOrderById($orderId);
    
    if (!$order) {
        throw new Exception('订单不存在');
    }
    
    // 检查订单所有权
    if ($order['user_id'] != $userId) {
        throw new Exception('无权访问此订单');
    }
    
    // 计算进度百分比
    $progress = 0;
    $statusText = '';
    
    switch ($order['status']) {
        case 'pending':
            $progress = 10;
            $statusText = '等待处理中...';
            break;
        case 'processing':
            $progress = 50;
            $statusText = 'AI正在分析中...';
            break;
        case 'completed':
            $progress = 100;
            $statusText = '分析完成';
            break;
        case 'failed':
            $progress = 0;
            $statusText = '分析失败';
            break;
    }
    
    jsonResponse([
        'success' => true,
        'data' => [
            'id' => $order['id'],
            'order_no' => $order['order_no'],
            'title' => $order['title'],
            'status' => $order['status'],
            'progress' => $progress,
            'status_text' => $statusText,
            'created_at' => $order['created_at'],
            'completed_at' => $order['completed_at'],
            'error_message' => $order['error_message']
        ]
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 200);
}
?>