<?php
/**
 * 管理员 - 订单详情页面
 */
require_once '../config/config.php';
require_once '../config/database.php';

// 检查管理员登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$db = new Database();
$admin = $db->fetchOne("SELECT * FROM admins WHERE id = ?", [$_SESSION['admin_id']]);

if (!$admin || $admin['status'] != 1) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// 获取订单ID
$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) {
    header('Location: orders.php');
    exit;
}

// 获取订单详情
$analysisOrder = new AnalysisOrder();
$order = $analysisOrder->getOrderById($orderId);

if (!$order) {
    header('Location: orders.php');
    exit;
}

// 获取相关日志
$logs = $db->fetchAll(
    "SELECT ol.*, a.username as admin_name, u.phone as user_phone
     FROM operation_logs ol 
     LEFT JOIN admins a ON ol.operator_type = 'admin' AND ol.operator_id = a.id
     LEFT JOIN users u ON ol.operator_type = 'user' AND ol.operator_id = u.id
     WHERE ol.target_type = 'order' AND ol.target_id = ? 
     ORDER BY ol.created_at DESC",
    [$orderId]
);

// 获取错误日志
$errorLogs = $db->fetchAll(
    "SELECT * FROM error_logs WHERE error_message LIKE ? ORDER BY created_at DESC LIMIT 10",
    ["%订单{$orderId}%"]
);

