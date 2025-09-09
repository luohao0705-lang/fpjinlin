<?php
/**
 * FLV地址测试工具
 * 帮助用户测试FLV地址是否有效
 */

require_once 'config/config.php';
require_once 'config/database.php';

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FLV地址测试工具</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .test-result {
            margin-top: 20px;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        .code-block {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 10px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-video me-2"></i>FLV地址测试工具</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="testForm">
                            <div class="mb-3">
                                <label for="flvUrl" class="form-label">FLV地址</label>
                                <input type="url" class="form-control" id="flvUrl" name="flv_url" 
                                       placeholder="请输入FLV地址，例如：http://pull-flv-l26.douyincdn.com/stage/stream-xxx.flv"
                                       value="<?php echo isset($_POST['flv_url']) ? htmlspecialchars($_POST['flv_url']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="testDuration" class="form-label">测试时长（秒）</label>
                                <input type="number" class="form-control" id="testDuration" name="test_duration" 
                                       value="10" min="1" max="60">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-play me-2"></i>开始测试
                            </button>
                        </form>

                        <?php if (isset($_POST['flv_url']) && !empty($_POST['flv_url'])): ?>
                            <div class="test-result">
                                <h5>测试结果</h5>
                                <?php
                                $flvUrl = $_POST['flv_url'];
                                $testDuration = intval($_POST['test_duration'] ?? 10);
                                
                                echo "<div class='info'><i class='fas fa-info-circle me-2'></i>正在测试FLV地址...</div>";
                                echo "<div class='code-block'>测试地址: {$flvUrl}\n测试时长: {$testDuration}秒</div>";
                                
                                // 测试FLV地址
                                $testResult = testFlvAddress($flvUrl, $testDuration);
                                
                                if ($testResult['success']) {
                                    echo "<div class='alert alert-success'><i class='fas fa-check-circle me-2'></i>✅ FLV地址有效！</div>";
                                    echo "<div class='code-block'>{$testResult['output']}</div>";
                                } else {
                                    echo "<div class='alert alert-danger'><i class='fas fa-exclamation-circle me-2'></i>❌ FLV地址无效或已过期</div>";
                                    echo "<div class='code-block'>{$testResult['error']}</div>";
                                }
                                ?>
                            </div>
                        <?php endif; ?>

                        <div class="mt-4">
                            <h6>使用说明</h6>
                            <ul>
                                <li>将FLV地址粘贴到输入框中</li>
                                <li>设置测试时长（建议10-30秒）</li>
                                <li>点击"开始测试"按钮</li>
                                <li>查看测试结果，确认地址是否有效</li>
                            </ul>
                            
                            <h6>常见问题</h6>
                            <ul>
                                <li><strong>404 Not Found:</strong> FLV地址已过期，需要重新获取</li>
                                <li><strong>Connection refused:</strong> 网络连接问题或服务器不可达</li>
                                <li><strong>timeout:</strong> 连接超时，直播可能已结束</li>
                                <li><strong>返回码255:</strong> 通常是FLV地址过期或参数错误</li>
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

<?php
function testFlvAddress($flvUrl, $duration) {
    $outputFile = sys_get_temp_dir() . '/test_flv_' . time() . '.mp4';
    
    // 构建FFmpeg命令
    $command = sprintf(
        'ffmpeg -user_agent "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" -headers "Referer: https://live.douyin.com/" -i %s -t %d -c:v libx264 -preset fast -crf 23 -c:a aac -ac 2 -ar 44100 -movflags +faststart -avoid_negative_ts make_zero -fflags +genpts %s -y 2>&1',
        escapeshellarg($flvUrl),
        $duration,
        escapeshellarg($outputFile)
    );
    
    error_log("🧪 测试FLV地址: {$flvUrl}");
    error_log("🔧 执行命令: {$command}");
    
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    
    $result = [
        'success' => false,
        'output' => '',
        'error' => ''
    ];
    
    if ($returnCode === 0) {
        $result['success'] = true;
        $result['output'] = implode("\n", $output);
        
        // 检查输出文件
        if (file_exists($outputFile) && filesize($outputFile) > 0) {
            $fileSize = filesize($outputFile);
            $result['output'] .= "\n\n✅ 录制成功！\n文件大小: " . formatFileSize($fileSize);
        } else {
            $result['success'] = false;
            $result['error'] = "录制完成但文件为空或不存在";
        }
        
        // 清理测试文件
        if (file_exists($outputFile)) {
            unlink($outputFile);
        }
    } else {
        $result['success'] = false;
        $result['error'] = implode("\n", $output);
    }
    
    return $result;
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>
