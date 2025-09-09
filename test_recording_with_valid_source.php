<?php
// 使用有效视频源测试录制
require_once 'config/config.php';

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>录制测试（使用有效视频源）</h2>";

try {
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
    
    // 方法2: 直接测试命令
    if (!$ffmpegFound) {
        $testOutput = [];
        $testCode = 0;
        exec("{$ffmpegPath} -version 2>&1", $testOutput, $testCode);
        if ($testCode === 0 && !empty($testOutput)) {
            $ffmpegFound = true;
            $ffmpegLocation = $ffmpegPath;
        }
    }
    
    // 方法3: 常见路径
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
        
        // 使用一个公开的测试视频源
        $testVideoUrl = 'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4';
        $maxDuration = 5; // 只录制5秒用于测试
        $outputFile = sys_get_temp_dir() . '/test_recording_' . time() . '.mp4';
        
        echo "<p>使用测试视频源: " . htmlspecialchars($testVideoUrl) . "</p>";
        echo "<p>输出文件: " . htmlspecialchars($outputFile) . "</p>";
        
        $command = sprintf(
            '%s -i %s -t %d -c:v libx264 -preset fast -crf 23 -c:a aac -ac 2 -ar 44100 -movflags +faststart %s -y',
            escapeshellarg($ffmpegLocation),
            escapeshellarg($testVideoUrl),
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
                
                // 获取视频信息
                $infoCommand = sprintf(
                    '%s -i %s 2>&1 | grep -E "(Duration|Stream)"',
                    escapeshellarg($ffmpegLocation),
                    escapeshellarg($outputFile)
                );
                
                $infoOutput = [];
                exec($infoCommand, $infoOutput);
                if (!empty($infoOutput)) {
                    echo "<p>视频信息:</p>";
                    echo "<pre>" . htmlspecialchars(implode("\n", $infoOutput)) . "</pre>";
                }
                
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
        
        // 现在测试FLV录制（使用一个模拟的FLV源）
        echo "<h3>测试FLV录制功能</h3>";
        
        // 创建一个简单的测试视频作为FLV源
        $testFlvFile = sys_get_temp_dir() . '/test_source_' . time() . '.mp4';
        $testFlvOutput = sys_get_temp_dir() . '/test_flv_output_' . time() . '.mp4';
        
        // 生成一个简单的测试视频
        $generateCommand = sprintf(
            '%s -f lavfi -i testsrc=duration=10:size=640x480:rate=30 -c:v libx264 -preset fast %s -y',
            escapeshellarg($ffmpegLocation),
            escapeshellarg($testFlvFile)
        );
        
        echo "<p>生成测试视频...</p>";
        exec($generateCommand . ' 2>&1', $generateOutput, $generateCode);
        
        if ($generateCode === 0 && file_exists($testFlvFile)) {
            echo "<p>✅ 测试视频生成成功</p>";
            
            // 测试从本地文件录制
            $recordCommand = sprintf(
                '%s -i %s -t 5 -c:v libx264 -preset fast -crf 23 -c:a aac -ac 2 -ar 44100 -movflags +faststart %s -y',
                escapeshellarg($ffmpegLocation),
                escapeshellarg($testFlvFile),
                escapeshellarg($testFlvOutput)
            );
            
            echo "<p>测试录制命令: " . htmlspecialchars($recordCommand) . "</p>";
            
            $recordStartTime = time();
            exec($recordCommand . ' 2>&1', $recordOutput, $recordCode);
            $recordEndTime = time();
            
            if ($recordCode === 0 && file_exists($testFlvOutput) && filesize($testFlvOutput) > 0) {
                echo "<p>✅ FLV录制功能正常！</p>";
                echo "<p>录制文件大小: " . formatFileSize(filesize($testFlvOutput)) . "</p>";
                echo "<p>录制耗时: " . ($recordEndTime - $recordStartTime) . " 秒</p>";
            } else {
                echo "<p>❌ FLV录制失败，返回码: {$recordCode}</p>";
                echo "<p>错误输出:</p>";
                echo "<pre>" . htmlspecialchars(implode("\n", $recordOutput)) . "</pre>";
            }
            
            // 清理测试文件
            if (file_exists($testFlvFile)) unlink($testFlvFile);
            if (file_exists($testFlvOutput)) unlink($testFlvOutput);
        } else {
            echo "<p>❌ 无法生成测试视频</p>";
        }
        
    } else {
        echo "<p>❌ FFmpeg 未找到</p>";
        echo "<p>请安装FFmpeg或检查路径配置</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>文件: " . $e->getFile() . "</p>";
    echo "<p>行号: " . $e->getLine() . "</p>";
}

// 辅助函数（检查是否已存在）
if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
?>
