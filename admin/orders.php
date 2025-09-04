<?php
/**
 * 管理员 - 订单管理页面
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

$message = '';
$error = '';

// 处理订单操作
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $orderId = intval($_POST['order_id'] ?? 0);
    
    if ($action == 'delete_order' && $orderId > 0) {
        $analysisOrder = new AnalysisOrder();
        $order = $analysisOrder->getOrderById($orderId);
        
        if ($order) {
            if ($analysisOrder->deleteOrder($orderId)) {
                $message = '订单已删除';
                
                // 记录操作日志
                $operationLog = new OperationLog();
                $operationLog->log($_SESSION['admin_id'], 'order_delete', "删除订单 #{$orderId}", $orderId);
            } else {
                $error = '删除失败，请重试';
            }
        }
    }
}

// 分页和筛选参数
$page = max(1, intval($_GET['page'] ?? 1));
$pageSize = 20;
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// 构建查询条件
$where = "1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (ao.title LIKE ? OR u.phone LIKE ? OR u.nickname LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($status !== '') {
    $where .= " AND ao.status = ?";
    $params[] = $status;
}

if (!empty($dateFrom)) {
    $where .= " AND DATE(ao.created_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $where .= " AND DATE(ao.created_at) <= ?";
    $params[] = $dateTo;
}

// 获取订单列表
$offset = ($page - 1) * $pageSize;
$orders = $db->fetchAll(
    "SELECT ao.*, u.phone, u.nickname,
            DATE_FORMAT(ao.created_at, '%Y-%m-%d %H:%i') as created_time,
            DATE_FORMAT(ao.completed_at, '%Y-%m-%d %H:%i') as completed_time
     FROM analysis_orders ao 
     LEFT JOIN users u ON ao.user_id = u.id 
     WHERE {$where} 
     ORDER BY ao.created_at DESC 
     LIMIT ? OFFSET ?",
    array_merge($params, [$pageSize, $offset])
);

// 获取总数
$total = $db->fetchOne(
    "SELECT COUNT(*) as count FROM analysis_orders ao LEFT JOIN users u ON ao.user_id = u.id WHERE {$where}",
    $params
)['count'];

$totalPages = ceil($total / $pageSize);

// 获取统计数据
$stats = $db->fetchOne(
    "SELECT 
        COUNT(*) as total_orders,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
        COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_orders,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_orders,
        SUM(cost_coins) as total_coins_consumed
     FROM analysis_orders"
);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>订单管理 - <?php echo APP_NAME; ?></title>
    
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
                    <h1 class="h2">订单管理</h1>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- 统计卡片 -->
                <div class="row mb-4">
                    <div class="col-xl-2 col-md-4 col-6 mb-3">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body">
                                <div class="text-center">
                                    <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $stats['total_orders']; ?></h4>
                                    <small>总订单</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6 mb-3">
                        <div class="card bg-warning text-dark h-100">
                            <div class="card-body">
                                <div class="text-center">
                                    <i class="fas fa-clock fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $stats['pending_orders']; ?></h4>
                                    <small>待处理</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6 mb-3">
                        <div class="card bg-info text-white h-100">
                            <div class="card-body">
                                <div class="text-center">
                                    <i class="fas fa-spinner fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $stats['processing_orders']; ?></h4>
                                    <small>处理中</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6 mb-3">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body">
                                <div class="text-center">
                                    <i class="fas fa-check fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $stats['completed_orders']; ?></h4>
                                    <small>已完成</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6 mb-3">
                        <div class="card bg-danger text-white h-100">
                            <div class="card-body">
                                <div class="text-center">
                                    <i class="fas fa-times fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $stats['failed_orders']; ?></h4>
                                    <small>失败</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6 mb-3">
                        <div class="card bg-secondary text-white h-100">
                            <div class="card-body">
                                <div class="text-center">
                                    <i class="fas fa-coins fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $stats['total_coins_consumed']; ?></h4>
                                    <small>消费币数</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 搜索和筛选 -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">搜索订单</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="标题、手机号或昵称">
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">状态筛选</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">全部状态</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>待处理</option>
                                    <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>处理中</option>
                                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>已完成</option>
                                    <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>失败</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">开始日期</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo htmlspecialchars($dateFrom); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">结束日期</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo htmlspecialchars($dateTo); ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i>搜索
                                </button>
                                <a href="orders.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-1"></i>重置
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 订单列表 -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list-alt me-2"></i>订单列表
                            <span class="badge bg-primary ms-2"><?php echo $total; ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($orders)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">暂无订单</h5>
                            <p class="text-muted">没有找到符合条件的订单</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>订单ID</th>
                                        <th>标题</th>
                                        <th>用户</th>
                                        <th>消费币数</th>
                                        <th>状态</th>
                                        <th>创建时间</th>
                                        <th>完成时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td class="fw-bold">#<?php echo $order['id']; ?></td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($order['title']); ?>">
                                                <?php echo htmlspecialchars($order['title']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($order['nickname']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($order['phone']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-coins me-1"></i><?php echo $order['cost_coins']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $statusConfig = [
                                                'pending' => ['badge' => 'warning', 'icon' => 'clock', 'text' => '待处理'],
                                                'processing' => ['badge' => 'info', 'icon' => 'spinner', 'text' => '处理中'],
                                                'completed' => ['badge' => 'success', 'icon' => 'check', 'text' => '已完成'],
                                                'failed' => ['badge' => 'danger', 'icon' => 'times', 'text' => '失败']
                                            ];
                                            $config = $statusConfig[$order['status']] ?? ['badge' => 'secondary', 'icon' => 'question', 'text' => '未知'];
                                            ?>
                                            <span class="badge bg-<?php echo $config['badge']; ?>">
                                                <i class="fas fa-<?php echo $config['icon']; ?> me-1"></i><?php echo $config['text']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo $order['created_time']; ?></small>
                                        </td>
                                        <td>
                                            <?php if ($order['completed_time']): ?>
                                            <small class="text-muted"><?php echo $order['completed_time']; ?></small>
                                            <?php else: ?>
                                            <small class="text-muted">-</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <!-- 查看报告 -->
                                                <?php if ($order['status'] == 'completed'): ?>
                                                <a href="../report.php?id=<?php echo $order['id']; ?>" target="_blank" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i>查看
                                                </a>
                                                <?php endif; ?>
                                                
                                                <!-- 查看详情 -->
                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                        data-bs-toggle="modal" data-bs-target="#orderModal"
                                                        data-order='<?php echo json_encode($order); ?>'>
                                                    <i class="fas fa-info me-1"></i>详情
                                                </button>
                                                
                                                <!-- 删除订单 -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="delete_order">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                            onclick="return confirm('确定要删除订单 #<?php echo $order['id']; ?> 吗？此操作不可恢复！')">
                                                        <i class="fas fa-trash me-1"></i>删除
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- 分页 -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="订单列表分页" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- 订单详情模态框 -->
    <div class="modal fade" id="orderModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-list-alt me-2"></i>订单详情
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">基本信息</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <th width="30%">订单ID</th>
                                    <td id="detailOrderId"></td>
                                </tr>
                                <tr>
                                    <th>标题</th>
                                    <td id="detailOrderTitle"></td>
                                </tr>
                                <tr>
                                    <th>用户</th>
                                    <td id="detailOrderUser"></td>
                                </tr>
                                <tr>
                                    <th>消费币数</th>
                                    <td id="detailOrderCoins"></td>
                                </tr>
                                <tr>
                                    <th>状态</th>
                                    <td id="detailOrderStatus"></td>
                                </tr>
                                <tr>
                                    <th>创建时间</th>
                                    <td id="detailOrderCreated"></td>
                                </tr>
                                <tr>
                                    <th>完成时间</th>
                                    <td id="detailOrderCompleted"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">分析结果</h6>
                            <div id="detailOrderResult">
                                <div class="text-center py-4">
                                    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                                    <p class="text-muted mt-2">加载中...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                    <a id="viewReportBtn" href="#" target="_blank" class="btn btn-primary" style="display: none;">
                        <i class="fas fa-external-link-alt me-2"></i>查看完整报告
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 订单详情模态框
        const orderModal = document.getElementById('orderModal');
        orderModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const order = JSON.parse(button.getAttribute('data-order'));
            
            // 填充基本信息
            document.getElementById('detailOrderId').textContent = '#' + order.id;
            document.getElementById('detailOrderTitle').textContent = order.title;
            document.getElementById('detailOrderUser').innerHTML = `<div><strong>${order.nickname}</strong><br><small class="text-muted">${order.phone}</small></div>`;
            document.getElementById('detailOrderCoins').innerHTML = `<span class="badge bg-warning text-dark"><i class="fas fa-coins me-1"></i>${order.cost_coins}</span>`;
            
            // 状态显示
            const statusConfig = {
                'pending': {badge: 'warning', icon: 'clock', text: '待处理'},
                'processing': {badge: 'info', icon: 'spinner', text: '处理中'},
                'completed': {badge: 'success', icon: 'check', text: '已完成'},
                'failed': {badge: 'danger', icon: 'times', text: '失败'}
            };
            const config = statusConfig[order.status] || {badge: 'secondary', icon: 'question', text: '未知'};
            document.getElementById('detailOrderStatus').innerHTML = `<span class="badge bg-${config.badge}"><i class="fas fa-${config.icon} me-1"></i>${config.text}</span>`;
            
            document.getElementById('detailOrderCreated').textContent = order.created_time;
            document.getElementById('detailOrderCompleted').textContent = order.completed_time || '-';
            
            // 处理分析结果
            const resultDiv = document.getElementById('detailOrderResult');
            const viewReportBtn = document.getElementById('viewReportBtn');
            
            if (order.status === 'completed' && order.analysis_result) {
                try {
                    const result = JSON.parse(order.analysis_result);
                    resultDiv.innerHTML = `
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">综合评分</h6>
                                    <span class="badge bg-primary fs-6">${result.overall_score || 0}/100</span>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted">等级：</small>
                                    <span class="badge bg-info">${result.level || '未知'}</span>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted">总结：</small>
                                    <p class="small mb-0">${(result.summary || '').substring(0, 100)}${(result.summary || '').length > 100 ? '...' : ''}</p>
                                </div>
                            </div>
                        </div>
                    `;
                    viewReportBtn.href = `../report.php?id=${order.id}`;
                    viewReportBtn.style.display = 'inline-block';
                } catch (e) {
                    resultDiv.innerHTML = '<div class="alert alert-warning">分析结果解析失败</div>';
                    viewReportBtn.style.display = 'none';
                }
            } else if (order.status === 'failed') {
                resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>分析失败</div>';
                viewReportBtn.style.display = 'none';
            } else {
                resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-clock me-2"></i>分析尚未完成</div>';
                viewReportBtn.style.display = 'none';
            }
        });

        // 自动刷新处理中的订单
        setInterval(function() {
            const processingBadges = document.querySelectorAll('.badge:contains("处理中")');
            if (processingBadges.length > 0) {
                location.reload();
            }
        }, 30000); // 30秒刷新一次
    </script>
</body>
</html>