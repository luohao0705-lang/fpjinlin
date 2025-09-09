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
            // 首先检查FFmpeg是否可用
            $ffmpegCheck = [];
            $ffmpegReturnCode = 0;
            exec('ffmpeg -version 2>&1', $ffmpegCheck, $ffmpegReturnCode);
            
            if ($ffmpegReturnCode !== 0) {
                throw new Exception('FFmpeg不可用，返回码: ' . $ffmpegReturnCode);
            }
            
            // 检查FLV地址是否可访问（更宽松的检查）
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'method' => 'HEAD',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);
            
            $headers = @get_headers($flvUrl, 1, $context);
            $isAccessible = false;
            
            if ($headers) {
                $statusCode = $headers[0];
                // 接受200、302、403等状态码（有些流需要特殊处理）
                if (strpos($statusCode, '200') !== false || 
                    strpos($statusCode, '302') !== false || 
                    strpos($statusCode, '403') !== false) {
                    $isAccessible = true;
                }
            }
            
            // 如果HEAD请求失败，尝试GET请求（只获取少量数据）
            if (!$isAccessible) {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 5,
                        'method' => 'GET',
                        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                    ]
                ]);
                
                $testData = @file_get_contents($flvUrl, false, $context, 0, 1024);
                if ($testData !== false && strlen($testData) > 0) {
                    $isAccessible = true;
                }
            }
            
            if (!$isAccessible) {
                error_log("⚠️ FLV地址检查失败，但继续尝试录制: {$flvUrl}");
                // 不抛出异常，继续尝试录制
            }
            
            // 测试录制
            $tempFile = sys_get_temp_dir() . '/test_recording_' . time() . '.mp4';
            
            // 直接调用FFmpeg命令（针对抖音FLV流优化）
            $command = sprintf(
                'ffmpeg -user_agent "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" -headers "Referer: https://live.douyin.com/" -i %s -t %d -c:v libx264 -preset fast -crf 23 -c:a aac -ac 2 -ar 44100 -movflags +faststart -avoid_negative_ts make_zero -fflags +genpts %s -y',
                escapeshellarg($flvUrl),
                $duration,
                escapeshellarg($tempFile)
            );
            
            $testResult = "🔧 执行命令: {$command}\n\n";
            
            $output = [];
            $returnCode = 0;
            exec($command . ' 2>&1', $output, $returnCode);
            
            $testResult .= "📊 执行结果:\n";
            $testResult .= "返回码: {$returnCode}\n";
            $testResult .= "输出:\n" . implode("\n", $output) . "\n\n";
            
            if ($returnCode === 0 && file_exists($tempFile) && filesize($tempFile) > 0) {
                $fileSize = filesize($tempFile);
                $testResult .= "✅ 录制成功！\n";
                $testResult .= "文件大小: " . round($fileSize / 1024 / 1024, 2) . " MB\n";
                $testResult .= "文件路径: {$tempFile}\n";
                
                // 获取视频信息
                $infoOutput = [];
                $infoReturnCode = 0;
                exec("ffprobe -v quiet -print_format json -show_format -show_streams " . escapeshellarg($tempFile) . " 2>&1", $infoOutput, $infoReturnCode);
                
                if ($infoReturnCode === 0) {
                    $videoInfo = json_decode(implode('', $infoOutput), true);
                    if ($videoInfo) {
                        $testResult .= "视频时长: " . ($videoInfo['format']['duration'] ?? '未知') . " 秒\n";
                        $testResult .= "视频编码: " . ($videoInfo['streams'][0]['codec_name'] ?? '未知') . "\n";
                        $testResult .= "分辨率: " . ($videoInfo['streams'][0]['width'] ?? '未知') . "x" . ($videoInfo['streams'][0]['height'] ?? '未知') . "\n";
                    }
                }
                
                // 清理测试文件
                unlink($tempFile);
            } else {
                $testResult .= "❌ 录制失败！\n";
                if (!file_exists($tempFile)) {
                    $testResult .= "错误: 输出文件未生成\n";
                } elseif (filesize($tempFile) === 0) {
                    $testResult .= "错误: 输出文件为空\n";
                }
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
                            
                            <a href="flv_helper.php" class="btn btn-info ms-2">
                                <i class="fas fa-question-circle me-2"></i>FLV地址获取帮助
                            </a>
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
                                    try {
                                        $ffmpegOutput = [];
                                        $ffmpegReturnCode = 0;
                                        $ffmpegFound = false;
                                        $ffmpegError = '';
                                        
                                        // 尝试不同的FFmpeg命令（Linux优先）
                                        $ffmpegCommands = ['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', 'ffmpeg.exe'];
                                        
                                        foreach ($ffmpegCommands as $cmd) {
                                            exec($cmd . ' -version 2>&1', $ffmpegOutput, $returnCode);
                                            if ($returnCode === 0) {
                                                $ffmpegFound = true;
                                                break;
                                            } else {
                                                $ffmpegError .= "命令: {$cmd}, 返回码: {$returnCode}, 输出: " . implode(' ', $ffmpegOutput) . "\n";
                                            }
                                        }
                                        
                                        if ($ffmpegFound): ?>
                                            <span class="badge bg-success ms-2">已安装</span>
                                            <small class="text-muted"><?php echo htmlspecialchars($ffmpegOutput[0] ?? ''); ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-danger ms-2">未安装</span>
                                            <small class="text-muted">请安装FFmpeg并确保在PATH中</small>
                                            <br><small class="text-danger">错误详情: <?php echo htmlspecialchars($ffmpegError); ?></small>
                                        <?php endif; ?>
                                    <?php } catch (Exception $e) { ?>
                                        <span class="badge bg-danger ms-2">检查失败</span>
                                        <small class="text-danger">错误: <?php echo htmlspecialchars($e->getMessage()); ?></small>
                                    <?php } ?>
                                </li>
                                
                                <li class="list-group-item">
                                    <strong>系统诊断:</strong>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <?php try { ?>
                                                <strong>PATH环境变量:</strong> <?php echo htmlspecialchars(getenv('PATH')); ?><br>
                                                <strong>当前工作目录:</strong> <?php echo htmlspecialchars(getcwd()); ?><br>
                                                <strong>PHP版本:</strong> <?php echo PHP_VERSION; ?><br>
                                                <strong>禁用函数:</strong> <?php echo ini_get('disable_functions') ?: '无'; ?><br>
                                                <strong>shell_exec测试:</strong> 
                                                <?php 
                                                $shellResult = shell_exec('whoami 2>&1');
                                                echo $shellResult ? '正常' : '失败';
                                                ?>
                                            <?php } catch (Exception $e) { ?>
                                                <span class="text-danger">诊断失败: <?php echo htmlspecialchars($e->getMessage()); ?></span>
                                            <?php } ?>
                                        </small>
                                    </div>
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
