<?php
/**
 * 创建分析订单API
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
    $title = trim($_POST['title'] ?? '');
    $screenshots = $_POST['screenshots'] ?? [];
    $cover = $_POST['cover'] ?? '';
    $selfScript = trim($_POST['self_script'] ?? '');
    $competitorScripts = $_POST['competitor_scripts'] ?? [];
    
    $userId = $_SESSION['user_id'];
    
    // 验证输入
    if (empty($title)) {
        throw new Exception('请输入分析标题');
    }
    
    if (!is_array($screenshots) || count($screenshots) < 5) {
        throw new Exception('请上传5张直播截图');
    }
    
    if (empty($cover)) {
        throw new Exception('请上传封面图');
    }
    
    if (empty($selfScript)) {
        throw new Exception('请输入本方话术');
    }
    
    if (!is_array($competitorScripts) || count($competitorScripts) < 3) {
        throw new Exception('请输入3个同行话术');
    }
    
    // 验证每个同行话术都不为空
    foreach ($competitorScripts as $index => $script) {
        if (empty(trim($script))) {
            throw new Exception('同行话术' . ($index + 1) . '不能为空');
        }
    }
    
    // 创建分析订单
    $analysisOrder = new AnalysisOrder();
    $result = $analysisOrder->createOrder(
        $userId,
        $title,
        $selfScript,
        $competitorScripts
    );
    
    // 记录操作日志
    $operationLog = new OperationLog();
    $operationLog->log('user', $userId, 'create_analysis', 'order', $result['orderId'], "创建分析订单：{$title}");
    
    jsonResponse([
        'success' => true,
        'message' => '分析订单创建成功，AI正在分析中...',
        'data' => $result
    ]);
    
} catch (Exception $e) {
    // 对于业务逻辑错误，返回200状态码但success为false
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 200);
}