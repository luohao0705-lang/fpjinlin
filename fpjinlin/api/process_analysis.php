<?php
/**
 * AI分析处理API
 * 复盘精灵系统 - 可以通过AJAX调用或定时任务调用
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/AIAnalyzer.php';
require_once __DIR__ . '/../includes/AnalysisOrder.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $analyzer = new AIAnalyzer();
    $orderManager = new AnalysisOrder();
    
    // 获取请求参数
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $orderId = intval($input['order_id'] ?? $_GET['order_id'] ?? 0);
    
    if ($orderId) {
        // 处理单个订单
        $result = $analyzer->analyzeOrder($orderId);
        successResponse($result, '分析完成');
    } else {
        // 批量处理待分析订单
        $limit = intval($input['limit'] ?? 5);
        $results = $analyzer->processPendingOrders($limit);
        
        $successCount = count(array_filter($results, function($r) { return $r['status'] === 'success'; }));
        $errorCount = count(array_filter($results, function($r) { return $r['status'] === 'error'; }));
        
        successResponse($results, "处理完成：成功 {$successCount} 个，失败 {$errorCount} 个");
    }
    
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}
?>