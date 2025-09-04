<?php
/**
 * 管理员 - 操作日志页面
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

// 分页和筛选参数
$page = max(1, intval($_GET['page'] ?? 1));
$pageSize = 20;
$search = trim($_GET['search'] ?? '');
$action = $_GET['action'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// 构建查询条件
$where = "1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (ol.description LIKE ? OR a.username LIKE ? OR a.real_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($action !== '') {
    $where .= " AND ol.action = ?";
    $params[] = $action;
}

if (!empty($dateFrom)) {
    $where .= " AND DATE(ol.created_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $where .= " AND DATE(ol.created_at) <= ?";
    $params[] = $dateTo;
}

// 获取日志列表
$offset = ($page - 1) * $pageSize;
$logs = $db->fetchAll(
    "SELECT ol.*, a.username, a.real_name,
            DATE_FORMAT(ol.created_at, '%Y-%m-%d %H:%i:%s') as created_time
     FROM operation_logs ol 
     LEFT JOIN admins a ON ol.admin_id = a.id 
     WHERE {$where} 
     ORDER BY ol.created_at DESC 
     LIMIT ? OFFSET ?",
    array_merge($params, [$pageSize, $offset])
);

// 获取总数
$total = $db->fetchOne(
    "SELECT COUNT(*) as count FROM operation_logs ol LEFT JOIN admins a ON ol.admin_id = a.id WHERE {$where}",
    $params
)['count'];

$totalPages = ceil($total / $pageSize);

// 获取操作类型统计
$actionStats = $db->fetchAll(
    "SELECT action, COUNT(*) as count FROM operation_logs GROUP BY action ORDER BY count DESC"
);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>操作日志 - <?php echo APP_NAME; ?></title>
    
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
                            <a class="nav-link" href="orders.php">
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
                            <a class="nav-link active" href="logs.php">
                                <i class="fas fa-history me-2"></i>操作日志
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- 主要内容区域 -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">操作日志</h1>
                </div>

                <!-- 操作统计 -->
                <div class="row mb-4">
                    <?php foreach ($actionStats as $index => $stat): ?>
                    <?php if ($index < 6): // 只显示前6个统计 ?>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-bar fa-2x text-primary mb-2"></i>
                                <h5 class="mb-1"><?php echo $stat['count']; ?></h5>
                                <small class="text-muted"><?php echo $stat['action']; ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- 搜索和筛选 -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">搜索日志</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="描述内容或管理员">
                            </div>
                            <div class="col-md-2">
                                <label for="action" class="form-label">操作类型</label>
                                <select class="form-select" id="action" name="action">
                                    <option value="">全部类型</option>
                                    <?php foreach ($actionStats as $stat): ?>
                                    <option value="<?php echo htmlspecialchars($stat['action']); ?>" 
                                            <?php echo $action === $stat['action'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($stat['action']); ?> (<?php echo $stat['count']; ?>)
                                    </option>
                                    <?php endforeach; ?>
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
                                <a href="logs.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-1"></i>重置
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 日志列表 -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history me-2"></i>操作记录
                            <span class="badge bg-primary ms-2"><?php echo $total; ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($logs)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">暂无日志</h5>
                            <p class="text-muted">没有找到符合条件的操作日志</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>时间</th>
                                        <th>管理员</th>
                                        <th>操作类型</th>
                                        <th>描述</th>
                                        <th>IP地址</th>
                                        <th>相关ID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <small class="text-muted"><?php echo $log['created_time']; ?></small>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($log['real_name'] ?: $log['username']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($log['username']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $actionConfig = [
                                                'login' => ['badge' => 'success', 'icon' => 'sign-in-alt'],
                                                'logout' => ['badge' => 'secondary', 'icon' => 'sign-out-alt'],
                                                'user_status' => ['badge' => 'warning', 'icon' => 'user-cog'],
                                                'user_coins' => ['badge' => 'info', 'icon' => 'coins'],
                                                'order_delete' => ['badge' => 'danger', 'icon' => 'trash'],
                                                'exchange_code' => ['badge' => 'primary', 'icon' => 'ticket-alt'],
                                                'system_config' => ['badge' => 'dark', 'icon' => 'cogs']
                                            ];
                                            $config = $actionConfig[$log['action']] ?? ['badge' => 'secondary', 'icon' => 'question'];
                                            ?>
                                            <span class="badge bg-<?php echo $config['badge']; ?>">
                                                <i class="fas fa-<?php echo $config['icon']; ?> me-1"></i><?php echo htmlspecialchars($log['action']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($log['description']); ?>">
                                                <?php echo htmlspecialchars($log['description']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <code class="small"><?php echo htmlspecialchars($log['ip_address']); ?></code>
                                        </td>
                                        <td>
                                            <?php if ($log['related_id']): ?>
                                            <span class="badge bg-light text-dark">#<?php echo $log['related_id']; ?></span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- 分页 -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="日志列表分页" class="mt-4">
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

                <!-- 日志统计 -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-pie me-2"></i>操作统计
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($actionStats as $stat): ?>
                            <div class="col-lg-2 col-md-3 col-4 mb-3">
                                <div class="text-center">
                                    <div class="h5 text-primary"><?php echo $stat['count']; ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($stat['action']); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 自动刷新页面（每5分钟）
        setInterval(function() {
            // 只在没有筛选条件时自动刷新
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('search') && !urlParams.has('action') && !urlParams.has('date_from') && !urlParams.has('date_to')) {
                location.reload();
            }
        }, 300000); // 5分钟

        // 表格行点击效果
        document.querySelectorAll('tbody tr').forEach(row => {
            row.addEventListener('click', function() {
                // 移除其他行的高亮
                document.querySelectorAll('tbody tr').forEach(r => r.classList.remove('table-active'));
                // 高亮当前行
                this.classList.add('table-active');
            });
        });
    </script>
</body>
</html>