<?php
/**
 * 测试真实视频录制
 * 使用真实的视频源进行测试
 */

require_once 'config/database.php';
require_once 'SimpleRecorder.php';

echo "🎬 真实视频录制测试\n";
echo "==================\n\n";

// 使用一些公开的视频源进行测试
$testVideos = [
    [
        'name' => 'Big Buck Bunny (MP4)',
        'url' => 'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4',
        'duration' => 10
    ],
    [
        'name' => 'Test Video (WebM)',
        'url' => 'https://www.learningcontainer.com/wp-content/uploads/2020/05/sample-mp4-file.mp4',
        'duration' => 15
    ],
    [
        'name' => 'Sample Video (MP4)',
        'url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
        'duration' => 20
    ]
];

try {
    $db = new Database();
    $recorder = new SimpleRecorder();
    
    foreach ($testVideos as $index => $video) {
        echo "测试视频 " . ($index + 1) . ": {$video['name']}\n";
        echo "URL: {$video['url']}\n";
        echo "测试时长: {$video['duration']}秒\n";
        echo "==================\n";
        
        // 创建测试订单
        $orderId = 900 + $index;
        
        // 开始录制
        $result = $recorder->recordVideo($orderId, $video['url'], $video['duration']);
        
        if ($result['success']) {
            echo "✅ 录制成功！\n";
            echo "文件路径: {$result['file_path']}\n";
            echo "文件大小: " . $recorder->formatBytes($result['file_size']) . "\n";
            echo "视频时长: {$result['duration']}秒\n";
            
            // 检查文件是否真的存在
            if (file_exists($result['file_path'])) {
                echo "✅ 文件确实存在\n";
                
                // 尝试获取视频信息
                $info = $recorder->getVideoInfo($result['file_path']);
                if ($info) {
                    echo "视频信息: {$info['width']}x{$info['height']}, {$info['duration']}秒\n";
                }
            } else {
                echo "❌ 文件不存在\n";
            }
            
        } else {
            echo "❌ 录制失败: {$result['error']}\n";
        }
        
        // 清理
        $recorder->cleanup($orderId);
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
}
?>
