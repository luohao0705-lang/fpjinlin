<?php
/**
 * 简化测试脚本
 * 用于测试基本功能
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 简化测试 ===\n\n";

try {
    // 1. 测试基本配置加载
    echo "1. 测试配置加载...\n";
    require_once 'config/config.php';
    require_once 'config/database.php';
    echo "   ✅ 基本配置加载成功\n";
    
    // 2. 测试数据库连接
    echo "\n2. 测试数据库连接...\n";
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $stmt = $pdo->query("SELECT 1");
    if ($stmt) {
        echo "   ✅ 数据库连接成功\n";
    } else {
        echo "   ❌ 数据库连接失败\n";
        exit(1);
    }
    
    // 3. 测试SystemConfig类
    echo "\n3. 测试SystemConfig类...\n";
    require_once 'includes/classes/SystemConfig.php';
    $config = new SystemConfig();
    echo "   ✅ SystemConfig类加载成功\n";
    
    // 4. 测试VideoAnalysisWorkflow类
    echo "\n4. 测试VideoAnalysisWorkflow类...\n";
    require_once 'includes/classes/VideoAnalysisWorkflow.php';
    $workflow = new VideoAnalysisWorkflow();
    echo "   ✅ VideoAnalysisWorkflow类加载成功\n";
    
    // 5. 测试其他服务类
    echo "\n5. 测试其他服务类...\n";
    require_once 'includes/classes/VideoRecorder.php';
    $recorder = new VideoRecorder();
    echo "   ✅ VideoRecorder类加载成功\n";
    
    require_once 'includes/classes/AIAnalysisService.php';
    $aiService = new AIAnalysisService();
    echo "   ✅ AIAnalysisService类加载成功\n";
    
    require_once 'includes/classes/SpeechExtractionService.php';
    $speechService = new SpeechExtractionService();
    echo "   ✅ SpeechExtractionService类加载成功\n";
    
    require_once 'includes/classes/ReportGenerationService.php';
    $reportService = new ReportGenerationService();
    echo "   ✅ ReportGenerationService类加载成功\n";
    
    // 6. 测试配置获取
    echo "\n6. 测试配置获取...\n";
    $deepseekKey = $config->get('deepseek_api_key');
    if (!empty($deepseekKey)) {
        echo "   ✅ DeepSeek API密钥已配置\n";
    } else {
        echo "   ⚠️  DeepSeek API密钥未配置\n";
    }
    
    $qwenKey = $config->get('qwen_omni_api_key');
    if (!empty($qwenKey)) {
        echo "   ✅ Qwen-Omni API密钥已配置\n";
    } else {
        echo "   ⚠️  Qwen-Omni API密钥未配置\n";
    }
    
    echo "\n=== 测试完成 ===\n";
    echo "所有核心类都已成功加载！\n";
    echo "现在可以运行完整的测试脚本了。\n";
    
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