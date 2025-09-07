<?php
/**
 * 审核视频分析订单API
 * 复盘精灵系统 - 后台管理
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
    $orderId = intval($_POST['order_id'] ?? 0);
    $selfFlvUrl = trim($_POST['self_flv_url'] ?? '');
    $competitorFlvUrls = json_decode($_POST['competitor_flv_urls'] ?? '[]', true);
    
    // 验证输入
    if (!$orderId) {
        throw new Exception('订单ID不能为空');
    }
    
    if (empty($selfFlvUrl)) {
        throw new Exception('本方视频FLV地址不能为空');
    }
    
    if (!is_array($competitorFlvUrls) || count($competitorFlvUrls) < 2) {
        throw new Exception('同行视频FLV地址不能少于2个');
    }
    
    // 验证FLV地址格式
    if (!isValidFlvUrl($selfFlvUrl)) {
        throw new Exception('本方视频FLV地址格式不正确');
    }
    
    foreach ($competitorFlvUrls as $index => $url) {
        if (empty(trim($url))) {
            throw new Exception('同行' . ($index + 1) . '视频FLV地址不能为空');
        }
        if (!isValidFlvUrl($url)) {
            throw new Exception('同行' . ($index + 1) . '视频FLV地址格式不正确');
        }
    }
    
    // 获取订单信息
    $videoAnalysisOrder = new VideoAnalysisOrder();
    $order = $videoAnalysisOrder->getOrderById($orderId);
    
    if (!$order) {
        throw new Exception('订单不存在');
    }
    
    if ($order['status'] !== 'pending') {
        throw new Exception('订单状态不允许审核');
    }
    
    // 审核通过订单
    $videoAnalysisOrder->approveOrder($orderId, $selfFlvUrl, $competitorFlvUrls);
    
    // 记录操作日志
    $operationLog = new OperationLog();
    $operationLog->log('admin', $_SESSION['admin_id'], 'approve_video_order', 'video_order', $orderId, "审核通过视频分析订单：{$order['title']}");
    
    jsonResponse([
        'success' => true,
        'message' => '审核通过，订单已进入分析流程'
    ]);
    
} catch (Exception $e) {
    // 记录详细错误信息
    error_log("审核视频分析订单失败: " . $e->getMessage());
    error_log("错误堆栈: " . $e->getTraceAsString());
    
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 500
    ], 200);
}

/**
 * 验证FLV地址格式
 */
function isValidFlvUrl($url) {
    // 简单的URL格式验证
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}
