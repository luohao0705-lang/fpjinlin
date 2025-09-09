<?php
/**
 * 创建视频分析订单API
 * 复盘精灵系统 - 视频驱动分析
 */
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/classes/VideoAnalysisOrder.php';
require_once '../includes/classes/User.php';
require_once '../includes/classes/OperationLog.php';

// 启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    $title = trim($_POST['title'] ?? '');
    $selfVideoLink = trim($_POST['self_video_link'] ?? '');
    $competitorVideoLinks = json_decode($_POST['competitor_video_links'] ?? '[]', true);
    
    $userId = $_SESSION['user_id'];
    
    // 验证输入
    if (empty($title)) {
        throw new Exception('请输入分析标题');
    }
    
    if (empty($selfVideoLink)) {
        throw new Exception('请输入本方直播间链接');
    }
    
    if (!is_array($competitorVideoLinks) || count($competitorVideoLinks) < 2) {
        throw new Exception('请输入2个同行直播间链接');
    }
    
    // 基本验证：确保链接不为空
    foreach ($competitorVideoLinks as $index => $link) {
        if (empty(trim($link))) {
            throw new Exception('同行' . ($index + 1) . '直播间链接不能为空');
        }
    }
    
    // 创建视频分析订单
    $videoAnalysisOrder = new VideoAnalysisOrder();
    $result = $videoAnalysisOrder->createOrder(
        $userId,
        $title,
        $selfVideoLink,
        $competitorVideoLinks
    );
    
    // 记录操作日志
    $operationLog = new OperationLog();
    $operationLog->log('user', $userId, 'create_video_analysis', 'video_order', $result['orderId'], "创建视频分析订单：{$title}");
    
    jsonResponse([
        'success' => true,
        'message' => '视频分析订单创建成功，等待人工审核...',
        'data' => $result
    ]);
    
} catch (Exception $e) {
    // 记录详细错误信息
    error_log("创建视频分析订单失败: " . $e->getMessage());
    error_log("错误堆栈: " . $e->getTraceAsString());
    
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 500
    ], 200);
}

