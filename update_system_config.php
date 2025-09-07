<?php
/**
 * 系统配置更新脚本
 * 复盘精灵系统 - 视频分析功能配置
 */
require_once 'config/config.php';
require_once 'config/database.php';

// 检查管理员权限
if (!isset($_SESSION['admin_id'])) {
    die('请先登录管理员账号');
}

try {
    $db = new Database();
    
    // 更新系统配置
    $configs = [
        // 视频分析相关配置
        ['video_analysis_cost_coins', '50', 'int', '视频分析消耗精灵币数量'],
        ['max_video_duration', '3600', 'int', '最大视频时长(秒)'],
        ['video_segment_duration', '120', 'int', '视频切片时长(秒)'],
        ['video_resolution', '720p', 'string', '视频转码分辨率'],
        ['video_bitrate', '1500k', 'string', '视频转码码率'],
        ['audio_bitrate', '64k', 'string', '音频转码码率'],
        
        // 阿里云OSS配置
        ['oss_bucket', '', 'string', '阿里云OSS存储桶名称'],
        ['oss_endpoint', '', 'string', '阿里云OSS端点'],
        ['oss_access_key', '', 'string', '阿里云OSS访问密钥'],
        ['oss_secret_key', '', 'string', '阿里云OSS秘密密钥'],
        
        // 阿里云Qwen-Omni配置
        ['qwen_omni_api_key', '', 'string', '阿里云Qwen-Omni API密钥'],
        ['qwen_omni_api_url', 'https://dashscope.aliyuncs.com/api/v1/services/aigc/video-understanding/generation', 'string', 'Qwen-Omni API地址'],
        
        // Whisper配置
        ['whisper_model_path', '/opt/whisper/models', 'string', 'Whisper模型路径'],
        
        // 并发处理配置
        ['max_concurrent_processing', '3', 'int', '最大并发处理数量'],
        ['video_retention_days', '30', 'int', '视频文件保留天数'],
        
        // 新增功能开关
        ['video_analysis_enabled', 'true', 'boolean', '是否启用视频分析功能'],
        ['auto_processing_enabled', 'true', 'boolean', '是否启用自动处理'],
        ['notification_enabled', 'true', 'boolean', '是否启用通知功能']
    ];
    
    $updatedCount = 0;
    $insertedCount = 0;
    
    foreach ($configs as $config) {
        $existing = $db->fetchOne(
            "SELECT id FROM system_configs WHERE config_key = ?",
            [$config[0]]
        );
        
        if ($existing) {
            // 更新现有配置
            $db->query(
                "UPDATE system_configs SET config_value = ?, config_type = ?, description = ? WHERE config_key = ?",
                [$config[1], $config[2], $config[3], $config[0]]
            );
            $updatedCount++;
        } else {
            // 插入新配置
            $db->insert(
                "INSERT INTO system_configs (config_key, config_value, config_type, description, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$config[0], $config[1], $config[2], $config[3]]
            );
            $insertedCount++;
        }
    }
    
    // 更新用户表，添加视频分析相关字段
    $db->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS total_video_orders INT(11) UNSIGNED DEFAULT 0 COMMENT '总视频分析订单数' AFTER total_orders");
    
    // 更新精灵币交易记录表，添加视频分析类型
    $db->query("ALTER TABLE coin_transactions MODIFY COLUMN type ENUM('recharge', 'consume', 'refund', 'video_analysis') NOT NULL COMMENT '交易类型'");
    
    echo "系统配置更新完成！\n";
    echo "更新配置: {$updatedCount} 个\n";
    echo "新增配置: {$insertedCount} 个\n";
    echo "数据库表结构已更新\n";
    
} catch (Exception $e) {
    echo "配置更新失败: " . $e->getMessage() . "\n";
    error_log("系统配置更新失败: " . $e->getMessage());
}
