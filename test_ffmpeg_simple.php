<?php
/**
 * 简单的FFmpeg测试页面
 */
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FFmpeg简单测试 - 复盘精灵</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4>FFmpeg简单测试</h4>
                    </div>
                    <div class="card-body">
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
                                $output = [];
                                $returnCode = 0;
                                exec('ffmpeg -version 2>&1', $output, $returnCode);
                                if ($returnCode === 0): ?>
                                    <span class="badge bg-success ms-2">已安装</span>
                                    <small class="text-muted"><?php echo htmlspecialchars($output[0] ?? ''); ?></small>
                                <?php else: ?>
                                    <span class="badge bg-danger ms-2">未安装</span>
                                    <small class="text-danger">返回码: <?php echo $returnCode; ?></small>
                                <?php endif; ?>
                            </li>
                            <li class="list-group-item">
                                <strong>PHP版本:</strong> <?php echo PHP_VERSION; ?>
                            </li>
                            <li class="list-group-item">
                                <strong>禁用函数:</strong> <?php echo ini_get('disable_functions') ?: '无'; ?>
                            </li>
                        </ul>
                        
                        <h5 class="mt-4">测试录制</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="flv_url" class="form-label">FLV地址</label>
                                <input type="url" class="form-control" id="flv_url" name="flv_url" 
                                       value="<?php echo htmlspecialchars($_POST['flv_url'] ?? ''); ?>" 
                                       placeholder="请输入FLV流地址" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="duration" class="form-label">录制时长（秒）</label>
                                <input type="number" class="form-control" id="duration" name="duration" 
                                       value="<?php echo htmlspecialchars($_POST['duration'] ?? '30'); ?>" 
                                       min="5" max="300" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">开始测试</button>
                        </form>
                        
                        <?php if ($_POST): ?>
                            <div class="mt-4">
                                <h5>测试结果</h5>
                                <pre class="bg-light p-3 rounded"><?php
                                $flvUrl = $_POST['flv_url'] ?? '';
                                $duration = intval($_POST['duration'] ?? 30);
                                
                                if (empty($flvUrl)) {
                                    echo "请输入FLV地址";
                                } else {
                                    echo "开始测试录制...\n";
                                    echo "FLV地址: {$flvUrl}\n";
                                    echo "录制时长: {$duration}秒\n\n";
                                    
                                    $tempFile = sys_get_temp_dir() . '/test_' . time() . '.mp4';
                                    $command = "ffmpeg -i " . escapeshellarg($flvUrl) . " -t {$duration} -c:v libx264 -preset fast -crf 23 -c:a aac -ac 2 -ar 44100 -movflags +faststart " . escapeshellarg($tempFile) . " -y 2>&1";
                                    
                                    echo "执行命令: {$command}\n\n";
                                    
                                    $output = [];
                                    $returnCode = 0;
                                    exec($command, $output, $returnCode);
                                    
                                    echo "返回码: {$returnCode}\n";
                                    echo "输出:\n" . implode("\n", $output) . "\n\n";
                                    
                                    if ($returnCode === 0 && file_exists($tempFile) && filesize($tempFile) > 0) {
                                        $fileSize = filesize($tempFile);
                                        echo "✅ 录制成功！\n";
                                        echo "文件大小: " . round($fileSize / 1024 / 1024, 2) . " MB\n";
                                        echo "文件路径: {$tempFile}\n";
                                        unlink($tempFile);
                                    } else {
                                        echo "❌ 录制失败！\n";
                                        if (!file_exists($tempFile)) {
                                            echo "错误: 输出文件未生成\n";
                                        } elseif (filesize($tempFile) === 0) {
                                            echo "错误: 输出文件为空\n";
                                        }
                                    }
                                }
                                ?></pre>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
