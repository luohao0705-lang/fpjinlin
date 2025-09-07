<?php
/**
 * 定时处理待分析订单脚本
 * 复盘精灵系统
 * 
 * 使用方法：
 * 1. 命令行运行: php process_pending_orders.php
 * 2. 设置crontab定时任务: */5 * * * * /usr/bin/php /path/to/process_pending_orders.php
 */

// 设置运行环境
set_time_limit(300); // 5分钟超时
ini_set('memory_limit', '256M');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/AIAnalyzer.php';

// 锁文件防止重复运行
$lockFile = __DIR__ . '/process_orders.lock';

if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    if (time() - $lockTime < 300) { // 5分钟内有锁文件，退出
        echo "处理脚本正在运行中...\n";
        exit(0);
    } else {
        // 清除过期锁文件
        unlink($lockFile);
    }
}

// 创建锁文件
file_put_contents($lockFile, getmypid());

try {
    echo "开始处理待分析订单...\n";
    echo "时间: " . date('Y-m-d H:i:s') . "\n";
    
    $analyzer = new AIAnalyzer();
    
    // 获取待处理订单数量
    $db = Database::getInstance();
    $pendingCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM analysis_orders WHERE status = 'pending'"
    )['count'];
    
    echo "待处理订单数量: {$pendingCount}\n";
    
    if ($pendingCount > 0) {
        // 批量处理订单（每次最多处理3个，避免API限制）
        $batchSize = min(3, $pendingCount);
        $results = $analyzer->processPendingOrders($batchSize);
        
        echo "本次处理订单数量: " . count($results) . "\n";
        
        foreach ($results as $result) {
            $orderId = $result['order_id'];
            $status = $result['status'];
            
            if ($status === 'success') {
                echo "订单 {$orderId}: 分析成功\n";
                
                // 获取订单信息
                $order = $db->fetchOne(
                    "SELECT title, user_id FROM analysis_orders WHERE id = ?",
                    [$orderId]
                );
                
                if ($order) {
                    echo "  - 标题: {$order['title']}\n";
                    echo "  - 用户ID: {$order['user_id']}\n";
                }
                
            } else {
                echo "订单 {$orderId}: 分析失败 - {$result['error']}\n";
            }
        }
        
        // 统计处理结果
        $successCount = count(array_filter($results, function($r) { return $r['status'] === 'success'; }));
        $errorCount = count(array_filter($results, function($r) { return $r['status'] === 'error'; }));
        
        echo "\n处理结果统计:\n";
        echo "成功: {$successCount} 个\n";
        echo "失败: {$errorCount} 个\n";
        
        // 如果还有待处理订单，建议下次运行时间
        $remainingCount = $pendingCount - count($results);
        if ($remainingCount > 0) {
            echo "剩余待处理: {$remainingCount} 个\n";
            echo "建议5分钟后再次运行\n";
        }
        
    } else {
        echo "没有待处理的订单\n";
    }
    
    echo "处理完成: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "处理出错: " . $e->getMessage() . "\n";
    error_log("定时分析脚本错误: " . $e->getMessage());
    
} finally {
    // 清理锁文件
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}

echo "\n";
?>