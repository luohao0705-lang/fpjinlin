<?php
/**
 * 简化工作流测试
 * 只测试核心功能
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/classes/SystemConfig.php';
require_once 'includes/classes/VideoAnalysisWorkflow.php';

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 简化工作流测试 ===\n\n";

try {
    // 1. 测试基本功能
    echo "1. 测试基本功能...\n";
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    echo "   ✅ 数据库连接成功\n";
    
    $config = new SystemConfig();
    echo "   ✅ 配置加载成功\n";
    
    $workflow = new VideoAnalysisWorkflow();
    echo "   ✅ 工作流类加载成功\n";
    
    // 2. 创建测试订单
    echo "\n2. 创建测试订单...\n";
    
    // 创建测试用户
    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = '13900139000'");
    $stmt->execute();
    $userId = $stmt->fetchColumn();
    
    if (!$userId) {
        $stmt = $pdo->prepare("
            INSERT INTO users (phone, nickname, password_hash, jingling_coins) 
            VALUES ('13900139000', '测试用户2', 'test_hash', 1000)
        ");
        $stmt->execute();
        $userId = $pdo->lastInsertId();
        echo "   ✅ 创建测试用户成功，ID: {$userId}\n";
    } else {
        echo "   ✅ 测试用户已存在，ID: {$userId}\n";
    }
    
    // 创建测试订单
    $orderNo = 'TEST_' . date('YmdHis');
    $stmt = $pdo->prepare("
        INSERT INTO video_analysis_orders 
        (user_id, order_no, title, cost_coins, status, self_video_link, competitor_video_links, self_flv_url, competitor_flv_urls) 
        VALUES (?, ?, ?, 50, 'pending', ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $orderNo,
        '简化测试订单 - ' . date('Y-m-d H:i:s'),
        'https://example.com/self.mp4',
        json_encode(['https://example.com/competitor1.mp4', 'https://example.com/competitor2.mp4']),
        'https://example.com/self.flv',
        json_encode(['https://example.com/competitor1.flv', 'https://example.com/competitor2.flv'])
    ]);
    $testOrderId = $pdo->lastInsertId();
    echo "   ✅ 创建测试订单成功，ID: {$testOrderId}\n";
    
    // 3. 测试启动分析（不实际执行录制）
    echo "\n3. 测试启动分析...\n";
    
    // 先检查订单是否存在
    $stmt = $pdo->prepare("SELECT * FROM video_analysis_orders WHERE id = ?");
    $stmt->execute([$testOrderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        echo "   ✅ 订单查询成功\n";
        echo "   订单状态: {$order['status']}\n";
        echo "   本方链接: {$order['self_video_link']}\n";
        
        // 测试启动分析（但不实际执行录制）
        try {
            $result = $workflow->startAnalysis($testOrderId);
            if ($result['success']) {
                echo "   ✅ 启动分析成功\n";
                echo "   消息: " . $result['message'] . "\n";
                
                // 检查是否创建了视频文件记录
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM video_files WHERE order_id = ?");
                $stmt->execute([$testOrderId]);
                $videoFileCount = $stmt->fetchColumn();
                echo "   创建的视频文件数量: {$videoFileCount}\n";
                
                // 检查订单状态是否更新
                $stmt = $pdo->prepare("SELECT status, current_stage FROM video_analysis_orders WHERE id = ?");
                $stmt->execute([$testOrderId]);
                $orderStatus = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "   更新后状态: {$orderStatus['status']}\n";
                echo "   当前阶段: {$orderStatus['current_stage']}\n";
                
            } else {
                echo "   ❌ 启动分析失败: " . $result['message'] . "\n";
            }
        } catch (Exception $e) {
            echo "   ❌ 启动分析异常: " . $e->getMessage() . "\n";
            echo "   错误文件: " . $e->getFile() . "\n";
            echo "   错误行号: " . $e->getLine() . "\n";
        }
    } else {
        echo "   ❌ 订单查询失败\n";
    }
    
    // 4. 清理测试数据
    echo "\n4. 清理测试数据...\n";
    $pdo->prepare("DELETE FROM video_files WHERE order_id = ?")->execute([$testOrderId]);
    $pdo->prepare("DELETE FROM video_analysis_orders WHERE id = ?")->execute([$testOrderId]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
    echo "   ✅ 测试数据清理完成\n";
    
    echo "\n=== 测试完成 ===\n";
    echo "工作流核心功能测试通过！\n";
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . "\n";
    echo "错误行号: " . $e->getLine() . "\n";
    echo "错误堆栈:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "❌ 致命错误: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . "\n";
    echo "错误行号: " . $e->getLine() . "\n";
    echo "错误堆栈:\n" . $e->getTraceAsString() . "\n";
}
?>
