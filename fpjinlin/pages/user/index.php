<?php
/**
 * 用户首页
 * 复盘精灵系统
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/User.php';
require_once __DIR__ . '/../../includes/AnalysisOrder.php';

$userManager = new User();
$orderManager = new AnalysisOrder();

// 检查登录状态
$userManager->requireLogin();

$currentUser = $userManager->getCurrentUser();
$recentOrders = $userManager->getUserOrders($currentUser['id'], 1, 5);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 智能直播复盘分析平台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: var(--primary-gradient);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .hero-section {
            background: var(--primary-gradient);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .btn-gradient {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .order-card {
            background: white;
            border-radius: 10px;
            border: none;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        
        .order-card:hover {
            transform: translateY(-2px);
        }
        
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-processing {
            background-color: #cff4fc;
            color: #055160;
        }
        
        .status-completed {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        
        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-magic"></i> <?php echo SITE_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="bi bi-house"></i> 首页
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create_order.php">
                            <i class="bi bi-plus-circle"></i> 新建分析
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="bi bi-list-ul"></i> 我的订单
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="recharge.php">
                            <i class="bi bi-coin"></i> 充值中心
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($currentUser['nickname']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> 个人资料</a></li>
                            <li><a class="dropdown-item" href="transactions.php"><i class="bi bi-clock-history"></i> 交易记录</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> 退出登录</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Hero区域 -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-5 fw-bold mb-3">
                        欢迎回来，<?php echo htmlspecialchars($currentUser['nickname']); ?>！
                    </h1>
                    <p class="lead mb-4">
                        使用AI智能分析，让你的直播带货效果更上一层楼
                    </p>
                    <a href="create_order.php" class="btn btn-light btn-lg">
                        <i class="bi bi-plus-circle"></i> 开始新的分析
                    </a>
                </div>
                <div class="col-lg-4 text-center">
                    <div class="stats-summary">
                        <h3 class="mb-2"><?php echo $currentUser['spirit_coins']; ?></h3>
                        <p class="mb-0">可用精灵币</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <div class="container">
        <!-- 数据统计 -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card text-center">
                    <div class="stats-icon mx-auto" style="background: rgba(102, 126, 234, 0.1); color: #667eea;">
                        <i class="bi bi-coin"></i>
                    </div>
                    <h4 class="fw-bold"><?php echo number_format($currentUser['spirit_coins']); ?></h4>
                    <p class="text-muted mb-0">精灵币余额</p>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card text-center">
                    <div class="stats-icon mx-auto" style="background: rgba(245, 87, 108, 0.1); color: #f5576c;">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <h4 class="fw-bold"><?php echo $currentUser['total_reports']; ?></h4>
                    <p class="text-muted mb-0">分析报告</p>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card text-center">
                    <div class="stats-icon mx-auto" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <h4 class="fw-bold">
                        <?php 
                        $completedOrders = 0;
                        foreach ($recentOrders['orders'] as $order) {
                            if ($order['status'] === 'completed') $completedOrders++;
                        }
                        echo $completedOrders;
                        ?>
                    </h4>
                    <p class="text-muted mb-0">已完成分析</p>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card text-center">
                    <div class="stats-icon mx-auto" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <h4 class="fw-bold">
                        <?php 
                        $processingOrders = 0;
                        foreach ($recentOrders['orders'] as $order) {
                            if (in_array($order['status'], ['pending', 'processing'])) $processingOrders++;
                        }
                        echo $processingOrders;
                        ?>
                    </h4>
                    <p class="text-muted mb-0">处理中</p>
                </div>
            </div>
        </div>
        
        <!-- 快速操作 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-lightning-charge"></i> 快速操作
                        </h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <a href="create_order.php" class="btn btn-gradient w-100">
                                    <i class="bi bi-plus-circle"></i> 创建新的分析订单
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="recharge.php" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-coin"></i> 充值精灵币
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 最近订单 -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-clock-history"></i> 最近订单
                            </h5>
                            <a href="orders.php" class="btn btn-sm btn-outline-primary">
                                查看全部 <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentOrders['orders'])): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-4 text-muted"></i>
                                <h5 class="mt-3 text-muted">暂无订单</h5>
                                <p class="text-muted">开始你的第一次直播复盘分析吧！</p>
                                <a href="create_order.php" class="btn btn-gradient">
                                    <i class="bi bi-plus-circle"></i> 创建订单
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($recentOrders['orders'] as $order): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="order-card card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0">
                                                        <?php echo htmlspecialchars($order['title']); ?>
                                                    </h6>
                                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                                        <?php 
                                                        $statusText = [
                                                            'pending' => '待处理',
                                                            'processing' => '分析中',
                                                            'completed' => '已完成',
                                                            'failed' => '失败'
                                                        ];
                                                        echo $statusText[$order['status']] ?? $order['status'];
                                                        ?>
                                                    </span>
                                                </div>
                                                
                                                <p class="text-muted small mb-2">
                                                    订单号：<?php echo $order['order_no']; ?>
                                                </p>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="bi bi-calendar"></i> 
                                                        <?php echo date('m-d H:i', strtotime($order['created_at'])); ?>
                                                    </small>
                                                    
                                                    <?php if ($order['status'] === 'completed'): ?>
                                                        <a href="report.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                                            查看报告
                                                        </a>
                                                    <?php elseif ($order['status'] === 'pending'): ?>
                                                        <a href="edit_order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            继续编辑
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="badge bg-info">处理中</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 功能介绍 -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-info-circle"></i> 如何使用复盘精灵
                        </h5>
                        <div class="row">
                            <div class="col-md-3 text-center mb-3">
                                <div class="feature-icon mb-2">
                                    <i class="bi bi-upload display-4 text-primary"></i>
                                </div>
                                <h6>上传数据</h6>
                                <p class="text-muted small">上传直播截图、话术文本等数据</p>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="feature-icon mb-2">
                                    <i class="bi bi-cpu display-4 text-success"></i>
                                </div>
                                <h6>AI分析</h6>
                                <p class="text-muted small">智能分析直播数据，生成专业报告</p>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="feature-icon mb-2">
                                    <i class="bi bi-file-earmark-text display-4 text-warning"></i>
                                </div>
                                <h6>查看报告</h6>
                                <p class="text-muted small">获取详细的分析报告和改进建议</p>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="feature-icon mb-2">
                                    <i class="bi bi-share display-4 text-info"></i>
                                </div>
                                <h6>分享导出</h6>
                                <p class="text-muted small">分享报告链接或导出PDF文件</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>