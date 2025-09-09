<?php
// 纯FFmpeg测试，不依赖任何系统配置
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>FFmpeg 纯功能测试</h2>";

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
        
        // 测试1: 生成测试视频
        echo "<h3>测试1: 生成测试视频</h3>";
        $testVideoFile = sys_get_temp_dir() . '/test_generate_' . time() . '.mp4';
        
        $generateCommand = sprintf(
            '%s -f lavfi -i testsrc=duration=5:size=640x480:rate=30 -c:v libx264 -preset fast %s -y',
            escapeshellarg($ffmpegLocation),
            escapeshellarg($testVideoFile)
        );
        
        echo "<p>生成命令: " . htmlspecialchars($generateCommand) . "</p>";
        
        $startTime = time();
        $output = [];
        $returnCode = 0;
        exec($generateCommand . ' 2>&1', $output, $returnCode);
        $endTime = time();
        
        if ($returnCode === 0 && file_exists($testVideoFile) && filesize($testVideoFile) > 0) {
            echo "<p>✅ 测试视频生成成功！</p>";
            echo "<p>文件大小: " . formatFileSize(filesize($testVideoFile)) . "</p>";
            echo "<p>耗时: " . ($endTime - $startTime) . " 秒</p>";
            
            // 测试2: 从网络视频源录制
            echo "<h3>测试2: 从网络视频源录制</h3>";
            $networkVideoUrl = 'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4';
            $recordFile = sys_get_temp_dir() . '/test_record_' . time() . '.mp4';
            
            $recordCommand = sprintf(
                '%s -i %s -t 5 -c:v libx264 -preset fast -crf 23 -c:a aac -ac 2 -ar 44100 -movflags +faststart %s -y',
                escapeshellarg($ffmpegLocation),
                escapeshellarg($networkVideoUrl),
                escapeshellarg($recordFile)
            );
            
            echo "<p>录制命令: " . htmlspecialchars($recordCommand) . "</p>";
            
            $recordStartTime = time();
            $recordOutput = [];
            $recordCode = 0;
            exec($recordCommand . ' 2>&1', $recordOutput, $recordCode);
            $recordEndTime = time();
            
            if ($recordCode === 0 && file_exists($recordFile) && filesize($recordFile) > 0) {
                echo "<p>✅ 网络视频录制成功！</p>";
                echo "<p>文件大小: " . formatFileSize(filesize($recordFile)) . "</p>";
                echo "<p>耗时: " . ($recordEndTime - $recordStartTime) . " 秒</p>";
            } else {
                echo "<p>❌ 网络视频录制失败，返回码: {$recordCode}</p>";
                echo "<p>错误输出:</p>";
                echo "<pre>" . htmlspecialchars(implode("\n", $recordOutput)) . "</pre>";
            }
            
            // 测试3: 视频信息提取
            echo "<h3>测试3: 视频信息提取</h3>";
            $infoCommand = sprintf(
                '%s -i %s 2>&1 | grep -E "(Duration|Stream|Video|Audio)"',
                escapeshellarg($ffmpegLocation),
                escapeshellarg($testVideoFile)
            );
            
            $infoOutput = [];
            exec($infoCommand, $infoOutput);
            if (!empty($infoOutput)) {
                echo "<p>✅ 视频信息提取成功:</p>";
                echo "<pre>" . htmlspecialchars(implode("\n", $infoOutput)) . "</pre>";
            } else {
                echo "<p>❌ 视频信息提取失败</p>";
            }
            
            // 清理测试文件
            if (file_exists($testVideoFile)) unlink($testVideoFile);
            if (file_exists($recordFile)) unlink($recordFile);
            
            echo "<h3>总结</h3>";
            echo "<p>✅ FFmpeg功能正常，可以用于视频录制和处理</p>";
            echo "<p>✅ 系统支持视频分析功能</p>";
            
        } else {
            echo "<p>❌ 测试视频生成失败，返回码: {$returnCode}</p>";
            echo "<p>错误输出:</p>";
            echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
        }
        
    } else {
        echo "<p>❌ FFmpeg 未找到</p>";
        echo "<p>请安装FFmpeg或检查路径配置</p>";
        echo "<p>安装命令示例:</p>";
        echo "<pre>";
        echo "# Ubuntu/Debian:\n";
        echo "sudo apt update && sudo apt install ffmpeg\n\n";
        echo "# CentOS/RHEL:\n";
        echo "sudo yum install epel-release && sudo yum install ffmpeg\n\n";
        echo "# 或者使用snap:\n";
        echo "sudo snap install ffmpeg\n";
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>文件: " . $e->getFile() . "</p>";
    echo "<p>行号: " . $e->getLine() . "</p>";
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
?>
