<?php
/**
 * 充值中心页面
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
    <title>充值中心 - <?php echo APP_NAME; ?></title>
    
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
                    当前余额：<strong><?php echo $userInfo['jingling_coins']; ?></strong> 精灵币
                </span>
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home me-1"></i>返回首页
                </a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <!-- 页面标题 -->
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="fas fa-coins text-warning me-2"></i>充值中心</h2>
                <p class="text-muted">使用兑换码为您的账户充值精灵币</p>
            </div>
        </div>
        
        <!-- 当前余额显示 -->
        <div class="row mb-4">
            <div class="col-md-6 mx-auto">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $userInfo['jingling_coins']; ?></div>
                    <div class="stat-label">当前精灵币余额</div>
                </div>
            </div>
        </div>
        
        <!-- 兑换码充值 -->
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-ticket-alt me-2"></i>兑换码充值
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="exchange-form">
                            <div class="form-group mb-3">
                                <label for="exchange-code" class="form-label">兑换码</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-ticket-alt"></i>
                                    </span>
                                    <input type="text" class="form-control" id="exchange-code" name="code" 
                                           placeholder="请输入12位兑换码" maxlength="12" 
                                           style="letter-spacing: 2px; font-family: monospace;">
                                    <button type="submit" class="btn btn-primary" id="use-code-btn">
                                        <i class="fas fa-exchange-alt me-1"></i>立即兑换
                                    </button>
                                </div>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    兑换码格式：4位字母 + 8位数字，如：ABCD12345678
                                </div>
                            </div>
                        </form>
                        
                        <!-- 使用说明 -->
                        <div class="alert alert-info">
                            <h6><i class="fas fa-question-circle me-2"></i>使用说明</h6>
                            <ul class="mb-0 small">
                                <li>兑换码由管理员生成，请确保兑换码的准确性</li>
                                <li>每个兑换码只能使用一次，使用后立即生效</li>
                                <li>兑换的精灵币会立即到账，可用于创建分析订单</li>
                                <li>如有疑问，请联系客服获取帮助</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 充值记录 -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas fa-history me-2"></i>充值记录
                        </h6>
                        <button class="btn btn-sm btn-outline-secondary" onclick="loadTransactions()">
                            <i class="fas fa-refresh me-1"></i>刷新
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="transactions-container">
                            <!-- 交易记录将通过AJAX加载 -->
                            <div class="text-center py-3">
                                <div class="loading"></div>
                                <p class="text-muted mt-2">加载中...</p>
                            </div>
                        </div>
                    </div>
                </div>
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
        // 页面加载完成
        $(document).ready(function() {
            loadTransactions();
            
            // 兑换码输入格式化
            $('#exchange-code').on('input', function() {
                let value = $(this).val().toUpperCase().replace(/[^A-Z0-9]/g, '');
                if (value.length > 12) {
                    value = value.substring(0, 12);
                }
                $(this).val(value);
                
                // 检查是否可以提交
                $('#use-code-btn').prop('disabled', value.length !== 12);
            });
        });
        
        // 表单提交
        $('#exchange-form').on('submit', function(e) {
            e.preventDefault();
            useExchangeCode();
        });
        
        // 加载交易记录
        function loadTransactions() {
            $.get('api/coin_transactions.php', function(response) {
                if (response.success && response.data.transactions.length > 0) {
                    displayTransactions(response.data.transactions);
                } else {
                    $('#transactions-container').html(`
                        <div class="empty-state py-3">
                            <i class="fas fa-receipt"></i>
                            <h6>暂无充值记录</h6>
                            <p class="text-muted">您还没有任何充值记录</p>
                        </div>
                    `);
                }
            }).fail(function() {
                $('#transactions-container').html(`
                    <div class="text-center py-3">
                        <i class="fas fa-exclamation-circle text-danger"></i>
                        <p class="text-muted mt-2">加载失败，请重试</p>
                    </div>
                `);
            });
        }
        
        // 显示交易记录
        function displayTransactions(transactions) {
            let html = '<div class="table-responsive">';
            html += '<table class="table table-sm">';
            html += '<thead><tr><th>类型</th><th>数量</th><th>余额</th><th>描述</th><th>时间</th></tr></thead><tbody>';
            
            transactions.forEach(function(transaction) {
                const typeText = transaction.type === 'recharge' ? '充值' : 
                                transaction.type === 'consume' ? '消费' : '退款';
                const typeClass = transaction.type === 'recharge' ? 'success' : 
                                 transaction.type === 'consume' ? 'danger' : 'info';
                const amountDisplay = transaction.amount > 0 ? `+${transaction.amount}` : transaction.amount;
                
                html += `<tr>
                    <td><span class="badge bg-${typeClass}">${typeText}</span></td>
                    <td><strong class="text-${typeClass}">${amountDisplay}</strong></td>
                    <td>${transaction.balance_after}</td>
                    <td><small>${transaction.description}</small></td>
                    <td><small>${formatDateTime(transaction.created_at)}</small></td>
                </tr>`;
            });
            
            html += '</tbody></table></div>';
            
            $('#transactions-container').html(html);
        }
    </script>
</body>
</html>