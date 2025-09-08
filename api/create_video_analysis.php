<?php
/**
 * 创建视频分析订单API
 * 复盘精灵系统 - 视频驱动分析
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
    $selfVideoLink = trim($_POST['self_video_link'] ?? '');
    $competitorVideoLinks = json_decode($_POST['competitor_video_links'] ?? '[]', true);
    
    $userId = $_SESSION['user_id'];
    
    // 验证输入
    if (empty($title)) {
        throw new Exception('请输入分析标题');
    }
    
    if (empty($selfVideoLink)) {
        throw new Exception('请输入本方视频链接');
    }
    
    if (!is_array($competitorVideoLinks) || count($competitorVideoLinks) < 2) {
        throw new Exception('请输入2个同行视频链接');
    }
    
    // 提取和验证视频链接
    $extractedSelfLink = extractVideoLink($selfVideoLink);
    if (!$extractedSelfLink) {
        throw new Exception('本方视频链接格式不正确，请提供有效的视频分享链接');
    }
    
    foreach ($competitorVideoLinks as $index => $link) {
        if (empty(trim($link))) {
            throw new Exception('同行' . ($index + 1) . '视频链接不能为空');
        }
        $extractedLink = extractVideoLink($link);
        if (!$extractedLink) {
            throw new Exception('同行' . ($index + 1) . '视频链接格式不正确，请提供有效的视频分享链接');
        }
        // 更新为提取后的链接
        $competitorVideoLinks[$index] = $extractedLink;
    }
    
    // 更新本方链接为提取后的链接
    $selfVideoLink = $extractedSelfLink;
    
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

/**
 * 从文本中提取视频链接
 */
function extractVideoLink($text) {
    // 清理输入文本
    $text = trim($text);
    
    // 定义支持的平台链接模式
    $patterns = [
        // 抖音短链接 (v.douyin.com)
        '/https?:\/\/v\.douyin\.com\/[a-zA-Z0-9_-]+/',
        // 抖音完整链接
        '/https?:\/\/(www\.)?(douyin|iesdouyin)\.com\/video\/\d+/',
        // 快手短链接
        '/https?:\/\/v\.kuaishou\.com\/[a-zA-Z0-9_-]+/',
        // 快手完整链接
        '/https?:\/\/(www\.)?kuaishou\.com\/video\/\d+/',
        // 小红书链接
        '/https?:\/\/(www\.)?xiaohongshu\.com\/explore\/[a-zA-Z0-9_-]+/',
        // 小红书短链接
        '/https?:\/\/xhslink\.com\/[a-zA-Z0-9_-]+/'
    ];
    
    // 尝试从文本中提取链接
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $extractedUrl = $matches[0];
            
            // 清理URL，移除可能的额外字符
            $extractedUrl = preg_replace('/[^\w\/:\.-]+$/', '', $extractedUrl);
            
            // 验证提取的链接是否有效
            if (isValidVideoLink($extractedUrl)) {
                return $extractedUrl;
            }
        }
    }
    
    return false;
}

/**
 * 验证视频链接格式
 */
function isValidVideoLink($url) {
    $patterns = [
        // 抖音短链接
        '/^https?:\/\/v\.douyin\.com\/[a-zA-Z0-9_-]+$/',
        // 抖音完整链接
        '/^https?:\/\/(www\.)?(douyin|iesdouyin)\.com\/video\/\d+$/',
        // 快手短链接
        '/^https?:\/\/v\.kuaishou\.com\/[a-zA-Z0-9_-]+$/',
        // 快手完整链接
        '/^https?:\/\/(www\.)?kuaishou\.com\/video\/\d+$/',
        // 小红书链接
        '/^https?:\/\/(www\.)?xiaohongshu\.com\/explore\/[a-zA-Z0-9_-]+$/',
        // 小红书短链接
        '/^https?:\/\/xhslink\.com\/[a-zA-Z0-9_-]+$/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url)) {
            return true;
        }
    }
    
    return false;
}
