<?php
/**
 * 视频分析订单详情页面
 * 复盘精灵系统 - 后台管理
 */
require_once '../config/config.php';
require_once '../config/database.php';

// 检查管理员登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$orderId = intval($_GET['id'] ?? 0);
if (!$orderId) {
    header('Location: video_orders.php');
    exit;
}

$db = new Database();

// 获取订单信息
$order = $db->fetchOne(
    "SELECT vao.*, u.phone as user_phone, u.nickname as user_nickname 
     FROM video_analysis_orders vao 
     LEFT JOIN users u ON vao.user_id = u.id 
     WHERE vao.id = ?",
    [$orderId]
);

if (!$order) {
    header('Location: video_orders.php');
    exit;
}

// 获取视频文件信息
$videoFiles = $db->fetchAll(
    "SELECT * FROM video_files WHERE order_id = ? ORDER BY video_type, video_index",
    [$orderId]
);

// 获取处理进度
$processingTasks = $db->fetchAll(
    "SELECT * FROM video_processing_queue WHERE order_id = ? ORDER BY created_at DESC",
    [$orderId]
);

// 处理FLV地址更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_flv_addresses') {
        $flvAddresses = $_POST['flv_addresses'] ?? [];
        
        $db->beginTransaction();
        try {
            foreach ($flvAddresses as $videoFileId => $flvUrl) {
                if (!empty($flvUrl)) {
                    $db->query(
                        "UPDATE video_files SET flv_url = ? WHERE id = ? AND order_id = ?",
                        [$flvUrl, $videoFileId, $orderId]
                    );
                }
            }
            
            $db->commit();
            $message = 'FLV地址更新成功';
        } catch (Exception $e) {
            $db->rollback();
            $error = '更新失败：' . $e->getMessage();
        }
    } elseif ($action === 'start_analysis') {
        // 启动分析
        try {
            $videoAnalysisOrder = new VideoAnalysisOrder();
            $result = $videoAnalysisOrder->startAnalysis($orderId);
            $message = '分析已启动';
        } catch (Exception $e) {
            $error = '启动分析失败：' . $e->getMessage();
        }
    } elseif ($action === 'stop_analysis') {
        // 停止分析
        try {
            $videoAnalysisOrder = new VideoAnalysisOrder();
            $result = $videoAnalysisOrder->stopAnalysis($orderId);
            $message = '分析已停止';
        } catch (Exception $e) {
            $error = '停止分析失败：' . $e->getMessage();
        }
    }
}

// 获取AI服务额度信息
$apiQuotas = [
    'deepseek' => getApiQuota('deepseek'),
    'qwen_omni' => getApiQuota('qwen_omni'),
    'whisper' => getApiQuota('whisper')
];

function getApiQuota($service) {
    // 这里应该调用各服务的API获取额度信息
    // 暂时返回模拟数据
    return [
        'used' => rand(100, 1000),
        'total' => 10000,
        'remaining' => 9000,
        'percentage' => 10
    ];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>视频分析订单详情 - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- 自定义样式 -->
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: #adb5bd;
            padding: 0.75rem 1rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: #495057;
        }
        .main-content {
            background-color: #f8f9fa;
            min-height: calc(100vh - 56px);
        }
        .video-link-card {
            border-left: 4px solid #007bff;
        }
        .flv-address-card {
            border-left: 4px solid #28a745;
        }
        .progress-card {
            border-left: 4px solid #ffc107;
        }
        .quota-card {
            border-left: 4px solid #17a2b8;
        }
    </style>
