<?php
// 简单的录制测试
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/classes/VideoProcessor.php';

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>录制测试</h2>";

try {
    // 创建VideoProcessor实例
    $processor = new VideoProcessor();
    
    // 测试FLV地址
    $flvUrl = 'http://pull-flv-l11.douyincdn.com/stage/stream-117938925979566225_or4.flv?arch_hrchy=w1&exp_hrchy=w1&expire=1758043051&major_anchor_level=common&sign=e89d25e0d515043fe75bb9e5f6dc8efc&t_id=037-2025091001173157A1F3440DB6F835A1D0-XfqGZG&unique_id=stream-117938925979566225_145_flv_or4';
    
    echo "<p>测试FLV地址: " . htmlspecialchars($flvUrl) . "</p>";
    
    // 生成测试文件路径
    $outputFile = sys_get_temp_dir() . '/test_recording_' . time() . '.mp4';
    
    echo "<p>输出文件: " . htmlspecialchars($outputFile) . "</p>";
    
    // 检查proc_open是否可用
    if (function_exists('proc_open')) {
        echo "<p>✅ proc_open 可用</p>";
    } else {
        echo "<p>❌ proc_open 不可用，将使用exec</p>";
    }
    
    // 检查exec是否可用
    if (function_exists('exec')) {
        echo "<p>✅ exec 可用</p>";
    } else {
        echo "<p>❌ exec 不可用</p>";
    }
    
    // 检查FFmpeg
    $ffmpegPath = '';
    if (PHP_OS_FAMILY === 'Windows') {
        $ffmpegPath = 'ffmpeg.exe';
    } else {
        $ffmpegPath = 'ffmpeg';
    }
    
    // 尝试多种方式查找FFmpeg
    $ffmpegFound = false;
    $ffmpegLocation = '';
    
    // 方法1: which命令
    $ffmpegCheck = shell_exec("which {$ffmpegPath} 2>/dev/null");
    if ($ffmpegCheck && trim($ffmpegCheck)) {
        $ffmpegFound = true;
        $ffmpegLocation = trim($ffmpegCheck);
    }
    
    // 方法2: whereis命令
    if (!$ffmpegFound) {
        $ffmpegCheck = shell_exec("whereis {$ffmpegPath} 2>/dev/null");
        if ($ffmpegCheck && strpos($ffmpegCheck, '/') !== false) {
            $parts = explode(' ', $ffmpegCheck);
            if (count($parts) > 1) {
                $ffmpegFound = true;
                $ffmpegLocation = $parts[1];
            }
        }
    }
    
    // 方法3: 直接测试命令
    if (!$ffmpegFound) {
        $testOutput = [];
        $testCode = 0;
        exec("{$ffmpegPath} -version 2>&1", $testOutput, $testCode);
        if ($testCode === 0 && !empty($testOutput)) {
            $ffmpegFound = true;
            $ffmpegLocation = $ffmpegPath;
        }
    }
    
    // 方法4: 常见路径
    if (!$ffmpegFound) {
        $commonPaths = [
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            '/opt/ffmpeg/bin/ffmpeg',
            '/usr/bin/ffmpeg-static/ffmpeg'
        ];
        
        foreach ($commonPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $ffmpegFound = true;
                $ffmpegLocation = $path;
                break;
            }
        }
    }
    
    if ($ffmpegFound) {
        echo "<p>✅ FFmpeg 路径: " . htmlspecialchars($ffmpegLocation) . "</p>";
        
        // 测试FFmpeg版本
        $versionOutput = [];
        exec("{$ffmpegLocation} -version 2>&1", $versionOutput);
        if (!empty($versionOutput)) {
            echo "<p>FFmpeg 版本: " . htmlspecialchars($versionOutput[0]) . "</p>";
        }
    } else {
        echo "<p>❌ FFmpeg 未找到</p>";
        echo "<p>请安装FFmpeg或检查路径配置</p>";
    }
    
    // 测试录制
    if ($ffmpegFound) {
        echo "<p>开始测试录制...</p>";
        
        // 直接测试FFmpeg命令
        $maxDuration = 10; // 只录制10秒用于测试
        $outputFile = sys_get_temp_dir() . '/test_recording_' . time() . '.mp4';
        
        $command = sprintf(
            '%s -user_agent "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" -headers "Referer: https://live.douyin.com/" -i %s -t %d -c:v libx264 -preset fast -crf 23 -c:a aac -ac 2 -ar 44100 -movflags +faststart -avoid_negative_ts make_zero -fflags +genpts %s -y',
            escapeshellarg($ffmpegLocation),
            escapeshellarg($flvUrl),
            $maxDuration,
            escapeshellarg($outputFile)
        );
        
        echo "<p>执行命令: " . htmlspecialchars($command) . "</p>";
        
        $startTime = time();
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        $endTime = time();
        
        if ($returnCode === 0) {
            if (file_exists($outputFile) && filesize($outputFile) > 0) {
                echo "<p>✅ 录制成功！</p>";
                echo "<p>文件大小: " . formatFileSize(filesize($outputFile)) . "</p>";
                echo "<p>耗时: " . ($endTime - $startTime) . " 秒</p>";
                
                // 清理测试文件
                unlink($outputFile);
            } else {
                echo "<p>❌ 录制文件生成失败</p>";
            }
        } else {
            echo "<p>❌ FFmpeg执行失败，返回码: {$returnCode}</p>";
            echo "<p>错误输出:</p>";
            echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
        }
    } else {
        echo "<p>❌ 无法测试录制，FFmpeg未找到</p>";
    }
    
    // 辅助函数
    function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>文件: " . $e->getFile() . "</p>";
    echo "<p>行号: " . $e->getLine() . "</p>";
}
?>
