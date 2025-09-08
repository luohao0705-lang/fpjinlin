<?php
/**
 * 视频分析订单管理页面
 * 复盘精灵系统 - 后台管理
 */
require_once '../config/config.php';
require_once '../config/database.php';

// 检查管理员登录
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

// 获取分页参数
$page = max(1, intval($_GET['page'] ?? 1));
$pageSize = 20;
$status = $_GET['status'] ?? '';

// 构建查询条件
$whereClause = "1=1";
$params = [];

if ($status) {
    $whereClause .= " AND vao.status = ?";
    $params[] = $status;
}

// 获取订单列表
$offset = ($page - 1) * $pageSize;
$orders = $db->fetchAll(
    "SELECT vao.*, u.phone as user_phone, u.nickname as user_nickname 
     FROM video_analysis_orders vao 
     LEFT JOIN users u ON vao.user_id = u.id 
     WHERE {$whereClause} 
     ORDER BY vao.created_at DESC 
     LIMIT ? OFFSET ?",
    array_merge($params, [$pageSize, $offset])
);

// 获取总数
$total = $db->fetchOne(
    "SELECT COUNT(*) as count FROM video_analysis_orders vao WHERE {$whereClause}",
    $params
)['count'];

// 获取统计数据
$stats = [];
$statusStats = $db->fetchAll(
    "SELECT status, COUNT(*) as count FROM video_analysis_orders GROUP BY status"
);

