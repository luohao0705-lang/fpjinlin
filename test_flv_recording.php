<?php
/**
 * FLV录制测试页面
 * 用于测试FFmpeg录制功能
 */
require_once 'config/config.php';
require_once 'config/database.php';

// 启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查管理员登录
if (!isset($_SESSION['admin_id'])) {
    die('请先登录管理员账户');
}

$message = '';
$testResult = '';

if ($_POST) {
    $flvUrl = $_POST['flv_url'] ?? '';
    $duration = intval($_POST['duration'] ?? 30);
    
    if (empty($flvUrl)) {
        $message = '请输入FLV地址';
    } else {
        try {
            require_once 'includes/classes/VideoProcessor.php';
            $videoProcessor = new VideoProcessor();
            
            // 测试录制
            $tempFile = sys_get_temp_dir() . '/test_recording_' . time() . '.mp4';
            
            // 直接调用FFmpeg命令
            $command = sprintf(
                'ffmpeg -i %s -t %d -c:v libx264 -preset fast -crf 23 -c:a aac -ac 2 -ar 44100 -movflags +faststart %s -y',
                escapeshellarg($flvUrl),
                $duration,
                escapeshellarg($tempFile)
            );
            
            $output = [];
            $returnCode = 0;
            exec($command . ' 2>&1', $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($tempFile) && filesize($tempFile) > 0) {
                $fileSize = filesize($tempFile);
                $testResult = "✅ 录制成功！\n";
                $testResult .= "文件大小: " . round($fileSize / 1024 / 1024, 2) . " MB\n";
                $testResult .= "文件路径: {$tempFile}\n";
                $testResult .= "FFmpeg输出:\n" . implode("\n", array_slice($output, 0, 10));
                
                // 清理测试文件
                unlink($tempFile);
            } else {
                $testResult = "❌ 录制失败！\n";
                $testResult .= "返回码: {$returnCode}\n";
                $testResult .= "FFmpeg输出:\n" . implode("\n", $output);
            }
            
        } catch (Exception $e) {
            $testResult = "❌ 录制异常: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FLV录制测试 - 复盘精灵</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-video me-2"></i>FLV录制测试</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="flv_url" class="form-label">FLV地址</label>
                                <input type="url" class="form-control" id="flv_url" name="flv_url" 
                                       value="<?php echo htmlspecialchars($_POST['flv_url'] ?? ''); ?>" 
                                       placeholder="请输入FLV流地址，例如：http://example.com/stream.flv" required>
                                <div class="form-text">请输入有效的FLV流地址进行测试</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="duration" class="form-label">录制时长（秒）</label>
                                <input type="number" class="form-control" id="duration" name="duration" 
                                       value="<?php echo htmlspecialchars($_POST['duration'] ?? '30'); ?>" 
                                       min="5" max="300" required>
                                <div class="form-text">建议5-60秒，避免录制时间过长</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-play me-2"></i>开始测试录制
                            </button>
                            
                            <a href="admin/video_order_detail.php?id=16" class="btn btn-secondary ms-2">
                                <i class="fas fa-arrow-left me-2"></i>返回订单详情
                            </a>
                        </form>
                        
                        <?php if ($testResult): ?>
                            <div class="mt-4">
                                <h5>测试结果</h5>
                                <pre class="bg-light p-3 rounded"><?php echo htmlspecialchars($testResult); ?></pre>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <h5>系统信息</h5>
                            <ul class="list-group">
                                <li class="list-group-item">
                                    <strong>临时目录:</strong> <?php echo sys_get_temp_dir(); ?>
                                    <?php if (is_writable(sys_get_temp_dir())): ?>
                                        <span class="badge bg-success ms-2">可写</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger ms-2">不可写</span>
                                    <?php endif; ?>
                                </li>
                                <li class="list-group-item">
                                    <strong>FFmpeg状态:</strong>
                                    <?php
                                    $ffmpegOutput = [];
                                    $ffmpegReturnCode = 0;
                                    $ffmpegFound = false;
                                    
                                    // 尝试不同的FFmpeg命令（Linux优先）
                                    $ffmpegCommands = ['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', 'ffmpeg.exe'];
                                    
                                    foreach ($ffmpegCommands as $cmd) {
                                        exec($cmd . ' -version 2>&1', $ffmpegOutput, $ffmpegReturnCode);
                                        if ($ffmpegReturnCode === 0) {
                                            $ffmpegFound = true;
                                            break;
                                        }
                                    }
                                    
                                    if ($ffmpegFound): ?>
                                        <span class="badge bg-success ms-2">已安装</span>
                                        <small class="text-muted"><?php echo htmlspecialchars($ffmpegOutput[0] ?? ''); ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-danger ms-2">未安装</span>
                                        <small class="text-muted">请安装FFmpeg并确保在PATH中</small>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
