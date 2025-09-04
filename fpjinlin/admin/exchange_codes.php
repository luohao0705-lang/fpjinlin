<?php
/**
 * 兑换码管理页面
 * 复盘精灵系统
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Admin.php';
require_once __DIR__ . '/../includes/ExchangeCode.php';

$adminManager = new Admin();
$exchangeCodeManager = new ExchangeCode();

// 检查登录状态
$adminManager->requireLogin();

$currentAdmin = $adminManager->getCurrentAdmin();

$error = '';
$success = '';

// 处理操作请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'generate_codes':
                $coinsValue = intval($_POST['coins_value'] ?? 0);
                $quantity = intval($_POST['quantity'] ?? 0);
                $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
                
                if ($coinsValue <= 0) {
                    throw new Exception('精灵币面值必须大于0');
                }
                
                if ($quantity <= 0 || $quantity > 1000) {
                    throw new Exception('生成数量必须在1-1000之间');
                }
                
                $result = $exchangeCodeManager->generateCodes($coinsValue, $quantity, $currentAdmin['id'], $expiresAt);
                
                $success = "成功生成 {$quantity} 个兑换码，批次ID：{$result['batch_id']}";
                
                // 记录操作日志
                $adminManager->logAction($currentAdmin['id'], 'generate_codes', 'exchange_codes', null, 
                    "生成兑换码：{$quantity}个，面值{$coinsValue}，批次{$result['batch_id']}");
                
                break;
                
            case 'delete_batch':
                $batchId = sanitizeInput($_POST['batch_id'] ?? '');
                if (empty($batchId)) {
                    throw new Exception('批次ID不能为空');
                }
                
                $exchangeCodeManager->deleteBatch($batchId, $currentAdmin['id']);
                $success = "批次 {$batchId} 删除成功";
                
                // 记录操作日志
                $adminManager->logAction($currentAdmin['id'], 'delete_batch', 'exchange_codes', null, 
                    "删除兑换码批次：{$batchId}");
                
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 获取筛选参数
$filters = [
    'status' => sanitizeInput($_GET['status'] ?? ''),
    'batch_id' => sanitizeInput($_GET['batch_id'] ?? ''),
    'coins_value' => intval($_GET['coins_value'] ?? 0),
    'start_date' => sanitizeInput($_GET['start_date'] ?? ''),
    'end_date' => sanitizeInput($_GET['end_date'] ?? '')
];

$page = intval($_GET['page'] ?? 1);

// 获取兑换码列表
$codesData = $exchangeCodeManager->getCodes($page, ADMIN_PAGE_SIZE, $filters);

// 获取批次列表
$batches = $exchangeCodeManager->getAllBatches();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>兑换码管理 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
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
        
        .code-display {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #2c3e50;
            cursor: pointer;
        }
        
        .batch-card {
            border-left: 4px solid #3498db;
            transition: transform 0.2s ease;
        }
        
        .batch-card:hover {
            transform: translateX(5px);
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
                <a class="nav-link" href="index.php">
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
                <a class="nav-link active" href="exchange_codes.php">
                    <i class="bi bi-ticket-perforated"></i> 兑换码管理
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="bi bi-gear"></i> 系统设置
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logs.php">
                    <i class="bi bi-clock-history"></i> 操作日志
                </a>
            </li>
        </ul>
    </nav>
    
    <!-- 主内容区域 -->
    <div class="main-content">
        <!-- 顶部导航 -->
        <div class="top-navbar d-flex justify-content-between align-items-center">
            <h3 class="mb-0">兑换码管理</h3>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateModal">
                    <i class="bi bi-plus-circle"></i> 生成兑换码
                </button>
            </div>
        </div>
        
        <div class="p-4">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <!-- 批次概览 -->
            <?php if (!empty($batches)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="mb-3">
                            <i class="bi bi-collection"></i> 批次概览
                        </h5>
                        <div class="row">
                            <?php foreach (array_slice($batches, 0, 4) as $batch): ?>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card batch-card">
                                        <div class="card-body">
                                            <h6 class="card-title text-primary">
                                                <?php echo $batch['coins_value']; ?> 精灵币批次
                                            </h6>
                                            <p class="text-muted small mb-2">
                                                <?php echo $batch['batch_id']; ?>
                                            </p>
                                            <div class="d-flex justify-content-between">
                                                <small>总数：<?php echo $batch['total_codes']; ?></small>
                                                <small>已用：<?php echo $batch['used_codes']; ?></small>
                                                <small>剩余：<?php echo $batch['unused_codes']; ?></small>
                                            </div>
                                            <div class="mt-2">
                                                <a href="?batch_id=<?php echo urlencode($batch['batch_id']); ?>" 
                                                   class="btn btn-sm btn-outline-primary">查看详情</a>
                                                <a href="export_codes.php?batch_id=<?php echo urlencode($batch['batch_id']); ?>" 
                                                   class="btn btn-sm btn-outline-success">导出</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- 筛选器 -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">状态</label>
                            <select class="form-select" name="status">
                                <option value="">全部</option>
                                <option value="unused" <?php echo $filters['status'] === 'unused' ? 'selected' : ''; ?>>未使用</option>
                                <option value="used" <?php echo $filters['status'] === 'used' ? 'selected' : ''; ?>>已使用</option>
                                <option value="expired" <?php echo $filters['status'] === 'expired' ? 'selected' : ''; ?>>已过期</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">面值</label>
                            <input type="number" class="form-control" name="coins_value" 
                                   value="<?php echo $filters['coins_value'] ?: ''; ?>" placeholder="精灵币">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">批次ID</label>
                            <input type="text" class="form-control" name="batch_id" 
                                   value="<?php echo htmlspecialchars($filters['batch_id']); ?>" placeholder="批次ID">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">开始日期</label>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?php echo $filters['start_date']; ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">结束日期</label>
                            <input type="date" class="form-control" name="end_date" 
                                   value="<?php echo $filters['end_date']; ?>">
                        </div>
                        
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 兑换码列表 -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">兑换码列表</h5>
                        <div>
                            <small class="text-muted">
                                共 <?php echo number_format($codesData['total']); ?> 条记录
                            </small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($codesData['codes'])): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-4 text-muted"></i>
                            <h5 class="mt-3 text-muted">暂无兑换码</h5>
                            <p class="text-muted">点击上方按钮生成兑换码</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>兑换码</th>
                                        <th>面值</th>
                                        <th>状态</th>
                                        <th>批次ID</th>
                                        <th>使用者</th>
                                        <th>创建时间</th>
                                        <th>过期时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($codesData['codes'] as $code): ?>
                                        <tr>
                                            <td>
                                                <span class="code-display" onclick="copyCode('<?php echo $code['code']; ?>')" 
                                                      title="点击复制">
                                                    <?php echo $code['code']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $code['coins_value']; ?> 币</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($code['status']) {
                                                        'unused' => 'success',
                                                        'used' => 'secondary',
                                                        'expired' => 'warning',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php 
                                                    echo match($code['status']) {
                                                        'unused' => '未使用',
                                                        'used' => '已使用',
                                                        'expired' => '已过期',
                                                        default => $code['status']
                                                    };
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo $code['batch_id']; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($code['used_by_phone']): ?>
                                                    <div>
                                                        <?php echo htmlspecialchars($code['used_by_nickname'] ?: ''); ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo $code['used_by_phone']; ?></small>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo date('m-d H:i', strtotime($code['used_at'])); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo date('Y-m-d H:i', strtotime($code['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($code['expires_at']): ?>
                                                    <small class="<?php echo strtotime($code['expires_at']) < time() ? 'text-danger' : 'text-muted'; ?>">
                                                        <?php echo date('Y-m-d', strtotime($code['expires_at'])); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-success">永久有效</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-secondary" 
                                                            onclick="copyCode('<?php echo $code['code']; ?>')" 
                                                            title="复制兑换码">
                                                        <i class="bi bi-copy"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- 分页 -->
                        <?php if ($codesData['totalPages'] > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $codesData['totalPages']; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 生成兑换码模态框 -->
    <div class="modal fade" id="generateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle"></i> 生成兑换码
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="generate_codes">
                        
                        <div class="mb-3">
                            <label for="coins_value" class="form-label">精灵币面值 <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="coins_value" name="coins_value" 
                                       min="1" max="10000" required placeholder="例如：100">
                                <span class="input-group-text">精灵币</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="quantity" class="form-label">生成数量 <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="quantity" name="quantity" 
                                   min="1" max="1000" required placeholder="例如：50">
                            <div class="form-text">单次最多生成1000个</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="expires_at" class="form-label">过期时间</label>
                            <input type="datetime-local" class="form-control" id="expires_at" name="expires_at">
                            <div class="form-text">留空表示永不过期</div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            生成的兑换码将自动分组为一个批次，便于管理和统计
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-magic"></i> 生成兑换码
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- 删除批次确认模态框 -->
    <div class="modal fade" id="deleteBatchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="bi bi-exclamation-triangle"></i> 确认删除
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>确认要删除批次 <strong id="deleteBatchId"></strong> 吗？</p>
                    <p class="text-danger">
                        <i class="bi bi-exclamation-triangle"></i> 
                        只能删除包含未使用兑换码的批次，已使用的兑换码不会被删除
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_batch">
                        <input type="hidden" name="batch_id" id="deleteBatchIdInput">
                        <button type="submit" class="btn btn-danger">确认删除</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 复制兑换码
        function copyCode(code) {
            navigator.clipboard.writeText(code).then(function() {
                // 显示复制成功提示
                const toast = document.createElement('div');
                toast.className = 'toast align-items-center text-white bg-success border-0';
                toast.style.position = 'fixed';
                toast.style.top = '20px';
                toast.style.right = '20px';
                toast.style.zIndex = '9999';
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            兑换码已复制：${code}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.parentElement.parentElement.remove()"></button>
                    </div>
                `;
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.remove();
                    }
                }, 3000);
            }).catch(function() {
                alert('复制失败，兑换码：' + code);
            });
        }
        
        // 删除批次
        function deleteBatch(batchId) {
            document.getElementById('deleteBatchId').textContent = batchId;
            document.getElementById('deleteBatchIdInput').value = batchId;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteBatchModal'));
            modal.show();
        }
        
        // 表单验证
        document.querySelector('#generateModal form').addEventListener('submit', function(e) {
            const coinsValue = parseInt(document.getElementById('coins_value').value);
            const quantity = parseInt(document.getElementById('quantity').value);
            
            if (coinsValue <= 0) {
                e.preventDefault();
                alert('精灵币面值必须大于0');
                return false;
            }
            
            if (quantity <= 0 || quantity > 1000) {
                e.preventDefault();
                alert('生成数量必须在1-1000之间');
                return false;
            }
            
            if (!confirm(`确认生成 ${quantity} 个面值 ${coinsValue} 的兑换码？`)) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>