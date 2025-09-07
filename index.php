<?php
/**
 * 复盘精灵 - 首页
 */
require_once 'config/config.php';
require_once 'config/database.php';

// 检查用户登录状态
$isLoggedIn = SessionManager::isLoggedIn('user');
$user = null;

if ($isLoggedIn) {
    $userObj = new User();
    $user = $userObj->getUserById(SessionManager::getUserId('user'));
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - 视频号直播复盘分析专家</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- 自定义样式 -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-magic me-2"></i><?php echo APP_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">首页</a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="create_analysis.php">创建分析</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_orders.php">我的订单</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="recharge.php">充值中心</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="help.php">帮助中心</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if ($isLoggedIn): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($user['nickname']); ?>
                            <span class="badge bg-warning text-dark ms-2"><?php echo $user['jingling_coins']; ?>币</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit me-2"></i>个人资料</a></li>
                            <li><a class="dropdown-item" href="coin_history.php"><i class="fas fa-coins me-2"></i>精灵币记录</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>退出登录</a></li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">登录</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">注册</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- 主要内容 -->
    <main class="container my-5">
        <!-- 英雄区域 -->
        <div class="hero-section text-center mb-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold text-primary mb-3">
                        <i class="fas fa-magic me-3"></i>复盘精灵
                    </h1>
                    <p class="lead text-muted mb-4">
                        专业的视频号直播复盘分析平台，AI驱动的话术优化专家
                    </p>
                    <?php if (!$isLoggedIn): ?>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <a href="register.php" class="btn btn-primary btn-lg me-md-2">
                            <i class="fas fa-user-plus me-2"></i>立即注册
                        </a>
                        <a href="login.php" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>用户登录
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <a href="create_analysis.php" class="btn btn-primary btn-lg me-md-2">
                            <i class="fas fa-plus me-2"></i>创建分析
                        </a>
                        <a href="my_orders.php" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-list me-2"></i>我的订单
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 功能特色 -->
        <div class="row mb-5">
            <div class="col-lg-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-brain fa-3x text-primary"></i>
                        </div>
                        <h5 class="card-title">AI智能分析</h5>
                        <p class="card-text">基于DeepSeek大模型，深度分析直播话术的逻辑结构、情感调动和转化效果</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-chart-line fa-3x text-success"></i>
                        </div>
                        <h5 class="card-title">同行对比</h5>
                        <p class="card-text">对比分析同行话术优势，识别差异化机会，提供具体的优化建议</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-file-export fa-3x text-info"></i>
                        </div>
                        <h5 class="card-title">专业报告</h5>
                        <p class="card-text">生成专业的分析报告，支持在线查看、链接分享、图片导出和PDF下载</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 使用流程 -->
        <div class="row mb-5">
            <div class="col-12">
                <h2 class="text-center mb-4">使用流程</h2>
                <div class="row">
                    <div class="col-md-3 text-center mb-3">
                        <div class="step-number bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <span class="fw-bold">1</span>
                        </div>
                        <h6>注册账户</h6>
                        <p class="text-muted small">使用手机号快速注册</p>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <div class="step-number bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <span class="fw-bold">2</span>
                        </div>
                        <h6>上传数据</h6>
                        <p class="text-muted small">上传直播截图和话术文本</p>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <div class="step-number bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <span class="fw-bold">3</span>
                        </div>
                        <h6>AI分析</h6>
                        <p class="text-muted small">AI深度分析生成报告</p>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <div class="step-number bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <span class="fw-bold">4</span>
                        </div>
                        <h6>获取报告</h6>
                        <p class="text-muted small">查看报告并导出分享</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 最新动态 -->
        <?php if ($isLoggedIn): ?>
        <div class="row">
            <div class="col-12">
                <h3>我的最新订单</h3>
                <div id="recent-orders">
                    <!-- 这里通过AJAX加载最新订单 -->
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- 页脚 -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h6><?php echo APP_NAME; ?></h6>
                    <p class="text-muted small">专业的视频号直播复盘分析平台</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted small">
                        版本 <?php echo APP_VERSION; ?> | 
                        <a href="mailto:support@fupanjingling.com" class="text-light">技术支持</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- 自定义JS -->
    <script src="assets/js/app.js"></script>
    
    <?php if ($isLoggedIn): ?>
    <script>
        // 加载最新订单
        $(document).ready(function() {
            loadRecentOrders();
        });
        
        function loadRecentOrders() {
            $.get('api/user_orders.php?limit=5', function(data) {
                if (data.success && data.orders.length > 0) {
                    let html = '<div class="table-responsive"><table class="table table-striped">';
                    html += '<thead><tr><th>订单号</th><th>标题</th><th>状态</th><th>评分</th><th>创建时间</th><th>操作</th></tr></thead><tbody>';
                    
                    data.orders.forEach(function(order) {
                        let statusBadge = getStatusBadge(order.status);
                        let score = order.report_score ? order.report_score + '分' : '-';
                        html += `<tr>
                            <td>${order.order_no}</td>
                            <td>${order.title}</td>
                            <td>${statusBadge}</td>
                            <td>${score}</td>
                            <td>${formatDateTime(order.created_at)}</td>
                            <td>
                                ${order.status === 'completed' ? 
                                    `<a href="report.php?id=${order.id}" class="btn btn-sm btn-primary">查看报告</a>` : 
                                    '<span class="text-muted">处理中...</span>'
                                }
                            </td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                    html += '<div class="text-center mt-3"><a href="my_orders.php" class="btn btn-outline-primary">查看全部订单</a></div>';
                    
                    $('#recent-orders').html(html);
                } else {
                    $('#recent-orders').html('<div class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2"></i><br>暂无订单</div>');
                }
            });
        }
    </script>
    <?php endif; ?>
</body>
</html>