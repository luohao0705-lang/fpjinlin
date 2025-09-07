<?php
/**
 * 后台订单处理API
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
    
    // 获取参数
    $orderId = (int)($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $adminId = $_SESSION['admin_id'];
    
    if (!$orderId) {
        throw new Exception('订单ID不能为空');
    }
    
    if (!in_array($action, ['start', 'reset', 'retry', 'delete'])) {
        throw new Exception('操作类型错误');
    }
    
    $analysisOrder = new AnalysisOrder();
    $order = $analysisOrder->getOrderById($orderId);
    
    if (!$order) {
        throw new Exception('订单不存在');
    }
    
    $operationLog = new OperationLog();
    
    switch ($action) {
        case 'start':
            if ($order['status'] !== 'pending') {
                throw new Exception('订单状态不允许开始分析');
            }
            
            // 手动启动分析
            $analysisOrder->updateOrderStatus($orderId, 'processing');
            
            // 尝试启动后台分析
            $scriptPath = dirname(__DIR__, 2) . '/scripts/process_analysis.php';
            if (file_exists($scriptPath)) {
                if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')))) {
                    $command = "php {$scriptPath} {$orderId} > /dev/null 2>&1 &";
                    exec($command);
                    $message = '已启动后台分析进程';
                } else {
                    $message = '已更新状态为处理中，请检查后台处理机制';
                }
            } else {
                $message = '已更新状态，但分析脚本不存在';
            }
            
            $operationLog->log('admin', $adminId, 'order_start', 'order', $orderId, "手动开始分析订单：{$order['order_no']}");
            break;
            
        case 'reset':
            $analysisOrder->updateOrderStatus($orderId, 'pending');
            $message = '订单状态已重置为待处理';
            $operationLog->log('admin', $adminId, 'order_reset', 'order', $orderId, "重置订单状态：{$order['order_no']}");
            break;
            
        case 'retry':
            if ($order['status'] !== 'failed') {
                throw new Exception('只能重试失败的订单');
            }
            
            $analysisOrder->updateOrderStatus($orderId, 'pending');
            $message = '订单已重置，可以重新分析';
            $operationLog->log('admin', $adminId, 'order_retry', 'order', $orderId, "重试分析订单：{$order['order_no']}");
            break;
            
        case 'delete':
            $analysisOrder->deleteOrder($orderId);
            $message = '订单已删除';
            $operationLog->log('admin', $adminId, 'order_delete', 'order', $orderId, "删除订单：{$order['order_no']}");
            break;
    }
    
    jsonResponse([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 200);
}
?>