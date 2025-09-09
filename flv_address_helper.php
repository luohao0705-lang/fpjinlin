<?php
/**
 * FLV地址获取帮助页面
 * 复盘精灵系统 - 帮助用户获取有效的FLV地址
 */
require_once 'config/config.php';
require_once 'config/database.php';

// 启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查管理员登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin/login.php');
    exit;
}

$message = '';
$error = '';

// 处理FLV地址测试
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_flv'])) {
    $flvUrl = trim($_POST['flv_url']);
    
    if (empty($flvUrl)) {
        $error = '请输入FLV地址';
    } else {
        // 测试FLV地址
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'HEAD',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $headers = @get_headers($flvUrl, 1, $context);
        if ($headers) {
            $statusCode = $headers[0];
            if (strpos($statusCode, '200') !== false) {
                $message = '✅ FLV地址有效，可以录制';
            } elseif (strpos($statusCode, '404') !== false) {
                $error = '❌ FLV地址已过期（404错误），请重新获取';
            } elseif (strpos($statusCode, '403') !== false) {
                $error = '❌ FLV地址被拒绝访问（403错误），可能需要更新';
            } else {
                $error = '❌ FLV地址状态异常：' . $statusCode;
            }
        } else {
            $error = '❌ 无法访问FLV地址，请检查网络连接';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FLV地址获取帮助 - 复盘精灵</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- 侧边栏 -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="admin/index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>仪表盘
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin/video_orders.php">
                                <i class="fas fa-video me-2"></i>视频分析订单
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="#">
                                <i class="fas fa-question-circle me-2"></i>FLV地址帮助
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- 主要内容区域 -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">FLV地址获取帮助</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-link me-2"></i>FLV地址测试
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="flv_url" class="form-label">FLV地址</label>
                                        <input type="url" class="form-control" id="flv_url" name="flv_url" 
                                               placeholder="http://pull-flv-xxx.douyincdn.com/stage/stream-xxx.flv" 
                                               value="<?php echo htmlspecialchars($_POST['flv_url'] ?? ''); ?>" required>
                                        <div class="form-text">请输入完整的FLV地址进行测试</div>
                                    </div>
                                    <button type="submit" name="test_flv" class="btn btn-primary">
                                        <i class="fas fa-test-tube me-2"></i>测试FLV地址
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>获取FLV地址的方法
                                </h5>
                            </div>
                            <div class="card-body">
                                <h6>抖音直播FLV地址获取：</h6>
                                <ol>
                                    <li><strong>使用浏览器开发者工具：</strong>
                                        <ul>
                                            <li>打开抖音直播页面</li>
                                            <li>按F12打开开发者工具</li>
                                            <li>切换到"Network"标签</li>
                                            <li>刷新页面，在请求中查找包含"flv"的链接</li>
                                            <li>复制完整的FLV地址</li>
                                        </ul>
                                    </li>
                                    <li><strong>使用第三方工具：</strong>
                                        <ul>
                                            <li>使用直播录制工具（如youtube-dl）</li>
                                            <li>使用浏览器插件获取直播流地址</li>
                                        </ul>
                                    </li>
                                </ol>

                                <h6 class="mt-4">注意事项：</h6>
                                <ul>
                                    <li>FLV地址通常有<strong>有效期限制</strong>（几分钟到几小时）</li>
                                    <li>直播结束后，FLV地址会失效</li>
                                    <li>需要<strong>实时获取</strong>，不能使用过期的地址</li>
                                    <li>建议在直播进行中获取FLV地址</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-lightbulb me-2"></i>常见问题
                                </h5>
                            </div>
                            <div class="card-body">
                                <h6>Q: 为什么录制失败？</h6>
                                <p class="small">A: 最常见的原因是FLV地址已过期，请重新获取有效的地址。</p>

                                <h6>Q: 如何知道FLV地址是否有效？</h6>
                                <p class="small">A: 使用上面的测试功能，或者检查地址是否包含当前时间戳。</p>

                                <h6>Q: 录制多长时间？</h6>
                                <p class="small">A: 系统默认录制30秒，可在后台配置中修改。</p>

                                <h6>Q: 支持哪些直播平台？</h6>
                                <p class="small">A: 目前主要支持抖音直播，其他平台需要测试。</p>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-tools me-2"></i>快速操作
                                </h5>
                            </div>
                            <div class="card-body">
                                <a href="admin/video_orders.php" class="btn btn-outline-primary btn-sm w-100 mb-2">
                                    <i class="fas fa-list me-2"></i>查看所有订单
                                </a>
                                <a href="admin/system_config.php" class="btn btn-outline-secondary btn-sm w-100">
                                    <i class="fas fa-cog me-2"></i>系统配置
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
