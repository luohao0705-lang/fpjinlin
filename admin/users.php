<?php
/**
 * 管理员 - 用户管理页面
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

// 处理用户操作
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = intval($_POST['user_id'] ?? 0);
    
    if ($action == 'toggle_status' && $userId > 0) {
        $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        if ($user) {
            $newStatus = $user['status'] == 1 ? 0 : 1;
            $statusText = $newStatus == 1 ? '启用' : '禁用';
            
            if ($db->query("UPDATE users SET status = ? WHERE id = ?", [$newStatus, $userId])) {
                $message = "用户状态已{$statusText}";
                
                // 记录操作日志
                $operationLog = new OperationLog();
                $operationLog->log($_SESSION['admin_id'], 'user_status', "将用户 {$user['phone']} 状态设为{$statusText}", $userId);
            } else {
                $error = '操作失败，请重试';
            }
        }
    } elseif ($action == 'update_coins' && $userId > 0) {
        $coins = intval($_POST['coins'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        
        if ($coins != 0 && !empty($reason)) {
            $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            if ($user) {
                $newBalance = max(0, $user['jingling_coins'] + $coins);
                
                $db->beginTransaction();
                try {
                    // 更新用户余额
                    $db->query("UPDATE users SET jingling_coins = ? WHERE id = ?", [$newBalance, $userId]);
                    
                    // 记录交易
                    $transactionType = $coins > 0 ? 'admin_add' : 'admin_deduct';
                    $db->insert(
                        "INSERT INTO coin_transactions (user_id, type, amount, balance_after, description) VALUES (?, ?, ?, ?, ?)",
                        [$userId, $transactionType, abs($coins), $newBalance, $reason]
                    );
                    
                    $db->commit();
                    $message = '精灵币余额已更新';
                    
                    // 记录操作日志
                    $operationLog = new OperationLog();
                    $operationLog->log($_SESSION['admin_id'], 'user_coins', "为用户 {$user['phone']} " . ($coins > 0 ? '增加' : '扣除') . " {$coins} 精灵币，原因：{$reason}", $userId);
                } catch (Exception $e) {
                    $db->rollback();
                    $error = '操作失败：' . $e->getMessage();
                }
            }
        } else {
            $error = '请输入有效的金额和原因';
        }
    }
}

// 分页和筛选参数
$page = max(1, intval($_GET['page'] ?? 1));
$pageSize = 20;
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

// 构建查询条件
$where = "1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (phone LIKE ? OR nickname LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($status !== '') {
    $where .= " AND status = ?";
    $params[] = intval($status);
}

// 获取用户列表
$offset = ($page - 1) * $pageSize;
$users = $db->fetchAll(
    "SELECT *, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as created_time,
            DATE_FORMAT(last_login_time, '%Y-%m-%d %H:%i') as last_login_time_formatted
     FROM users 
     WHERE {$where} 
     ORDER BY created_at DESC 
     LIMIT ? OFFSET ?",
    array_merge($params, [$pageSize, $offset])
);

// 获取总数
$total = $db->fetchOne(
    "SELECT COUNT(*) as count FROM users WHERE {$where}",
    $params
)['count'];

$totalPages = ceil($total / $pageSize);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - <?php echo APP_NAME; ?></title>
    
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
                            <a class="nav-link active" href="users.php">
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
                    <h1 class="h2">用户管理</h1>
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

                <!-- 搜索和筛选 -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">搜索用户</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="手机号或昵称">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">状态筛选</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">全部状态</option>
                                    <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>正常</option>
                                    <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>禁用</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i>搜索
                                </button>
                                <a href="users.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-1"></i>重置
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 用户列表 -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-users me-2"></i>用户列表
                            <span class="badge bg-primary ms-2"><?php echo $total; ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($users)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">暂无用户</h5>
                            <p class="text-muted">没有找到符合条件的用户</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>手机号</th>
                                        <th>昵称</th>
                                        <th>精灵币</th>
                                        <th>状态</th>
                                        <th>注册时间</th>
                                        <th>最后登录</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td class="fw-bold">#<?php echo $user['id']; ?></td>
                                        <td>
                                            <span class="text-primary"><?php echo htmlspecialchars($user['phone']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['nickname']); ?></td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-coins me-1"></i><?php echo $user['jingling_coins']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['status'] == 1): ?>
                                            <span class="badge bg-success">正常</span>
                                            <?php else: ?>
                                            <span class="badge bg-danger">禁用</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo $user['created_time']; ?></small>
                                        </td>
                                        <td>
                                            <?php if ($user['last_login_time_formatted']): ?>
                                            <small class="text-muted"><?php echo $user['last_login_time_formatted']; ?></small>
                                            <?php else: ?>
                                            <small class="text-muted">从未登录</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <!-- 切换状态 -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn-sm <?php echo $user['status'] == 1 ? 'btn-outline-danger' : 'btn-outline-success'; ?>" 
                                                            onclick="return confirm('确定要<?php echo $user['status'] == 1 ? '禁用' : '启用'; ?>该用户吗？')">
                                                        <i class="fas <?php echo $user['status'] == 1 ? 'fa-ban' : 'fa-check'; ?> me-1"></i>
                                                        <?php echo $user['status'] == 1 ? '禁用' : '启用'; ?>
                                                    </button>
                                                </form>
                                                
                                                <!-- 调整精灵币 -->
                                                <button type="button" class="btn btn-sm btn-outline-warning" 
                                                        data-bs-toggle="modal" data-bs-target="#coinsModal"
                                                        data-user-id="<?php echo $user['id']; ?>"
                                                        data-user-phone="<?php echo htmlspecialchars($user['phone']); ?>"
                                                        data-user-coins="<?php echo $user['jingling_coins']; ?>">
                                                    <i class="fas fa-coins me-1"></i>调币
                                                </button>
                                                
                                                <!-- 查看详情 -->
                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                        data-bs-toggle="modal" data-bs-target="#userModal"
                                                        data-user='<?php echo json_encode($user); ?>'>
                                                    <i class="fas fa-eye me-1"></i>详情
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- 分页 -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="用户列表分页" class="mt-4">
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

    <!-- 调整精灵币模态框 -->
    <div class="modal fade" id="coinsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-coins me-2"></i>调整精灵币
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_coins">
                        <input type="hidden" name="user_id" id="coinsUserId">
                        
                        <div class="mb-3">
                            <label class="form-label">用户</label>
                            <input type="text" class="form-control" id="coinsUserInfo" readonly>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label for="coins" class="form-label">调整金额</label>
                                <input type="number" class="form-control" id="coins" name="coins" required>
                                <div class="form-text">正数为增加，负数为扣除</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">当前余额</label>
                                <input type="text" class="form-control" id="currentCoins" readonly>
                            </div>
                        </div>
                        
                        <div class="mb-3 mt-3">
                            <label for="reason" class="form-label">操作原因 <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" required 
                                      placeholder="请输入调整精灵币的原因..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-warning">确认调整</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 用户详情模态框 -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user me-2"></i>用户详情
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="30%">用户ID</th>
                                    <td id="detailUserId"></td>
                                </tr>
                                <tr>
                                    <th>手机号</th>
                                    <td id="detailUserPhone"></td>
                                </tr>
                                <tr>
                                    <th>昵称</th>
                                    <td id="detailUserNickname"></td>
                                </tr>
                                <tr>
                                    <th>精灵币</th>
                                    <td id="detailUserCoins"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="30%">状态</th>
                                    <td id="detailUserStatus"></td>
                                </tr>
                                <tr>
                                    <th>注册时间</th>
                                    <td id="detailUserCreated"></td>
                                </tr>
                                <tr>
                                    <th>最后登录</th>
                                    <td id="detailUserLastLogin"></td>
                                </tr>
                                <tr>
                                    <th>登录IP</th>
                                    <td id="detailUserLoginIp"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 调整精灵币模态框
        const coinsModal = document.getElementById('coinsModal');
        coinsModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const userPhone = button.getAttribute('data-user-phone');
            const userCoins = button.getAttribute('data-user-coins');
            
            document.getElementById('coinsUserId').value = userId;
            document.getElementById('coinsUserInfo').value = userPhone;
            document.getElementById('currentCoins').value = userCoins + ' 币';
            document.getElementById('coins').value = '';
            document.getElementById('reason').value = '';
        });

        // 用户详情模态框
        const userModal = document.getElementById('userModal');
        userModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const user = JSON.parse(button.getAttribute('data-user'));
            
            document.getElementById('detailUserId').textContent = '#' + user.id;
            document.getElementById('detailUserPhone').textContent = user.phone;
            document.getElementById('detailUserNickname').textContent = user.nickname;
            document.getElementById('detailUserCoins').innerHTML = '<span class="badge bg-warning text-dark"><i class="fas fa-coins me-1"></i>' + user.jingling_coins + '</span>';
            document.getElementById('detailUserStatus').innerHTML = user.status == 1 ? '<span class="badge bg-success">正常</span>' : '<span class="badge bg-danger">禁用</span>';
            document.getElementById('detailUserCreated').textContent = user.created_time;
            document.getElementById('detailUserLastLogin').textContent = user.last_login_time_formatted || '从未登录';
            document.getElementById('detailUserLoginIp').textContent = user.last_login_ip || '-';
        });
    </script>
</body>
</html>