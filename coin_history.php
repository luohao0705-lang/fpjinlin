<?php
/**
 * 复盘精灵 - 精灵币记录页面
 */
require_once 'config/config.php';
require_once 'config/database.php';

// 检查用户登录状态
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userObj = new User();
$user = $userObj->getUserById($_SESSION['user_id']);

// 分页参数
$page = max(1, intval($_GET['page'] ?? 1));
$pageSize = 20;

// 获取交易记录
$transactionData = $userObj->getCoinTransactions($_SESSION['user_id'], $page, $pageSize);
$transactions = $transactionData['transactions'];
$totalPages = $transactionData['totalPages'];

// 获取统计数据
$db = new Database();
$stats = $db->fetchOne(
    "SELECT 
        SUM(CASE WHEN type = 'recharge' THEN amount ELSE 0 END) as total_recharged,
        SUM(CASE WHEN type = 'consume' THEN amount ELSE 0 END) as total_consumed,
        COUNT(CASE WHEN type = 'recharge' THEN 1 END) as recharge_count,
        COUNT(CASE WHEN type = 'consume' THEN 1 END) as consume_count
     FROM coin_transactions 
     WHERE user_id = ?",
    [$_SESSION['user_id']]
);

$stats['total_recharged'] = $stats['total_recharged'] ?: 0;
$stats['total_consumed'] = $stats['total_consumed'] ?: 0;
$stats['recharge_count'] = $stats['recharge_count'] ?: 0;
$stats['consume_count'] = $stats['consume_count'] ?: 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>精灵币记录 - <?php echo APP_NAME; ?></title>
    
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
                        <a class="nav-link" href="index.php">首页</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create_analysis.php">创建分析</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_orders.php">我的订单</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="recharge.php">充值中心</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($user['nickname']); ?>
                            <span class="badge bg-warning text-dark ms-2"><?php echo $user['jingling_coins']; ?>币</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit me-2"></i>个人资料</a></li>
                            <li><a class="dropdown-item active" href="coin_history.php"><i class="fas fa-coins me-2"></i>精灵币记录</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>退出登录</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- 主要内容 -->
    <main class="container my-5">
        <div class="row">
            <div class="col-12">
                <!-- 页面标题 -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="fas fa-coins text-warning me-2"></i>精灵币记录
                    </h2>
                    <div class="d-flex gap-2">
                        <a href="recharge.php" class="btn btn-warning">
                            <i class="fas fa-plus me-2"></i>立即充值
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>返回首页
                        </a>
                    </div>
                </div>

                <!-- 统计卡片 -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">当前余额</h6>
                                        <h4 class="mb-0"><?php echo $user['jingling_coins']; ?></h4>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-wallet fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">累计充值</h6>
                                        <h4 class="mb-0"><?php echo $stats['total_recharged']; ?></h4>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-arrow-up fa-2x opacity-75"></i>
                                    </div>
                                </div>
                                <small class="opacity-75"><?php echo $stats['recharge_count']; ?> 次充值</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">累计消费</h6>
                                        <h4 class="mb-0"><?php echo $stats['total_consumed']; ?></h4>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-arrow-down fa-2x opacity-75"></i>
                                    </div>
                                </div>
                                <small class="opacity-75"><?php echo $stats['consume_count']; ?> 次消费</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">节省金额</h6>
                                        <h4 class="mb-0"><?php echo $stats['total_consumed'] * 2; ?></h4>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-piggy-bank fa-2x opacity-75"></i>
                                    </div>
                                </div>
                                <small class="opacity-75">相比市场价格</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 交易记录 -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history me-2"></i>交易记录
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($transactions)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">暂无交易记录</h5>
                            <p class="text-muted mb-4">您还没有任何精灵币交易记录</p>
                            <a href="recharge.php" class="btn btn-warning">
                                <i class="fas fa-plus me-2"></i>立即充值
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>时间</th>
                                        <th>类型</th>
                                        <th>金额</th>
                                        <th>余额</th>
                                        <th>描述</th>
                                        <th>相关订单</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('Y-m-d H:i:s', strtotime($transaction['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($transaction['type'] == 'recharge'): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-plus me-1"></i>充值
                                            </span>
                                            <?php elseif ($transaction['type'] == 'consume'): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-minus me-1"></i>消费
                                            </span>
                                            <?php elseif ($transaction['type'] == 'refund'): ?>
                                            <span class="badge bg-info">
                                                <i class="fas fa-undo me-1"></i>退款
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($transaction['type'] == 'recharge' || $transaction['type'] == 'refund'): ?>
                                            <span class="text-success fw-bold">+<?php echo $transaction['amount']; ?></span>
                                            <?php else: ?>
                                            <span class="text-danger fw-bold">-<?php echo $transaction['amount']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold"><?php echo $transaction['balance_after']; ?></td>
                                        <td>
                                            <?php if ($transaction['description']): ?>
                                                <?php echo htmlspecialchars($transaction['description']); ?>
                                            <?php elseif ($transaction['exchange_code']): ?>
                                                兑换码: <?php echo htmlspecialchars($transaction['exchange_code']); ?>
                                            <?php elseif ($transaction['order_title']): ?>
                                                分析订单: <?php echo htmlspecialchars($transaction['order_title']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($transaction['related_order_id']): ?>
                                            <a href="report.php?id=<?php echo $transaction['related_order_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i>查看
                                            </a>
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
                        <nav aria-label="交易记录分页">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">
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
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 页面加载完成后的初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 添加动画效果
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.3s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>