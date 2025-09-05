<?php
/**
 * 我的订单页面
 */
require_once 'config/config.php';
require_once 'config/database.php';

// 检查用户登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user = new User();
$userInfo = $user->getUserById($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的订单 - <?php echo APP_NAME; ?></title>
    
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
            
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-coins text-warning me-1"></i>
                    余额：<strong><?php echo $userInfo['jingling_coins']; ?></strong> 精灵币
                </span>
                <a class="nav-link" href="create_analysis.php">
                    <i class="fas fa-plus me-1"></i>创建分析
                </a>
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home me-1"></i>返回首页
                </a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <!-- 页面标题 -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="fas fa-list text-primary me-2"></i>我的订单</h2>
                <p class="text-muted">查看您的所有分析订单和报告</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="create_analysis.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>创建新分析
                </a>
            </div>
        </div>
        
        <!-- 筛选器 -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">状态筛选</label>
                        <select class="form-select" id="status-filter">
                            <option value="">全部状态</option>
                            <option value="pending">待处理</option>
                            <option value="processing">处理中</option>
                            <option value="completed">已完成</option>
                            <option value="failed">失败</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">创建时间</label>
                        <select class="form-select" id="date-filter">
                            <option value="">全部时间</option>
                            <option value="today">今天</option>
                            <option value="week">最近一周</option>
                            <option value="month">最近一月</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">搜索标题</label>
                        <input type="text" class="form-control" id="title-search" placeholder="输入标题关键词搜索...">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-primary w-100" onclick="loadOrders()">
                            <i class="fas fa-search me-1"></i>搜索
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 订单列表 -->
        <div class="card">
            <div class="card-body">
                <div id="orders-container">
                    <!-- 订单列表将通过AJAX加载 -->
                    <div class="text-center py-5">
                        <div class="loading"></div>
                        <p class="text-muted mt-3">加载中...</p>
                    </div>
                </div>
                
                <!-- 分页 -->
                <nav id="pagination-container" class="mt-4" style="display: none;">
                    <ul class="pagination justify-content-center" id="pagination">
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- 自定义JS -->
    <script src="assets/js/app.js"></script>
    
    <script>
        let currentPage = 1;
        
        // 页面加载完成
        $(document).ready(function() {
            loadOrders();
            
            // 绑定筛选器事件
            $('#status-filter, #date-filter').on('change', function() {
                currentPage = 1;
                loadOrders();
            });
            
            // 搜索框防抖
            $('#title-search').on('input', debounce(function() {
                currentPage = 1;
                loadOrders();
            }, 500));
        });
        
        // 加载订单列表
        function loadOrders(page = 1) {
            currentPage = page;
            
            const filters = {
                status: $('#status-filter').val(),
                date: $('#date-filter').val(),
                title: $('#title-search').val().trim(),
                page: page,
                pageSize: 10
            };
            
            $('#orders-container').html(`
                <div class="text-center py-5">
                    <div class="loading"></div>
                    <p class="text-muted mt-3">加载中...</p>
                </div>
            `);
            
            $.get('api/user_orders.php', filters, function(response) {
                if (response.success) {
                    displayOrders(response.data);
                    displayPagination(response.data);
                } else {
                    $('#orders-container').html(`
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle"></i>
                            <h5>加载失败</h5>
                            <p>${response.message}</p>
                            <button class="btn btn-primary" onclick="loadOrders()">重新加载</button>
                        </div>
                    `);
                }
            }).fail(function() {
                $('#orders-container').html(`
                    <div class="empty-state">
                        <i class="fas fa-wifi"></i>
                        <h5>网络错误</h5>
                        <p>请检查网络连接后重试</p>
                        <button class="btn btn-primary" onclick="loadOrders()">重新加载</button>
                    </div>
                `);
            });
        }
        
        // 显示订单列表
        function displayOrders(data) {
            if (!data.orders || data.orders.length === 0) {
                $('#orders-container').html(`
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h5>暂无订单</h5>
                        <p>您还没有创建任何分析订单</p>
                        <a href="create_analysis.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>创建第一个分析
                        </a>
                    </div>
                `);
                return;
            }
            
            let html = '<div class="table-responsive">';
            html += '<table class="table table-hover">';
            html += '<thead class="table-light">';
            html += '<tr><th>订单号</th><th>标题</th><th>状态</th><th>评分/等级</th><th>费用</th><th>创建时间</th><th>操作</th></tr>';
            html += '</thead><tbody>';
            
            data.orders.forEach(function(order) {
                const statusBadge = getStatusBadge(order.status);
                const score = order.report_score ? `${order.report_score}分` : '-';
                const level = order.report_level ? getLevelBadge(order.report_level) : '';
                const scoreDisplay = order.report_score ? `${score} ${level}` : '-';
                
                html += `<tr>
                    <td><code>${order.order_no}</code></td>
                    <td>
                        <strong>${order.title}</strong>
                        ${order.status === 'processing' ? '<br><small class="text-muted">AI分析中...</small>' : ''}
                    </td>
                    <td>${statusBadge}</td>
                    <td>${scoreDisplay}</td>
                    <td><span class="text-primary">${order.cost_coins}</span> 币</td>
                    <td><small>${formatDateTime(order.created_at)}</small></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            ${order.status === 'completed' ? 
                                `<a href="report.php?id=${order.id}" class="btn btn-primary">查看报告</a>` : 
                                `<span class="btn btn-outline-secondary disabled">分析中...</span>`
                            }
                        </div>
                    </td>
                </tr>`;
            });
            
            html += '</tbody></table></div>';
            
            $('#orders-container').html(html);
        }
        
        // 显示分页
        function displayPagination(data) {
            if (data.totalPages <= 1) {
                $('#pagination-container').hide();
                return;
            }
            
            $('#pagination-container').show();
            
            let html = '';
            
            // 上一页
            if (data.page > 1) {
                html += `<li class="page-item">
                    <a class="page-link" href="#" onclick="loadOrders(${data.page - 1})">上一页</a>
                </li>`;
            }
            
            // 页码
            const startPage = Math.max(1, data.page - 2);
            const endPage = Math.min(data.totalPages, data.page + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                html += `<li class="page-item ${i === data.page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadOrders(${i})">${i}</a>
                </li>`;
            }
            
            // 下一页
            if (data.page < data.totalPages) {
                html += `<li class="page-item">
                    <a class="page-link" href="#" onclick="loadOrders(${data.page + 1})">下一页</a>
                </li>`;
            }
            
            $('#pagination').html(html);
        }
    </script>
</body>
</html>