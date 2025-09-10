<?php
/**
 * 清除所有任务相关数据库的脚本
 * 危险操作，请谨慎使用！
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "⚠️ 危险操作：清除所有任务相关数据\n";
echo "=====================================\n\n";

// 安全检查
echo "请确认您要清除以下数据：\n";
echo "1. 所有视频分析订单\n";
echo "2. 所有视频文件记录\n";
echo "3. 所有处理队列任务\n";
echo "4. 所有操作日志\n\n";

echo "输入 'CLEAR_ALL_DATA' 确认清除（区分大小写）：";
$confirmation = trim(fgets(STDIN));

if ($confirmation !== 'CLEAR_ALL_DATA') {
    echo "❌ 操作已取消\n";
    exit(0);
}

echo "\n🔧 开始清除数据...\n";

try {
    // 检查数据库配置
    if (!file_exists('config/database.php')) {
        throw new Exception("缺少数据库配置文件");
    }
    
    require_once 'config/database.php';
    $db = new Database();
    
    // 开始事务
    $db->beginTransaction();
    
    echo "1. 清除视频处理队列...\n";
    $result1 = $db->query("TRUNCATE TABLE video_processing_queue");
    echo "✅ 已清除视频处理队列\n";
    
    echo "2. 清除视频文件记录...\n";
    $result2 = $db->query("TRUNCATE TABLE video_files");
    echo "✅ 已清除视频文件记录\n";
    
    echo "3. 清除视频分析订单...\n";
    $result3 = $db->query("TRUNCATE TABLE video_analysis_orders");
    echo "✅ 已清除视频分析订单\n";
    
    echo "4. 清除操作日志...\n";
    $result4 = $db->query("TRUNCATE TABLE operation_logs");
    echo "✅ 已清除操作日志\n";
    
    // 提交事务
    $db->commit();
    
    echo "\n🎉 数据清除完成！\n";
    echo "所有任务相关数据已被清除。\n";
    
} catch (Exception $e) {
    // 回滚事务
    if (isset($db)) {
        $db->rollback();
    }
    echo "❌ 清除失败: " . $e->getMessage() . "\n";
    exit(1);
}
?>
