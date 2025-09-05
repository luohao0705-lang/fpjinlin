<?php
/**
 * 手动触发分析API
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
    $adminId = $_SESSION['admin_id'];
    
    if (!$orderId) {
        throw new Exception('订单ID不能为空');
    }
    
    // 获取订单信息
    $analysisOrder = new AnalysisOrder();
    $order = $analysisOrder->getOrderById($orderId);
    
    if (!$order) {
        throw new Exception('订单不存在');
    }
    
    error_log("管理员 {$adminId} 手动触发分析订单 {$orderId}");
    
    // 记录管理员操作
    $operationLog = new OperationLog();
    $operationLog->log('admin', $adminId, 'manual_trigger_analysis', 'order', $orderId, "手动触发分析订单：{$order['order_no']}");
    
    // 立即执行分析
    try {
        $result = $analysisOrder->processAnalysis($orderId);
        
        jsonResponse([
            'success' => true,
            'message' => '分析已完成',
            'data' => [
                'order_id' => $orderId,
                'status' => 'completed'
            ]
        ]);
        
    } catch (Exception $analysisError) {
        // 分析失败，但API调用成功
        error_log("手动分析失败，订单 {$orderId}: " . $analysisError->getMessage());
        
        jsonResponse([
            'success' => false,
            'message' => '分析失败：' . $analysisError->getMessage(),
            'data' => [
                'order_id' => $orderId,
                'status' => 'failed',
                'error' => $analysisError->getMessage()
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("手动触发分析API错误: " . $e->getMessage());
    
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 200);
}
?>