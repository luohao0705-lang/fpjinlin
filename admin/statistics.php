<?php
/**
 * 管理员 - 数据统计页面
 */
require_once '../config/config.php';
require_once '../config/database.php';

// 检查管理员登录
session_start();
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

// 获取时间范围（默认最近30天）
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// 基础统计数据
$totalUsers = $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'];
$totalOrders = $db->fetchOne("SELECT COUNT(*) as count FROM analysis_orders")['count'];
$totalCoinsConsumed = $db->fetchOne("SELECT COALESCE(SUM(cost_coins), 0) as total FROM analysis_orders WHERE status = 'completed'")['total'];
$totalCoinsRecharged = $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM coin_transactions WHERE type IN ('recharge', 'admin_add')")['total'];

// 今日数据
$todayUsers = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()")['count'];
$todayOrders = $db->fetchOne("SELECT COUNT(*) as count FROM analysis_orders WHERE DATE(created_at) = CURDATE()")['count'];
$todayRevenue = $db->fetchOne("SELECT COALESCE(SUM(cost_coins), 0) as total FROM analysis_orders WHERE DATE(created_at) = CURDATE() AND status = 'completed'")['total'];

// 用户注册趋势（最近30天）
$userTrend = $db->fetchAll(
    "SELECT DATE(created_at) as date, COUNT(*) as count 
     FROM users 
     WHERE DATE(created_at) BETWEEN ? AND ? 
     GROUP BY DATE(created_at) 
     ORDER BY date",
    [$dateFrom, $dateTo]
);

// 订单趋势（最近30天）
$orderTrend = $db->fetchAll(
    "SELECT DATE(created_at) as date, COUNT(*) as count, COALESCE(SUM(cost_coins), 0) as revenue
     FROM analysis_orders 
     WHERE DATE(created_at) BETWEEN ? AND ? 
     GROUP BY DATE(created_at) 
     ORDER BY date",
    [$dateFrom, $dateTo]
);

// 订单状态分布
$orderStatusStats = $db->fetchAll(
    "SELECT status, COUNT(*) as count 
     FROM analysis_orders 
     WHERE DATE(created_at) BETWEEN ? AND ? 
     GROUP BY status",
    [$dateFrom, $dateTo]
);

// 热门分析时段
$hourlyStats = $db->fetchAll(
    "SELECT HOUR(created_at) as hour, COUNT(*) as count 
     FROM analysis_orders 
     WHERE DATE(created_at) BETWEEN ? AND ? 
     GROUP BY HOUR(created_at) 
     ORDER BY hour",
    [$dateFrom, $dateTo]
);