foreach ($statusStats as $stat) {
    $stats[$stat['status']] = $stat['count'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>视频分析订单管理 - <?php echo APP_NAME; ?> 管理后台</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-cog me-2"></i><?php echo APP_NAME; ?> 管理后台
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>退出
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- 侧边栏 -->
            <nav class="col-md-2 d-md-block bg-light sidebar">
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
                                <i class="fas fa-file-alt me-2"></i>文本分析订单
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="video_orders.php">
                                <i class="fas fa-video me-2"></i>视频分析订单
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="exchange_codes.php">
                                <i class="fas fa-gift me-2"></i>兑换码管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="system_config.php">
                                <i class="fas fa-cog me-2"></i>系统配置
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- 主要内容 -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">视频分析订单管理</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshPage()">
                                <i class="fas fa-sync-alt me-1"></i>刷新
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 统计卡片 -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">总订单</h6>
                                        <h3><?php echo $stats['total'] ?? 0; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-list fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">待审核</h6>
                                        <h3><?php echo $stats['pending'] ?? 0; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-eye fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">分析中</h6>
                                        <h3><?php echo $stats['processing'] ?? 0; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-cog fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">已完成</h6>
                                        <h3><?php echo $stats['completed'] ?? 0; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">失败</h6>
                                        <h3><?php echo $stats['failed'] ?? 0; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-times fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 筛选器 -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="status" class="form-label">状态筛选</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">全部状态</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>待审核</option>
                                    <option value="reviewing" <?php echo $status === 'reviewing' ? 'selected' : ''; ?>>审核中</option>
                                    <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>分析中</option>
                                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>已完成</option>
                                    <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>失败</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>筛选
                                    </button>
                                    <a href="video_orders.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>清除
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 订单列表 -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">订单列表</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="ordersTable">
                                <thead>
                                    <tr>
                                        <th>订单号</th>
                                        <th>用户</th>
                                        <th>标题</th>
                                        <th>状态</th>
                                        <th>消耗精灵币</th>
                                        <th>创建时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <code><?php echo htmlspecialchars($order['order_no']); ?></code>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($order['user_nickname']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($order['user_phone']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($order['title']); ?>">
                                                <?php echo htmlspecialchars($order['title']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = [
                                                'pending' => 'warning',
                                                'reviewing' => 'info',
                                                'processing' => 'primary',
                                                'completed' => 'success',
                                                'failed' => 'danger'
                                            ];
                                            $statusText = [
                                                'pending' => '待审核',
                                                'reviewing' => '审核中',
                                                'processing' => '分析中',
                                                'completed' => '已完成',
                                                'failed' => '失败'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass[$order['status']] ?? 'secondary'; ?>">
                                                <?php echo $statusText[$order['status']] ?? $order['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $order['cost_coins']; ?></span>
                                        </td>
                                        <td>
                                            <?php echo date('Y-m-d H:i:s', strtotime($order['created_at'])); ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="video_order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i>详情
                                                </a>
                                                <?php if ($order['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-outline-success" onclick="approveOrder(<?php echo $order['id']; ?>)">
                                                    <i class="fas fa-check me-1"></i>审核
                                                </button>
                                                <?php endif; ?>
                                                <?php if ($order['status'] === 'completed'): ?>
                                                <button type="button" class="btn btn-outline-info" onclick="viewReport(<?php echo $order['id']; ?>)">
                                                    <i class="fas fa-file-alt me-1"></i>报告
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- 分页 -->
                        <?php if ($total > $pageSize): ?>
                        <nav aria-label="订单分页">
                            <ul class="pagination justify-content-center">
                                <?php
                                $totalPages = ceil($total / $pageSize);
                                $currentPage = $page;
                                
                                // 上一页
                                if ($currentPage > 1):
                                    $prevPage = $currentPage - 1;
                                    $prevUrl = "?page={$prevPage}" . ($status ? "&status={$status}" : "");
                                ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo $prevUrl; ?>">上一页</a>
                                </li>
                                <?php endif; ?>
                                
                                <?php
                                // 页码
                                $startPage = max(1, $currentPage - 2);
                                $endPage = min($totalPages, $currentPage + 2);
                                
                                for ($i = $startPage; $i <= $endPage; $i++):
                                    $pageUrl = "?page={$i}" . ($status ? "&status={$status}" : "");
                                    $activeClass = $i === $currentPage ? 'active' : '';
                                ?>
                                <li class="page-item <?php echo $activeClass; ?>">
                                    <a class="page-link" href="<?php echo $pageUrl; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php
                                // 下一页
                                if ($currentPage < $totalPages):
                                    $nextPage = $currentPage + 1;
                                    $nextUrl = "?page={$nextPage}" . ($status ? "&status={$status}" : "");
                                ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo $nextUrl; ?>">下一页</a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- 审核模态框 -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">审核视频分析订单</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="approveForm">
                        <input type="hidden" id="approveOrderId" name="order_id">
                        
                        <div class="mb-3">
                            <label class="form-label">本方视频FLV地址</label>
                            <input type="url" class="form-control" id="selfFlvUrl" name="self_flv_url" 
                                   placeholder="请输入本方视频的FLV地址" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">同行1视频FLV地址</label>
                            <input type="url" class="form-control" id="competitor1FlvUrl" name="competitor_flv_urls[]" 
                                   placeholder="请输入同行1视频的FLV地址" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">同行2视频FLV地址</label>
                            <input type="url" class="form-control" id="competitor2FlvUrl" name="competitor_flv_urls[]" 
                                   placeholder="请输入同行2视频的FLV地址" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-success" onclick="submitApprove()">
                        <i class="fas fa-check me-1"></i>审核通过
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // 初始化DataTable
        $('#ordersTable').DataTable({
            "paging": false,
            "searching": false,
            "info": false,
            "ordering": false
        });
    });
    
    // 查看订单详情
    function viewOrder(orderId) {
        window.open('video_order_detail.php?id=' + orderId, '_blank');
    }
    
    // 审核订单
    function approveOrder(orderId) {
        $('#approveOrderId').val(orderId);
        $('#approveModal').modal('show');
    }
    
    // 提交审核
    function submitApprove() {
        const orderId = $('#approveOrderId').val();
        const selfFlvUrl = $('#selfFlvUrl').val();
        const competitor1FlvUrl = $('#competitor1FlvUrl').val();
        const competitor2FlvUrl = $('#competitor2FlvUrl').val();
        
        if (!selfFlvUrl || !competitor1FlvUrl || !competitor2FlvUrl) {
            alert('请填写所有FLV地址！');
            return;
        }
        
        const competitorFlvUrls = [competitor1FlvUrl, competitor2FlvUrl];
        
        $.ajax({
            url: 'api/approve_video_order.php',
            type: 'POST',
            data: {
                order_id: orderId,
                self_flv_url: selfFlvUrl,
                competitor_flv_urls: JSON.stringify(competitorFlvUrls)
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('审核通过！订单已进入分析流程。');
                    location.reload();
                } else {
                    alert('审核失败：' + response.message);
                }
            },
            error: function() {
                alert('网络错误，请稍后重试！');
            }
        });
    }
    
    // 查看报告
    function viewReport(orderId) {
        window.open('../report.php?id=' + orderId + '&type=video', '_blank');
    }
    
    // 刷新页面
    function refreshPage() {
        location.reload();
    }
    </script>
</body>
</html>