</head>
<body>
    <!-- 顶部导航 -->
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-video me-2"></i><?php echo APP_NAME; ?> 管理后台
            </a>
            
            <div class="navbar-nav flex-row">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-light" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-shield me-1"></i>管理员
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../index.php" target="_blank"><i class="fas fa-external-link-alt me-2"></i>前台首页</a></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>退出登录</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- 侧边栏 -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>仪表盘
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i>用户管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="fas fa-file-text me-2"></i>文本分析订单
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="video_orders.php">
                                <i class="fas fa-video me-2"></i>视频分析订单
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="exchange_codes.php">
                                <i class="fas fa-ticket-alt me-2"></i>兑换码管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="system_config.php">
                                <i class="fas fa-cogs me-2"></i>系统配置
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- 主要内容区域 -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="pt-3 pb-2 mb-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1 class="h2">视频分析订单详情</h1>
                        <a href="video_orders.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>返回列表
                        </a>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- 订单基本信息 -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>订单信息</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>订单号：</strong><?php echo htmlspecialchars($order['order_no']); ?></p>
                                        <p><strong>用户：</strong><?php echo htmlspecialchars($order['user_phone']); ?> 
                                           <?php if ($order['user_nickname']): ?>
                                           (<?php echo htmlspecialchars($order['user_nickname']); ?>)
                                           <?php endif; ?>
                                        </p>
                                        <p><strong>标题：</strong><?php echo htmlspecialchars($order['title']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>状态：</strong>
                                            <?php
                                            $statusMap = [
                                                'pending' => '<span class="badge bg-secondary">待处理</span>',
                                                'reviewing' => '<span class="badge bg-warning">审核中</span>',
                                                'processing' => '<span class="badge bg-primary">处理中</span>',
                                                'completed' => '<span class="badge bg-success">已完成</span>',
                                                'failed' => '<span class="badge bg-danger">失败</span>'
                                            ];
                                            echo $statusMap[$order['status']] ?? '<span class="badge bg-secondary">未知</span>';
                                            ?>
                                        </p>
                                        <p><strong>消耗精灵币：</strong><?php echo $order['cost_coins']; ?></p>
                                        <p><strong>创建时间：</strong><?php echo date('Y-m-d H:i:s', strtotime($order['created_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI服务使用量 -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-robot me-2"></i>AI服务使用量</h5>
                            </div>
                            <div class="card-body" id="ai-usage-container">
                                <div class="text-center py-3">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="sr-only">加载中...</span>
                                    </div>
                                    <p class="text-muted mt-2">正在查询AI服务使用量...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 视频链接信息 -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card video-link-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-link me-2"></i>直播间链接</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>本方直播间</h6>
                                        <div class="input-group mb-3">
                                            <span class="input-group-text">本方</span>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($order['self_video_link']); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>同行直播间</h6>
                                        <?php 
                                        $competitorLinks = json_decode($order['competitor_video_links'], true);
                                        foreach ($competitorLinks as $index => $link): 
                                        ?>
                                        <div class="input-group mb-2">
                                            <span class="input-group-text">同行<?php echo $index + 1; ?></span>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($link); ?>" readonly>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FLV地址管理 -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card flv-address-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-video me-2"></i>FLV地址管理</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="flvAddressForm">
                                    <input type="hidden" name="action" value="update_flv_addresses">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>本方视频流</h6>
                                            <div class="input-group mb-3">
                                                <span class="input-group-text">FLV地址</span>
                                                <input type="url" class="form-control" name="flv_addresses[<?php echo $videoFiles[0]['id'] ?? 0; ?>]" 
                                                       value="<?php echo htmlspecialchars($videoFiles[0]['flv_url'] ?? ''); ?>" 
                                                       placeholder="请输入本方直播间的FLV地址">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>同行视频流</h6>
                                            <?php 
                                            $competitorFiles = array_filter($videoFiles, function($file) {
                                                return $file['video_type'] === 'competitor';
                                            });
                                            foreach ($competitorFiles as $index => $file): 
                                            ?>
                                            <div class="input-group mb-2">
                                                <span class="input-group-text">同行<?php echo $file['video_index']; ?></span>
                                                <input type="url" class="form-control" name="flv_addresses[<?php echo $file['id']; ?>]" 
                                                       value="<?php echo htmlspecialchars($file['flv_url'] ?? ''); ?>" 
                                                       placeholder="请输入同行<?php echo $file['video_index']; ?>的FLV地址">
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="text-center">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save me-2"></i>保存FLV地址
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 分析控制 -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card progress-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>分析控制</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>操作控制</h6>
                                        <div class="btn-group" role="group">
                                            <form method="POST" class="d-inline" id="startAnalysisForm">
                                                <input type="hidden" name="action" value="start_analysis">
                                                <button type="submit" class="btn btn-primary" id="startAnalysisBtn"
                                                        <?php echo $order['status'] === 'processing' ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-play me-2"></i>启动分析
                                                </button>
                                            </form>
                                            
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="stop_analysis">
                                                <button type="submit" class="btn btn-danger" 
                                                        <?php echo $order['status'] !== 'processing' ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-stop me-2"></i>停止分析
                                                </button>
                                            </form>
                                            
                                            <button type="button" class="btn btn-warning" id="processTasksBtn">
                                                <i class="fas fa-cogs me-2"></i>处理任务
                                            </button>
                                            
                                            <button type="button" class="btn btn-info" id="systemCheckBtn">
                                                <i class="fas fa-stethoscope me-2"></i>系统检查
                                            </button>
                                            
                                            <a href="../../test_flv_recording.php" class="btn btn-success" target="_blank">
                                                <i class="fas fa-video me-2"></i>测试录制
                                            </a>
                                            
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>处理进度</h6>
                                        <div id="progressContainer">
                                            <!-- 进度信息将通过AJAX加载 -->
                                            <div class="text-center">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">加载中...</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- 录制进度显示 -->
                                        <div class="mt-3">
                                            <h6>录制进度</h6>
                                            <div id="recordingProgressContainer">
                                                <div class="text-center">
                                                    <div class="spinner-border text-info" role="status">
                                                        <span class="visually-hidden">加载中...</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI服务额度 -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card quota-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>AI服务额度</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($apiQuotas as $service => $quota): ?>
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <h6 class="card-title"><?php echo ucfirst($service); ?></h6>
                                                <div class="progress mb-2" style="height: 20px;">
                                                    <div class="progress-bar" style="width: <?php echo $quota['percentage']; ?>%">
                                                        <?php echo $quota['percentage']; ?>%
                                                    </div>
                                                </div>
                                                <small class="text-muted">
                                                    已用: <?php echo $quota['used']; ?> / <?php echo $quota['total']; ?><br>
                                                    剩余: <?php echo $quota['remaining']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 处理日志 -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-list me-2"></i>处理日志</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>任务类型</th>
                                                <th>状态</th>
                                                <th>创建时间</th>
                                                <th>完成时间</th>
                                                <th>错误信息</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($processingTasks as $task): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($task['task_type']); ?></td>
                                                <td>
                                                    <?php
                                                    $statusMap = [
                                                        'pending' => '<span class="badge bg-secondary">待处理</span>',
                                                        'processing' => '<span class="badge bg-primary">处理中</span>',
                                                        'completed' => '<span class="badge bg-success">已完成</span>',
                                                        'failed' => '<span class="badge bg-danger">失败</span>',
                                                        'retry' => '<span class="badge bg-warning">重试</span>'
                                                    ];
                                                    echo $statusMap[$task['status']] ?? '<span class="badge bg-secondary">未知</span>';
                                                    ?>
                                                </td>
                                                <td><?php echo date('Y-m-d H:i:s', strtotime($task['created_at'])); ?></td>
                                                <td><?php echo $task['completed_at'] ? date('Y-m-d H:i:s', strtotime($task['completed_at'])) : '-'; ?></td>
                                                <td><?php echo $task['error_message'] ? htmlspecialchars($task['error_message']) : '-'; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // 自动刷新进度
        function loadProgress() {
            $.get('api/video_analysis_progress.php?order_id=<?php echo $orderId; ?>', function(data) {
                if (data.success) {
                    $('#progressContainer').html(data.html);
                }
            });
        }
        
        // 加载录制进度
        function loadRecordingProgress() {
            $.get('api/recording_progress.php?order_id=<?php echo $orderId; ?>', function(data) {
                if (data.success) {
                    displayRecordingProgress(data.data);
                }
            }).fail(function() {
                $('#recordingProgressContainer').html('<div class="alert alert-danger">加载录制进度失败</div>');
            });
        }
        
        // 显示录制进度
        function displayRecordingProgress(data) {
            if (!data || data.length === 0) {
                $('#recordingProgressContainer').html('<div class="text-muted">暂无录制进度信息</div>');
                return;
            }
            
            let html = '';
            data.forEach(function(videoFile) {
                const statusClass = getRecordingStatusClass(videoFile.recording_status);
                const progress = videoFile.recording_progress || 0;
                const message = videoFile.latest_progress ? videoFile.latest_progress.message : '等待开始';
                
                html += `
                    <div class="card mb-2">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge ${statusClass}">${getRecordingStatusText(videoFile.recording_status)}</span>
                                <small class="text-muted">${videoFile.video_type === 'self' ? '本方视频' : '同行视频' + videoFile.video_index}</small>
                            </div>
                            <div class="progress mb-2" style="height: 20px;">
                                <div class="progress-bar ${getProgressBarClass(videoFile.recording_status)}" 
                                     role="progressbar" style="width: ${progress}%" 
                                     aria-valuenow="${progress}" aria-valuemin="0" aria-valuemax="100">
                                    ${progress}%
                                </div>
                            </div>
                            <div class="small text-muted">
                                ${message}
                                ${videoFile.duration ? ` | 时长: ${videoFile.duration}秒` : ''}
                                ${videoFile.file_size ? ` | 大小: ${formatFileSize(videoFile.file_size)}` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $('#recordingProgressContainer').html(html);
        }
        
        // 获取录制状态样式类
        function getRecordingStatusClass(status) {
            switch(status) {
                case 'recording': return 'bg-primary';
                case 'completed': return 'bg-success';
                case 'failed': return 'bg-danger';
                default: return 'bg-secondary';
            }
        }
        
        // 获取录制状态文本
        function getRecordingStatusText(status) {
            switch(status) {
                case 'pending': return '等待录制';
                case 'recording': return '录制中';
                case 'completed': return '录制完成';
                case 'failed': return '录制失败';
                default: return '未知状态';
            }
        }
        
        // 获取进度条样式类
        function getProgressBarClass(status) {
            switch(status) {
                case 'recording': return 'progress-bar-striped progress-bar-animated';
                case 'completed': return 'bg-success';
                case 'failed': return 'bg-danger';
                default: return 'bg-secondary';
            }
        }
        
        // 格式化文件大小
        function formatFileSize(bytes) {
            if (!bytes) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB'];
            let unitIndex = 0;
            while (bytes >= 1024 && unitIndex < units.length - 1) {
                bytes /= 1024;
                unitIndex++;
            }
            return Math.round(bytes * 100) / 100 + ' ' + units[unitIndex];
        }
        
        // 页面加载完成后开始定时刷新
        $(document).ready(function() {
            loadProgress();
            loadRecordingProgress();
            loadAIUsage();
            loadTaskMonitor();
            
            // 每5秒刷新一次进度
            setInterval(loadProgress, 5000);
            // 每3秒刷新录制进度
            setInterval(loadRecordingProgress, 3000);
            // 每2秒更新任务监控
            setInterval(loadTaskMonitor, 2000);
        });
        
        // 加载AI服务使用量
        function loadAIUsage() {
            $.get('api/ai_usage.php?order_id=<?php echo $orderId; ?>', function(response) {
                if (response.success) {
                    displayAIUsage(response.data);
                } else {
                    $('#ai-usage-container').html(`
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                            <p>加载失败: ${response.message}</p>
                        </div>
                    `);
                }
            }).fail(function() {
                $('#ai-usage-container').html(`
                    <div class="text-center py-3 text-muted">
                        <i class="fas fa-wifi fa-2x mb-2"></i>
                        <p>网络错误，请稍后重试</p>
                    </div>
                `);
            });
        }
        
        // 显示AI服务使用量
        function displayAIUsage(usage) {
            if (usage.length === 0) {
                $('#ai-usage-container').html(`
                    <div class="text-center py-3 text-muted">
                        <i class="fas fa-info-circle fa-2x mb-2"></i>
                        <p>暂无AI服务使用记录</p>
                    </div>
                `);
                return;
            }
            
            let html = '<div class="table-responsive"><table class="table table-hover">';
            html += '<thead><tr><th>服务名称</th><th>使用量</th><th>费用</th><th>使用时间</th></tr></thead><tbody>';
            
            usage.forEach(item => {
                html += `
                    <tr>
                        <td><span class="badge bg-primary">${item.service_name}</span></td>
                        <td>${item.usage_amount} ${item.usage_unit || 'tokens'}</td>
                        <td>¥${item.cost_amount || '0.00'}</td>
                        <td><small class="text-muted">${item.created_at}</small></td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            $('#ai-usage-container').html(html);
        }
        
        // 处理任务
        $('#processTasksBtn').click(function() {
            const btn = $(this);
            const originalText = btn.html();
            
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>处理中...');
            
            // 显示处理状态
            showProcessingStatus('开始处理任务...');
            
            $.get('api/process_video_tasks.php?order_id=<?php echo $orderId; ?>', function(response) {
                if (response.success) {
                    showProcessingStatus('✅ 任务处理完成：' + response.message + '，处理了 ' + response.processed + ' 个任务');
                    // 刷新进度
                    loadProgress();
                } else {
                    showProcessingStatus('❌ 任务处理失败：' + response.message, 'error');
                }
            }).fail(function(xhr) {
                let errorMsg = '网络错误，请稍后重试';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showProcessingStatus('❌ ' + errorMsg, 'error');
            }).always(function() {
                btn.prop('disabled', false).html(originalText);
            });
        });
        
        // 系统检查
        $('#systemCheckBtn').click(function() {
            const btn = $(this);
            const originalText = btn.html();
            
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>检查中...');
            
            $.get('api/system_check.php', function(response) {
                if (response.success) {
                    showSystemCheckResults(response.data);
                } else {
                    showProcessingStatus('❌ 系统检查失败：' + response.message, 'error');
                }
            }).fail(function() {
                showProcessingStatus('❌ 系统检查网络错误', 'error');
            }).always(function() {
                btn.prop('disabled', false).html(originalText);
            });
        });
        
        // 显示系统检查结果
        function showSystemCheckResults(checks) {
            let html = '<div class="modal fade" id="systemCheckModal" tabindex="-1">';
            html += '<div class="modal-dialog modal-lg">';
            html += '<div class="modal-content">';
            html += '<div class="modal-header">';
            html += '<h5 class="modal-title"><i class="fas fa-stethoscope me-2"></i>系统检查结果</h5>';
            html += '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
            html += '</div>';
            html += '<div class="modal-body">';
            
            Object.keys(checks).forEach(key => {
                const check = checks[key];
                const statusIcon = check.status === 'ok' ? 'fa-check-circle text-success' : 
                                 check.status === 'warning' ? 'fa-exclamation-triangle text-warning' : 
                                 'fa-times-circle text-danger';
                const statusText = check.status === 'ok' ? '正常' : 
                                 check.status === 'warning' ? '警告' : '错误';
                
                html += '<div class="row mb-3">';
                html += '<div class="col-3"><strong>' + check.name + '</strong></div>';
                html += '<div class="col-2"><i class="fas ' + statusIcon + ' me-1"></i>' + statusText + '</div>';
                html += '<div class="col-7">' + check.message + '</div>';
                html += '</div>';
                
                if (check.details) {
                    html += '<div class="row mb-3">';
                    html += '<div class="col-12"><small class="text-muted">' + check.details + '</small></div>';
                    html += '</div>';
                }
            });
            
            html += '</div>';
            html += '<div class="modal-footer">';
            html += '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            
            // 移除已存在的模态框
            $('#systemCheckModal').remove();
            
            // 添加新的模态框
            $('body').append(html);
            
            // 显示模态框
            $('#systemCheckModal').modal('show');
        }
        
        // 加载任务监控
        function loadTaskMonitor() {
            $.get('api/task_monitor.php?order_id=<?php echo $orderId; ?>', function(response) {
                if (response.success) {
                    displayTaskMonitor(response.data);
                }
            }).fail(function() {
                // 静默失败，不影响其他功能
            });
        }
        
        // 显示任务监控
        function displayTaskMonitor(data) {
            const { tasks, task_stats, progress, current_task, failed_tasks } = data;
            
            // 更新进度条
            $('.progress-bar').css('width', progress + '%').text(progress + '%');
            
            // 更新任务统计
            let statsHtml = `
                <div class="row text-center">
                    <div class="col-2"><span class="badge bg-secondary">总计: ${task_stats.total}</span></div>
                    <div class="col-2"><span class="badge bg-warning">待处理: ${task_stats.pending}</span></div>
                    <div class="col-2"><span class="badge bg-info">处理中: ${task_stats.processing}</span></div>
                    <div class="col-2"><span class="badge bg-success">已完成: ${task_stats.completed}</span></div>
                    <div class="col-2"><span class="badge bg-danger">失败: ${task_stats.failed}</span></div>
                </div>
            `;
            
            if (current_task) {
                statsHtml += `
                    <div class="mt-2">
                        <small class="text-info">
                            <i class="fas fa-cog fa-spin me-1"></i>
                            当前处理: ${getTaskTypeName(current_task.task_type)}
                        </small>
                    </div>
                `;
            }
            
            if (failed_tasks.length > 0) {
                statsHtml += `
                    <div class="mt-2">
                        <small class="text-danger">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            失败任务: ${failed_tasks.length} 个
                        </small>
                    </div>
                `;
            }
            
            // 更新任务统计显示
            if ($('#task-stats').length === 0) {
                $('#progress-container').after('<div id="task-stats" class="mt-3"></div>');
            }
            $('#task-stats').html(statsHtml);
        }
        
        // 获取任务类型名称
        function getTaskTypeName(taskType) {
            const typeNames = {
                'record': '录制视频',
                'transcode': '转码处理',
                'segment': '视频切片',
                'asr': '语音识别',
                'analysis': 'AI分析',
                'report': '生成报告'
            };
            return typeNames[taskType] || taskType;
        }
        
        // 显示处理状态
        function showProcessingStatus(message, type = 'info') {
            const alertClass = type === 'error' ? 'alert-danger' : 'alert-info';
            const statusHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            // 在页面顶部显示状态
            if ($('.processing-status').length === 0) {
                $('.main-content').prepend('<div class="processing-status"></div>');
            }
            $('.processing-status').html(statusHtml);
            
            // 自动隐藏成功消息
            if (type !== 'error') {
                setTimeout(() => {
                    $('.processing-status .alert').fadeOut();
                }, 5000);
            }
        }
        
        // 页面加载完成后启动定时器
        $(document).ready(function() {
            // 加载录制进度
            loadRecordingProgress();
            
            // 加载任务监控
            loadTaskMonitor();
            
            // 每3秒刷新一次录制进度
            setInterval(loadRecordingProgress, 3000);
            
            // 每5秒刷新一次任务监控
            setInterval(loadTaskMonitor, 5000);
            
            // 处理启动分析表单提交
            $('#startAnalysisForm').on('submit', function(e) {
                e.preventDefault();
                
                const $btn = $('#startAnalysisBtn');
                const originalText = $btn.html();
                
                // 禁用按钮并显示加载状态
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>启动中...');
                
                // 提交表单
                $.post('', $(this).serialize(), function(response) {
                    if (response && response.includes('分析已启动')) {
                        showProcessingStatus('分析已启动，正在自动处理中...', 'success');
                        
                        // 立即开始监控
                        loadTaskMonitor();
                        loadRecordingProgress();
                        
                        // 3秒后刷新页面以更新状态
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    } else {
                        showProcessingStatus('启动分析失败，请检查错误信息', 'error');
                        $btn.prop('disabled', false).html(originalText);
                    }
                }).fail(function() {
                    showProcessingStatus('启动分析失败，请重试', 'error');
                    $btn.prop('disabled', false).html(originalText);
                });
            });
        });
        
    </script>
</body>
</html>