// 用户活跃度统计
$userActivityStats = $db->fetchAll(
    "SELECT 
        CASE 
            WHEN order_count = 0 THEN '未使用'
            WHEN order_count = 1 THEN '使用1次'
            WHEN order_count BETWEEN 2 AND 5 THEN '使用2-5次'
            WHEN order_count BETWEEN 6 AND 10 THEN '使用6-10次'
            ELSE '使用10次以上'
        END as activity_level,
        COUNT(*) as user_count
     FROM (
        SELECT u.id, COUNT(ao.id) as order_count
        FROM users u
        LEFT JOIN analysis_orders ao ON u.id = ao.user_id
        GROUP BY u.id
     ) user_orders
     GROUP BY activity_level
     ORDER BY 
        CASE activity_level
            WHEN '未使用' THEN 1
            WHEN '使用1次' THEN 2
            WHEN '使用2-5次' THEN 3
            WHEN '使用6-10次' THEN 4
            ELSE 5
        END"
);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据统计 - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .chart-container {
            position: relative;
            height: 300px;
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
                            <a class="nav-link active" href="statistics.php">
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
                <div class="pt-3 pb-2 mb-3 border-bottom d-flex justify-content-between align-items-center">
                    <h1 class="h2">数据统计</h1>
                    
                    <!-- 时间筛选 -->
                    <form method="GET" class="d-flex gap-2">
                        <input type="date" class="form-control" name="date_from" 
                               value="<?php echo $dateFrom; ?>" max="<?php echo date('Y-m-d'); ?>">
                        <input type="date" class="form-control" name="date_to" 
                               value="<?php echo $dateTo; ?>" max="<?php echo date('Y-m-d'); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>

                <!-- 核心指标卡片 -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">总用户数</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalUsers); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-success">今日新增: <?php echo $todayUsers; ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">总订单数</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalOrders); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-list-alt fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-success">今日新增: <?php echo $todayOrders; ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">总消费币数</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalCoinsConsumed); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-coins fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-info">今日消费: <?php echo $todayRevenue; ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">总充值币数</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalCoinsRecharged); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-wallet fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-warning">转化率: <?php echo $totalUsers > 0 ? round($totalOrders / $totalUsers * 100, 1) : 0; ?>%</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 图表区域 -->
                <div class="row mb-4">
                    <!-- 用户注册趋势 -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-user-plus me-2"></i>用户注册趋势
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="userTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 订单趋势 -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-success">
                                    <i class="fas fa-chart-line me-2"></i>订单趋势
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="orderTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <!-- 订单状态分布 -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-info">
                                    <i class="fas fa-chart-pie me-2"></i>订单状态分布
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 用户活跃度 -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-warning">
                                    <i class="fas fa-chart-bar me-2"></i>用户活跃度分布
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="activityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 热门时段分析 -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-dark">
                                    <i class="fas fa-clock me-2"></i>分析热门时段
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="hourlyChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 详细数据表格 -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">每日用户注册</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>日期</th>
                                                <th>注册数量</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_reverse($userTrend) as $trend): ?>
                                            <tr>
                                                <td><?php echo $trend['date']; ?></td>
                                                <td><span class="badge bg-primary"><?php echo $trend['count']; ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-success">每日订单统计</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>日期</th>
                                                <th>订单数</th>
                                                <th>收入币数</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_reverse($orderTrend) as $trend): ?>
                                            <tr>
                                                <td><?php echo $trend['date']; ?></td>
                                                <td><span class="badge bg-success"><?php echo $trend['count']; ?></span></td>
                                                <td><span class="badge bg-warning text-dark"><?php echo $trend['revenue']; ?></span></td>
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
    <script>
        // 用户注册趋势图
        const userTrendCtx = document.getElementById('userTrendChart').getContext('2d');
        new Chart(userTrendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($userTrend, 'date')); ?>,
                datasets: [{
                    label: '新注册用户',
                    data: <?php echo json_encode(array_column($userTrend, 'count')); ?>,
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // 订单趋势图
        const orderTrendCtx = document.getElementById('orderTrendChart').getContext('2d');
        new Chart(orderTrendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($orderTrend, 'date')); ?>,
                datasets: [{
                    label: '订单数量',
                    data: <?php echo json_encode(array_column($orderTrend, 'count')); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1,
                    fill: true,
                    yAxisID: 'y'
                }, {
                    label: '收入币数',
                    data: <?php echo json_encode(array_column($orderTrend, 'revenue')); ?>,
                    borderColor: 'rgb(255, 205, 86)',
                    backgroundColor: 'rgba(255, 205, 86, 0.1)',
                    tension: 0.1,
                    fill: true,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // 订单状态分布饼图
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusLabels = <?php echo json_encode(array_column($orderStatusStats, 'status')); ?>;
        const statusData = <?php echo json_encode(array_column($orderStatusStats, 'count')); ?>;
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels.map(status => {
                    const statusMap = {
                        'pending': '待处理',
                        'processing': '处理中',
                        'completed': '已完成',
                        'failed': '失败'
                    };
                    return statusMap[status] || status;
                }),
                datasets: [{
                    data: statusData,
                    backgroundColor: [
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(13, 202, 240, 0.8)',
                        'rgba(25, 135, 84, 0.8)',
                        'rgba(220, 53, 69, 0.8)'
                    ],
                    borderColor: [
                        'rgba(255, 193, 7, 1)',
                        'rgba(13, 202, 240, 1)',
                        'rgba(25, 135, 84, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // 用户活跃度图
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        new Chart(activityCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($userActivityStats, 'activity_level')); ?>,
                datasets: [{
                    label: '用户数量',
                    data: <?php echo json_encode(array_column($userActivityStats, 'user_count')); ?>,
                    backgroundColor: 'rgba(255, 159, 64, 0.8)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // 热门时段图
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        const hourlyData = new Array(24).fill(0);
        <?php foreach ($hourlyStats as $stat): ?>
        hourlyData[<?php echo $stat['hour']; ?>] = <?php echo $stat['count']; ?>;
        <?php endforeach; ?>
        
        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: Array.from({length: 24}, (_, i) => i + ':00'),
                datasets: [{
                    label: '订单数量',
                    data: hourlyData,
                    backgroundColor: 'rgba(153, 102, 255, 0.8)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>