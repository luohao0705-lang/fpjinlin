<?php
/**
 * 单个视频录制脚本
 * 专门用于录制单个视频，降低CPU使用
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/classes/VideoProcessor.php';

// 获取视频文件ID
$videoFileId = $argv[1] ?? null;

if (!$videoFileId) {
    error_log("❌ 未提供视频文件ID");
    exit(1);
}

try {
    // 获取视频文件信息
    $db = new Database();
    $videoFile = $db->fetchOne("SELECT * FROM video_files WHERE id = ?", [$videoFileId]);
    
    if (!$videoFile || empty($videoFile['flv_url'])) {
        error_log("❌ 视频文件或FLV地址不存在: {$videoFileId}");
        exit(1);
    }
    
    // 使用优化的录制参数
    $videoProcessor = new VideoProcessor();
    
    // 设置低CPU消耗参数
    $videoProcessor->setLowCPUParams();
    
    // 开始录制
    error_log("🎬 开始录制视频: {$videoFileId}");
    $videoProcessor->recordVideo($videoFileId, $videoFile['flv_url']);
    
    error_log("✅ 视频录制完成: {$videoFileId}");
    
} catch (Exception $e) {
    error_log("❌ 录制失败: " . $e->getMessage());
    exit(1);
}
?>