// 解析订单数据
$competitorScripts = json_decode($order['competitor_scripts'], true) ?: [];
$aiReport = json_decode($order['ai_report'], true) ?: null;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>订单详情 #<?php echo $order['order_no']; ?> - <?php echo APP_NAME; ?></title>
    
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
        .status-pending { background-color: #ffc107; }
        .status-processing { background-color: #17a2b8; }
        .status-completed { background-color: #28a745; }
        .status-failed { background-color: #dc3545; }
        .log-entry {
            border-left: 4px solid #007bff;
            margin-bottom: 1rem;
        }
        .error-log {
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body>
    <!-- 顶部导航 -->
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-cog me-2"></i><?php echo APP_NAME; ?> 管理后台
            </a>
            
            <div class="navbar-nav flex-row">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-light" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-shield me-1"></i><?php echo htmlspecialchars($admin['real_name'] ?: $admin['username']); ?>
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
                            <a class="nav-link active" href="orders.php">
                                <i class="fas fa-list-alt me-2"></i>订单管理
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
                        <li class="nav-item">
                            <a class="nav-link" href="statistics.php">
                                <i class="fas fa-chart-bar me-2"></i>数据统计
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logs.php">
                                <i class="fas fa-history me-2"></i>操作日志
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- 主要内容区域 -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="pt-3 pb-2 mb-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1 class="h2">订单详情 #<?php echo $order['order_no']; ?></h1>
                        <div>
                            <a href="orders.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>返回订单列表
                            </a>
                            <button class="btn btn-primary" onclick="refreshOrderStatus()">
                                <i class="fas fa-sync-alt me-1"></i>刷新状态
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 订单基本信息 -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>基本信息</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>订单标题：</strong><?php echo htmlspecialchars($order['title']); ?><br>
                                        <strong>订单号：</strong><?php echo $order['order_no']; ?><br>
                                        <strong>用户：</strong><?php echo $order['user_phone']; ?><br>
                                        <strong>消费：</strong><?php echo $order['cost_coins']; ?> 精灵币
                                    </div>
                                    <div class="col-md-6">
                                        <strong>创建时间：</strong><?php echo $order['created_at']; ?><br>
                                        <strong>完成时间：</strong><?php echo $order['completed_at'] ?: '未完成'; ?><br>
                                        <strong>当前状态：</strong>
                                        <?php
                                        $statusConfig = [
                                            'pending' => ['badge' => 'warning', 'text' => '等待处理'],
                                            'processing' => ['badge' => 'info', 'text' => '处理中'],
                                            'completed' => ['badge' => 'success', 'text' => '已完成'],
                                            'failed' => ['badge' => 'danger', 'text' => '失败']
                                        ];
                                        $config = $statusConfig[$order['status']] ?? ['badge' => 'secondary', 'text' => '未知'];
                                        ?>
                                        <span class="badge bg-<?php echo $config['badge']; ?>"><?php echo $config['text']; ?></span>
                                    </div>
                                </div>
                                
                                <?php if ($order['error_message']): ?>
                                <div class="alert alert-danger mt-3">
                                    <strong>错误信息：</strong><?php echo htmlspecialchars($order['error_message']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>分析进度</h5>
                            </div>
                            <div class="card-body text-center">
                                <?php
                                $progress = 0;
                                $statusText = '';
                                switch ($order['status']) {
                                    case 'pending':
                                        $progress = 10;
                                        $statusText = '等待处理中';
                                        break;
                                    case 'processing':
                                        $progress = 50;
                                        $statusText = 'AI正在分析中';
                                        break;
                                    case 'completed':
                                        $progress = 100;
                                        $statusText = '分析完成';
                                        break;
                                    case 'failed':
                                        $progress = 0;
                                        $statusText = '分析失败';
                                        break;
                                }
                                ?>
                                <div class="progress mb-3" style="height: 20px;">
                                    <div class="progress-bar bg-<?php echo $config['badge']; ?>" 
                                         style="width: <?php echo $progress; ?>%"></div>
                                </div>
                                <div class="h4 text-<?php echo $config['badge']; ?>"><?php echo $progress; ?>%</div>
                                <div class="text-muted"><?php echo $statusText; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 话术内容 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-comments me-2"></i>话术内容</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>本方话术：</h6>
                                <div class="border p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                                    <?php echo nl2br(htmlspecialchars($order['self_script'])); ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>同行话术：</h6>
                                <?php foreach ($competitorScripts as $index => $script): ?>
                                <div class="mb-2">
                                    <small class="text-muted">同行<?php echo $index + 1; ?>：</small>
                                    <div class="border p-2 bg-light small" style="max-height: 100px; overflow-y: auto;">
                                        <?php echo nl2br(htmlspecialchars($script)); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI分析报告 -->
                <?php if ($aiReport): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-robot me-2"></i>AI分析报告</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>综合评分：</strong>
                                <span class="h4 text-primary"><?php echo $order['report_score'] ?: '未评分'; ?></span>
                                <?php if ($order['report_level']): ?>
                                <span class="badge bg-info"><?php echo $order['report_level']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <a href="../report.php?id=<?php echo $orderId; ?>" class="btn btn-primary" target="_blank">
                                    <i class="fas fa-external-link-alt me-1"></i>查看完整报告
                                </a>
                            </div>
                        </div>
                        <hr>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php echo nl2br(htmlspecialchars($order['ai_report'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 操作日志 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>操作日志</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($logs)): ?>
                        <p class="text-muted">暂无操作日志</p>
                        <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($logs as $log): ?>
                            <div class="log-entry p-3 mb-3">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong><?php echo htmlspecialchars($log['action']); ?></strong>
                                        <span class="text-muted">- <?php echo htmlspecialchars($log['description']); ?></span>
                                    </div>
                                    <small class="text-muted"><?php echo $log['created_at']; ?></small>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        操作者: <?php echo htmlspecialchars($log['admin_name'] ?: $log['user_phone'] ?: '系统'); ?>
                                        | IP: <?php echo $log['ip_address']; ?>
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 错误日志 -->
                <?php if (!empty($errorLogs)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>错误日志</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($errorLogs as $errorLog): ?>
                        <div class="log-entry error-log p-3 mb-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong class="text-danger"><?php echo htmlspecialchars($errorLog['error_type']); ?></strong>
                                    <span class="text-muted">- <?php echo htmlspecialchars($errorLog['error_message']); ?></span>
                                </div>
                                <small class="text-muted"><?php echo $errorLog['created_at']; ?></small>
                            </div>
                            <?php if ($errorLog['file_path']): ?>
                            <div class="mt-2">
                                <small class="text-muted">
                                    文件: <?php echo htmlspecialchars($errorLog['file_path']); ?>
                                    <?php if ($errorLog['line_number']): ?>
                                    :<?php echo $errorLog['line_number']; ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 管理操作 -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tools me-2"></i>管理操作</h5>
                    </div>
                    <div class="card-body">
                        <div class="btn-group">
                            <?php if ($order['status'] === 'pending'): ?>
                            <button class="btn btn-primary" onclick="manualProcessOrder(<?php echo $orderId; ?>)">
                                <i class="fas fa-play me-1"></i>手动开始分析
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($order['status'] === 'processing'): ?>
                            <button class="btn btn-warning" onclick="resetOrderStatus(<?php echo $orderId; ?>)">
                                <i class="fas fa-undo me-1"></i>重置为待处理
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($order['status'] === 'failed'): ?>
                            <button class="btn btn-success" onclick="retryAnalysis(<?php echo $orderId; ?>)">
                                <i class="fas fa-redo me-1"></i>重新分析
                            </button>
                            <?php endif; ?>
                            
                            <button class="btn btn-danger" onclick="deleteOrder(<?php echo $orderId; ?>)">
                                <i class="fas fa-trash me-1"></i>删除订单
                            </button>
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
        // 自动刷新状态
        let autoRefreshInterval;
        
        $(document).ready(function() {
            // 如果订单正在处理中，启动自动刷新
            <?php if ($order['status'] === 'processing'): ?>
            startAutoRefresh();
            <?php endif; ?>
        });
        
        // 启动自动刷新
        function startAutoRefresh() {
            autoRefreshInterval = setInterval(function() {
                refreshOrderStatus();
            }, 5000); // 每5秒刷新一次
        }
        
        // 停止自动刷新
        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }
        
        // 刷新订单状态
        function refreshOrderStatus() {
            $.get('../api/order_status.php?id=<?php echo $orderId; ?>', function(response) {
                if (response.success) {
                    const order = response.data;
                    
                    // 如果状态改变，刷新页面
                    if (order.status !== '<?php echo $order['status']; ?>') {
                        location.reload();
                    }
                }
            }).fail(function() {
                console.error('状态刷新失败');
            });
        }
        
        // 手动开始分析
        function manualProcessOrder(orderId) {
            if (!confirm('确定要手动开始分析这个订单吗？')) {
                return;
            }
            
            $.post('api/process_order.php', {
                order_id: orderId,
                action: 'start'
            }, function(response) {
                if (response.success) {
                    alert('分析已开始');
                    startAutoRefresh();
                    location.reload();
                } else {
                    alert('操作失败：' + response.message);
                }
            }, 'json');
        }
        
        // 重置订单状态
        function resetOrderStatus(orderId) {
            if (!confirm('确定要重置订单状态为待处理吗？')) {
                return;
            }
            
            $.post('api/process_order.php', {
                order_id: orderId,
                action: 'reset'
            }, function(response) {
                if (response.success) {
                    alert('状态已重置');
                    location.reload();
                } else {
                    alert('操作失败：' + response.message);
                }
            }, 'json');
        }
        
        // 重新分析
        function retryAnalysis(orderId) {
            if (!confirm('确定要重新分析这个订单吗？')) {
                return;
            }
            
            $.post('api/process_order.php', {
                order_id: orderId,
                action: 'retry'
            }, function(response) {
                if (response.success) {
                    alert('重新分析已开始');
                    startAutoRefresh();
                    location.reload();
                } else {
                    alert('操作失败：' + response.message);
                }
            }, 'json');
        }
        
        // 删除订单
        function deleteOrder(orderId) {
            if (!confirm('确定要删除这个订单吗？此操作不可恢复！')) {
                return;
            }
            
            $.post('api/process_order.php', {
                order_id: orderId,
                action: 'delete'
            }, function(response) {
                if (response.success) {
                    alert('订单已删除');
                    window.location.href = 'orders.php';
                } else {
                    alert('删除失败：' + response.message);
                }
            }, 'json');
        }
    </script>
</body>
</html>