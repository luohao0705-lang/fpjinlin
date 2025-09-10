<?php
/**
 * 完整工作流程测试脚本
 * 用于测试视频分析工作流的各个阶段
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/classes/SystemConfig.php';
require_once 'includes/classes/VideoAnalysisWorkflow.php';

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 视频分析工作流程测试 ===\n\n";

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // 1. 测试数据库连接
    echo "1. 测试数据库连接...\n";
    $stmt = $pdo->query("SELECT 1");
    if ($stmt) {
        echo "   ✅ 数据库连接成功\n";
    } else {
        echo "   ❌ 数据库连接失败\n";
        exit(1);
    }
    
    // 2. 检查表结构
    echo "\n2. 检查表结构...\n";
    $requiredTables = [
        'video_analysis_orders',
        'video_files', 
        'video_processing_queue',
        'recording_progress_logs',
        'workflow_progress_logs'
    ];
    
    foreach ($requiredTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            echo "   ✅ 表 {$table} 存在\n";
        } else {
            echo "   ❌ 表 {$table} 不存在\n";
        }
    }
    
    // 3. 检查字段结构
    echo "\n3. 检查字段结构...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM video_analysis_orders LIKE 'current_stage'");
    if ($stmt->rowCount() > 0) {
        echo "   ✅ video_analysis_orders.current_stage 字段存在\n";
    } else {
        echo "   ❌ video_analysis_orders.current_stage 字段不存在\n";
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM video_files LIKE 'processing_stage'");
    if ($stmt->rowCount() > 0) {
        echo "   ✅ video_files.processing_stage 字段存在\n";
    } else {
        echo "   ❌ video_files.processing_stage 字段不存在\n";
    }
    
    // 4. 测试工作流类
    echo "\n4. 测试工作流类...\n";
    try {
        require_once 'includes/classes/VideoAnalysisWorkflow.php';
        $workflow = new VideoAnalysisWorkflow();
        echo "   ✅ VideoAnalysisWorkflow 类加载成功\n";
    } catch (Exception $e) {
        echo "   ❌ VideoAnalysisWorkflow 类加载失败: " . $e->getMessage() . "\n";
    }
    
    // 5. 测试录制服务类
    echo "\n5. 测试录制服务类...\n";
    try {
        require_once 'includes/classes/VideoRecorder.php';
        $recorder = new VideoRecorder();
        echo "   ✅ VideoRecorder 类加载成功\n";
    } catch (Exception $e) {
        echo "   ❌ VideoRecorder 类加载失败: " . $e->getMessage() . "\n";
    }
    
    // 6. 测试AI分析服务类
    echo "\n6. 测试AI分析服务类...\n";
    try {
        require_once 'includes/classes/AIAnalysisService.php';
        $aiService = new AIAnalysisService();
        echo "   ✅ AIAnalysisService 类加载成功\n";
    } catch (Exception $e) {
        echo "   ❌ AIAnalysisService 类加载失败: " . $e->getMessage() . "\n";
    }
    
    // 7. 测试语音提取服务类
    echo "\n7. 测试语音提取服务类...\n";
    try {
        require_once 'includes/classes/SpeechExtractionService.php';
        $speechService = new SpeechExtractionService();
        echo "   ✅ SpeechExtractionService 类加载成功\n";
    } catch (Exception $e) {
        echo "   ❌ SpeechExtractionService 类加载失败: " . $e->getMessage() . "\n";
    }
    
    // 8. 测试报告生成服务类
    echo "\n8. 测试报告生成服务类...\n";
    try {
        require_once 'includes/classes/ReportGenerationService.php';
        $reportService = new ReportGenerationService();
        echo "   ✅ ReportGenerationService 类加载成功\n";
    } catch (Exception $e) {
        echo "   ❌ ReportGenerationService 类加载失败: " . $e->getMessage() . "\n";
    }
    
    // 9. 测试任务处理器
    echo "\n9. 测试任务处理器...\n";
    try {
        require_once 'task_processor_workflow.php';
        $taskProcessor = new TaskProcessorWorkflow();
        echo "   ✅ TaskProcessorWorkflow 类加载成功\n";
    } catch (Exception $e) {
        echo "   ❌ TaskProcessorWorkflow 类加载失败: " . $e->getMessage() . "\n";
    }
    
    // 10. 检查系统配置
    echo "\n10. 检查系统配置...\n";
    $config = new SystemConfig();
    $requiredConfigs = [
        'deepseek_api_key',
        'deepseek_api_url',
        'qwen_omni_api_key',
        'qwen_omni_api_url',
        'recording_duration',
        'segment_duration'
    ];
    
    foreach ($requiredConfigs as $configKey) {
        $value = $config->get($configKey);
        if (!empty($value)) {
            echo "   ✅ {$configKey}: 已配置\n";
        } else {
            echo "   ⚠️  {$configKey}: 未配置\n";
        }
    }
    
    // 11. 测试创建测试订单
    echo "\n11. 测试创建测试订单...\n";
    try {
        // 创建测试用户（如果不存在）
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = '13800138000'");
        $stmt->execute();
        $userId = $stmt->fetchColumn();
        
        if (!$userId) {
            $stmt = $pdo->prepare("
                INSERT INTO users (phone, nickname, password_hash, jingling_coins) 
                VALUES ('13800138000', '测试用户', 'test_hash', 1000)
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
            (user_id, order_no, title, cost_coins, status, self_video_link, competitor_video_links) 
            VALUES (?, ?, ?, 50, 'pending', ?, ?)
        ");
        $stmt->execute([
            $userId,
            $orderNo,
            '测试订单 - ' . date('Y-m-d H:i:s'),
            'https://example.com/self.flv',
            json_encode(['https://example.com/competitor1.flv', 'https://example.com/competitor2.flv'])
        ]);
        $testOrderId = $pdo->lastInsertId();
        echo "   ✅ 创建测试订单成功，ID: {$testOrderId}\n";
        
        // 测试启动分析
        echo "\n12. 测试启动分析...\n";
        $workflow = new VideoAnalysisWorkflow();
        $result = $workflow->startAnalysis($testOrderId);
        
        if ($result['success']) {
            echo "   ✅ 启动分析成功\n";
            echo "   消息: " . $result['message'] . "\n";
            
            // 检查视频文件是否创建
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM video_files WHERE order_id = ?");
            $stmt->execute([$testOrderId]);
            $videoFileCount = $stmt->fetchColumn();
            echo "   创建的视频文件数量: {$videoFileCount}\n";
            
            // 检查队列任务是否创建
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM video_processing_queue WHERE order_id = ?");
            $stmt->execute([$testOrderId]);
            $taskCount = $stmt->fetchColumn();
            echo "   创建的队列任务数量: {$taskCount}\n";
            
        } else {
            echo "   ❌ 启动分析失败: " . $result['message'] . "\n";
        }
        
        // 清理测试数据
        echo "\n13. 清理测试数据...\n";
        $pdo->prepare("DELETE FROM video_processing_queue WHERE order_id = ?")->execute([$testOrderId]);
        $pdo->prepare("DELETE FROM video_files WHERE order_id = ?")->execute([$testOrderId]);
        $pdo->prepare("DELETE FROM video_analysis_orders WHERE id = ?")->execute([$testOrderId]);
        echo "   ✅ 测试数据清理完成\n";
        
    } catch (Exception $e) {
        echo "   ❌ 测试订单创建失败: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== 测试完成 ===\n";
    echo "如果所有测试都通过，说明工作流程已经正确配置。\n";
    echo "接下来可以：\n";
    echo "1. 执行数据库更新脚本: mysql -u username -p database_name < update_database_schema.sql\n";
    echo "2. 设置定时任务: */30 * * * * php /path/to/cron_workflow_processor.php\n";
    echo "3. 在后台管理界面测试完整的视频分析流程\n";
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . "\n";
    echo "错误行号: " . $e->getLine() . "\n";
}
?>
