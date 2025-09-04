<?php
/**
 * 管理员后台首页
 */
require_once '../config/config.php';
require_once '../config/database.php';

// 检查管理员登录
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// 获取管理员信息
$db = new Database();
$admin = $db->fetchOne("SELECT * FROM admins WHERE id = ?", [$_SESSION['admin_id']]);

if (!$admin || $admin['status'] != 1) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// 获取统计数据
$analysisOrder = new AnalysisOrder();
$exchangeCode = new ExchangeCode();

$orderStats = $analysisOrder->getStatistics();
$codeStats = $exchangeCode->getStatistics();

// 获取用户统计
$userStats = $db->fetchOne("SELECT COUNT(*) as total_users FROM users WHERE status = 1")['total_users'];
$todayUsers = $db->fetchOne("SELECT COUNT(*) as today_users FROM users WHERE DATE(created_at) = CURDATE()")['today_users'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - <?php echo APP_NAME; ?></title>
    
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
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>个人资料</a></li>
                        <li><hr class="dropdown-divider"></li>
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
                            <a class="nav-link active" href="index.php">
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
                    <h1 class="h2">仪表盘</h1>
                </div>

                <!-- 统计卡片 -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">总用户数</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($userStats); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
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
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($orderStats['total_orders']); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-list-alt fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">今日订单</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($orderStats['today_orders']); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">待处理订单</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($orderStats['pending_orders']); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 图表区域 -->
                <div class="row mb-4">
                    <!-- 订单趋势图 -->
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-chart-area me-2"></i>订单趋势（最近7天）
                                </h6>
                            </div>
                            <div class="card-body">
                                <canvas id="orderTrendChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- 兑换码统计 -->
                    <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-ticket-alt me-2"></i>兑换码统计
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>总数量</span>
                                        <strong><?php echo number_format($codeStats['total_codes']); ?></strong>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>已使用</span>
                                        <strong class="text-success"><?php echo number_format($codeStats['used_codes']); ?></strong>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>未使用</span>
                                        <strong class="text-warning"><?php echo number_format($codeStats['unused_codes']); ?></strong>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>使用率</span>
                                        <strong class="text-info"><?php echo $codeStats['usage_rate']; ?>%</strong>
                                    </div>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?php echo $codeStats['usage_rate']; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 最新订单 -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-clock me-2"></i>最新订单
                        </h6>
                        <a href="orders.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-list me-1"></i>查看全部
                        </a>
                    </div>
                    <div class="card-body">
                        <div id="recent-orders-container">
                            <div class="text-center py-3">
                                <div class="loading"></div>
                                <p class="text-muted mt-2">加载中...</p>
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
    <!-- 自定义JS -->
    <script src="../assets/js/app.js"></script>
    
    <script>
        // 页面加载完成
        $(document).ready(function() {
            loadRecentOrders();
            initOrderTrendChart();
        });
        
        // 加载最新订单
        function loadRecentOrders() {
            $.get('api/orders.php?limit=10', function(response) {
                if (response.success && response.data.orders.length > 0) {
                    displayRecentOrders(response.data.orders);
                } else {
                    $('#recent-orders-container').html(`
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <p>暂无订单</p>
                        </div>
                    `);
                }
            }).fail(function() {
                $('#recent-orders-container').html(`
                    <div class="text-center py-3 text-muted">
                        <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                        <p>加载失败</p>
                    </div>
                `);
            });
        }
        
        // 显示最新订单
        function displayRecentOrders(orders) {
            let html = '<div class="table-responsive">';
            html += '<table class="table table-sm table-hover">';
            html += '<thead><tr><th>订单号</th><th>用户</th><th>标题</th><th>状态</th><th>创建时间</th><th>操作</th></tr></thead><tbody>';
            
            orders.forEach(function(order) {
                const statusBadge = getStatusBadge(order.status);
                
                html += `<tr>
                    <td><code class="small">${order.order_no}</code></td>
                    <td>
                        <small>${order.phone || '未知用户'}</small>
                        ${order.nickname ? `<br><span class="text-muted small">${order.nickname}</span>` : ''}
                    </td>
                    <td>
                        <strong class="small">${order.title}</strong>
                    </td>
                    <td>${statusBadge}</td>
                    <td><small>${formatDateTime(order.created_at)}</small></td>
                    <td>
                        <a href="order_detail.php?id=${order.id}" class="btn btn-sm btn-outline-primary">查看</a>
                    </td>
                </tr>`;
            });
            
            html += '</tbody></table></div>';
            
            $('#recent-orders-container').html(html);
        }
        
        // 初始化订单趋势图
        function initOrderTrendChart() {
            $.get('api/order_trend.php', function(response) {
                if (response.success) {
                    const ctx = document.getElementById('orderTrendChart').getContext('2d');
                    
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: response.data.labels,
                            datasets: [{
                                label: '订单数量',
                                data: response.data.values,
                                borderColor: 'rgb(75, 192, 192)',
                                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                }
            });
        }
        
        // 状态映射函数
        function getStatusBadge(status) {
            const statusMap = {
                'pending': '<span class="badge bg-secondary">待处理</span>',
                'processing': '<span class="badge bg-warning">处理中</span>',
                'completed': '<span class="badge bg-success">已完成</span>',
                'failed': '<span class="badge bg-danger">失败</span>'
            };
            
            return statusMap[status] || '<span class="badge bg-secondary">未知</span>';
        }
    </script>
</body>
</html>