<?php
/**
 * AI分析处理脚本
 * 用于后台异步处理分析订单
 */

// 设置脚本运行环境
set_time_limit(300); // 5分钟超时
ini_set('memory_limit', '256M');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

// 获取订单ID
$orderId = $argv[1] ?? null;

if (!$orderId) {
    error_log("处理分析脚本：缺少订单ID参数");
    exit(1);
}

try {
    error_log("开始处理分析订单：{$orderId}");
    
    // 处理分析
    $analysisOrder = new AnalysisOrder();
    $success = $analysisOrder->processAnalysis($orderId);
    
    if ($success) {
        error_log("分析订单处理成功：{$orderId}");
        exit(0);
    } else {
        error_log("分析订单处理失败：{$orderId}");
        exit(1);
    }
    
} catch (Exception $e) {
    error_log("分析订单处理异常：{$orderId} - " . $e->getMessage());
    exit(1);
}