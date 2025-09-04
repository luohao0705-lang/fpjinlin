<?php
/**
 * 管理后台首页
 * 复盘精灵系统
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Admin.php';
require_once __DIR__ . '/../includes/AnalysisOrder.php';

$adminManager = new Admin();
$orderManager = new AnalysisOrder();

// 检查登录状态
$adminManager->requireLogin();

$currentAdmin = $adminManager->getCurrentAdmin();

// 获取系统统计数据
$stats = $adminManager->getSystemStats();
$todayOverview = $adminManager->getTodayOverview();

// 获取最新订单
$recentOrders = $orderManager->getAllOrders(1, 10);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --admin-primary: #2c3e50;
            --admin-secondary: #34495e;
        }
        
        .sidebar {
            background: var(--admin-primary);
            min-height: 100vh;
            width: 250px;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
        }
        
        .sidebar .nav-link {
            color: #bdc3c7;
            padding: 0.75rem 1.5rem;
            border-radius: 0;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: var(--admin-secondary);
            color: white;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 0;
        }
        
        .top-navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem 2rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid;
            transition: transform 0.2s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-card.users {
            border-left-color: #3498db;
        }
        
        .stats-card.orders {
            border-left-color: #2ecc71;
        }
        
        .stats-card.coins {
            border-left-color: #f39c12;
        }
        
        .stats-card.codes {
            border-left-color: #e74c3c;
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
                transition: margin-left 0.3s ease;
            }
            
            .sidebar.show {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- 侧边栏 -->
    <nav class="sidebar">
        <div class="p-3">
            <h4 class="text-white mb-4">
                <i class="bi bi-shield-lock"></i> 管理后台
            </h4>
        </div>
        
        <ul class="nav nav-pills flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="index.php">
                    <i class="bi bi-speedometer2"></i> 仪表盘
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="bi bi-people"></i> 用户管理
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="orders.php">
                    <i class="bi bi-list-ul"></i> 订单管理
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="exchange_codes.php">
                    <i class="bi bi-ticket-perforated"></i> 兑换码管理
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="bi bi-gear"></i> 系统设置
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admins.php">
                    <i class="bi bi-shield-check"></i> 管理员
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logs.php">
                    <i class="bi bi-clock-history"></i> 操作日志
                </a>
            </li>
        </ul>
        
        <div class="mt-auto p-3">
            <div class="text-center">
                <small class="text-muted">
                    当前用户：<?php echo htmlspecialchars($currentAdmin['real_name'] ?: $currentAdmin['username']); ?>
                </small>
                <br>
                <a href="logout.php" class="btn btn-outline-light btn-sm mt-2">
                    <i class="bi bi-box-arrow-right"></i> 退出
                </a>
            </div>
        </div>
    </nav>
    
    <!-- 主内容区域 -->
    <div class="main-content">
        <!-- 顶部导航 -->
        <div class="top-navbar d-flex justify-content-between align-items-center">
            <h3 class="mb-0">仪表盘概览</h3>
            <div>
                <span class="text-muted">
                    <i class="bi bi-calendar"></i> <?php echo date('Y年m月d日 H:i'); ?>
                </span>
            </div>
        </div>
        
        <div class="p-4">
            <!-- 今日数据概览 -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="mb-3">
                        <i class="bi bi-calendar-day"></i> 今日数据
                    </h5>
                </div>
                
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="stats-card users">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-1"><?php echo $todayOverview['new_users']; ?></h3>
                                <p class="text-muted mb-0">新增用户</p>
                            </div>
                            <div class="stats-icon" style="background-color: #3498db;">
                                <i class="bi bi-person-plus"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="stats-card orders">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-1"><?php echo $todayOverview['new_orders']; ?></h3>
                                <p class="text-muted mb-0">新增订单</p>
                            </div>
                            <div class="stats-icon" style="background-color: #2ecc71;">
                                <i class="bi bi-plus-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="stats-card orders">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-1"><?php echo $todayOverview['completed_orders']; ?></h3>
                                <p class="text-muted mb-0">完成分析</p>
                            </div>
                            <div class="stats-icon" style="background-color: #27ae60;">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="stats-card coins">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-1"><?php echo abs($todayOverview['consumed_coins']); ?></h3>
                                <p class="text-muted mb-0">消耗精灵币</p>
                            </div>
                            <div class="stats-icon" style="background-color: #f39c12;">
                                <i class="bi bi-coin"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 系统总览 -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="mb-3">
                        <i class="bi bi-bar-chart"></i> 系统总览
                    </h5>
                </div>
                
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="stats-card users">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-1"><?php echo number_format($stats['users']['total_users']); ?></h3>
                                <p class="text-muted mb-0">总用户数</p>
                                <small class="text-success">
                                    活跃：<?php echo $stats['users']['active_users']; ?>
                                </small>
                            </div>
                            <div class="stats-icon" style="background-color: #3498db;">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="stats-card orders">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-1"><?php echo number_format($stats['orders']['total_orders']); ?></h3>
                                <p class="text-muted mb-0">总订单数</p>
                                <small class="text-success">
                                    完成：<?php echo $stats['orders']['completed_orders']; ?>
                                </small>
                            </div>
                            <div class="stats-icon" style="background-color: #2ecc71;">
                                <i class="bi bi-list-ul"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="stats-card coins">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-1"><?php echo number_format($stats['coins']['total_user_coins']); ?></h3>
                                <p class="text-muted mb-0">用户总币数</p>
                                <small class="text-info">
                                    平均：<?php echo number_format($stats['coins']['avg_user_coins'], 1); ?>
                                </small>
                            </div>
                            <div class="stats-icon" style="background-color: #f39c12;">
                                <i class="bi bi-coin"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="stats-card codes">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-1"><?php echo number_format($stats['codes']['total_codes']); ?></h3>
                                <p class="text-muted mb-0">兑换码总数</p>
                                <small class="text-warning">
                                    已用：<?php echo $stats['codes']['used_codes']; ?>
                                </small>
                            </div>
                            <div class="stats-icon" style="background-color: #e74c3c;">
                                <i class="bi bi-ticket-perforated"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 图表区域 -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-graph-up"></i> 订单趋势
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="ordersChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-pie-chart"></i> 订单状态分布
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 最新订单 -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-clock-history"></i> 最新订单
                            </h5>
                            <a href="orders.php" class="btn btn-outline-primary btn-sm">
                                查看全部
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentOrders['orders'])): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-inbox display-4 text-muted"></i>
                                    <p class="text-muted mt-2">暂无订单</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>订单号</th>
                                                <th>标题</th>
                                                <th>用户</th>
                                                <th>状态</th>
                                                <th>消耗币数</th>
                                                <th>创建时间</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($recentOrders['orders'], 0, 8) as $order): ?>
                                                <tr>
                                                    <td>
                                                        <code><?php echo $order['order_no']; ?></code>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars(mb_substr($order['title'], 0, 20) . (mb_strlen($order['title']) > 20 ? '...' : '')); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($order['nickname'] ?: $order['phone']); ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo $order['phone']; ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo match($order['status']) {
                                                                'pending' => 'warning',
                                                                'processing' => 'info',
                                                                'completed' => 'success',
                                                                'failed' => 'danger',
                                                                default => 'secondary'
                                                            };
                                                        ?>">
                                                            <?php 
                                                            echo match($order['status']) {
                                                                'pending' => '待处理',
                                                                'processing' => '分析中',
                                                                'completed' => '已完成',
                                                                'failed' => '失败',
                                                                default => $order['status']
                                                            };
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $order['cost_coins']; ?></td>
                                                    <td>
                                                        <?php echo date('m-d H:i', strtotime($order['created_at'])); ?>
                                                    </td>
                                                    <td>
                                                        <a href="order_detail.php?id=<?php echo $order['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            查看
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 订单趋势图表
        const ordersCtx = document.getElementById('ordersChart').getContext('2d');
        new Chart(ordersCtx, {
            type: 'line',
            data: {
                labels: ['7天前', '6天前', '5天前', '4天前', '3天前', '2天前', '昨天', '今天'],
                datasets: [{
                    label: '新增订单',
                    data: [12, 19, 15, 17, 14, 22, 18, <?php echo $todayOverview['new_orders']; ?>],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4
                }, {
                    label: '完成订单',
                    data: [8, 15, 12, 14, 11, 18, 15, <?php echo $todayOverview['completed_orders']; ?>],
                    borderColor: '#2ecc71',
                    backgroundColor: 'rgba(46, 204, 113, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // 订单状态分布图表
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['已完成', '处理中', '待处理', '失败'],
                datasets: [{
                    data: [
                        <?php echo $stats['orders']['completed_orders']; ?>,
                        <?php echo $stats['orders']['processing_orders']; ?>,
                        <?php echo $stats['orders']['total_orders'] - $stats['orders']['completed_orders'] - $stats['orders']['processing_orders']; ?>,
                        0
                    ],
                    backgroundColor: [
                        '#2ecc71',
                        '#3498db',
                        '#f39c12',
                        '#e74c3c'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
        
        // 响应式侧边栏
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }
        
        // 自动刷新数据（每30秒）
        setInterval(function() {
            // 可以通过AJAX更新关键数据
            // location.reload();
        }, 30000);
    </script>
</body>
</html>