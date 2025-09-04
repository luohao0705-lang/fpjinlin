<?php
/**
 * 充值页面
 * 复盘精灵系统
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/User.php';
require_once __DIR__ . '/../../includes/ExchangeCode.php';

$userManager = new User();
$exchangeCodeManager = new ExchangeCode();

// 检查登录状态
$userManager->requireLogin();

$currentUser = $userManager->getCurrentUser();

$error = '';
$success = '';

// 处理兑换码使用
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'use_code') {
    try {
        $exchangeCode = strtoupper(sanitizeInput($_POST['exchange_code'] ?? ''));
        
        if (empty($exchangeCode)) {
            throw new Exception('请输入兑换码');
        }
        
        $result = $exchangeCodeManager->useCode($exchangeCode, $currentUser['id']);
        
        $success = "兑换成功！获得 {$result['coins_added']} 精灵币，当前余额：{$result['new_balance']} 精灵币";
        
        // 更新当前用户信息
        $currentUser = $userManager->getCurrentUser();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 获取最近的交易记录
$transactions = $userManager->getUserTransactions($currentUser['id'], 1, 10);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>充值中心 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: var(--primary-gradient);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .main-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
        }
        
        .balance-card {
            background: var(--success-gradient);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .recharge-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .btn-gradient {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 500;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .transaction-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .transaction-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .transaction-amount.positive {
            color: #28a745;
        }
        
        .transaction-amount.negative {
            color: #dc3545;
        }
        
        .code-input {
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            letter-spacing: 2px;
            text-transform: uppercase;
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
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="bi bi-house"></i> 返回首页
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- 余额显示 -->
                <div class="balance-card">
                    <h2 class="mb-2">
                        <i class="bi bi-coin"></i> <?php echo number_format($currentUser['spirit_coins']); ?>
                    </h2>
                    <h5 class="mb-3">当前精灵币余额</h5>
                    <p class="mb-0">
                        <i class="bi bi-info-circle"></i> 
                        每次AI分析消耗 <?php echo getSystemConfig('analysis_cost_coins', 100); ?> 精灵币
                    </p>
                </div>
                
                <div class="main-card">
                    <div class="card-header bg-white border-bottom">
                        <h4 class="mb-0">
                            <i class="bi bi-credit-card"></i> 充值中心
                        </h4>
                    </div>
                    
                    <div class="card-body">
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
                        
                        <!-- 兑换码充值 -->
                        <div class="recharge-section">
                            <h5 class="mb-3">
                                <i class="bi bi-ticket-perforated"></i> 兑换码充值
                            </h5>
                            <p class="text-muted mb-3">请联系客服获取兑换码，然后在此处兑换精灵币</p>
                            
                            <form method="POST" id="exchangeForm">
                                <input type="hidden" name="action" value="use_code">
                                
                                <div class="row align-items-end">
                                    <div class="col-md-8 mb-3">
                                        <label for="exchange_code" class="form-label">兑换码</label>
                                        <input type="text" class="form-control code-input" id="exchange_code" 
                                               name="exchange_code" placeholder="请输入兑换码" 
                                               maxlength="20" required>
                                        <div class="form-text">兑换码不区分大小写</div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <button type="submit" class="btn btn-gradient w-100">
                                            <i class="bi bi-arrow-right-circle"></i> 立即兑换
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- 充值说明 -->
                        <div class="recharge-section">
                            <h5 class="mb-3">
                                <i class="bi bi-question-circle"></i> 充值说明
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary">如何获取兑换码？</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="bi bi-check text-success"></i> 联系客服微信购买</li>
                                        <li><i class="bi bi-check text-success"></i> 参与平台活动获得</li>
                                        <li><i class="bi bi-check text-success"></i> 邀请好友奖励</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-primary">精灵币用途</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="bi bi-check text-success"></i> AI直播复盘分析</li>
                                        <li><i class="bi bi-check text-success"></i> 生成专业分析报告</li>
                                        <li><i class="bi bi-check text-success"></i> 同行对比分析</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 联系客服 -->
                        <div class="text-center">
                            <h6 class="text-muted mb-3">需要帮助？</h6>
                            <button class="btn btn-outline-primary" onclick="alert('客服微信：fpjinling2024')">
                                <i class="bi bi-wechat"></i> 联系客服
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- 交易记录 -->
                <?php if (!empty($transactions['transactions'])): ?>
                    <div class="main-card mt-4">
                        <div class="card-header bg-white border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-clock-history"></i> 最近交易记录
                                </h5>
                                <a href="transactions.php" class="btn btn-sm btn-outline-primary">
                                    查看全部
                                </a>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <?php foreach (array_slice($transactions['transactions'], 0, 5) as $transaction): ?>
                                <div class="transaction-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php 
                                                $typeText = [
                                                    'recharge' => '充值',
                                                    'consume' => '消费',
                                                    'refund' => '退款'
                                                ];
                                                echo $typeText[$transaction['transaction_type']] ?? $transaction['transaction_type'];
                                                ?>
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                <?php echo htmlspecialchars($transaction['description']); ?>
                                            </p>
                                            <small class="text-muted">
                                                <?php echo date('Y-m-d H:i:s', strtotime($transaction['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <h6 class="mb-1 transaction-amount <?php echo $transaction['amount'] > 0 ? 'positive' : 'negative'; ?>">
                                                <?php echo $transaction['amount'] > 0 ? '+' : ''; ?><?php echo $transaction['amount']; ?>
                                            </h6>
                                            <small class="text-muted">
                                                余额：<?php echo $transaction['balance_after']; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 兑换码输入格式化
        document.getElementById('exchange_code').addEventListener('input', function() {
            // 转换为大写，只保留字母和数字
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            
            // 限制长度
            if (this.value.length > 20) {
                this.value = this.value.substring(0, 20);
            }
        });
        
        // 表单验证
        document.getElementById('exchangeForm').addEventListener('submit', function(e) {
            const code = document.getElementById('exchange_code').value.trim();
            
            if (!code) {
                e.preventDefault();
                alert('请输入兑换码');
                return false;
            }
            
            if (code.length < 8) {
                e.preventDefault();
                alert('兑换码格式不正确');
                return false;
            }
            
            // 确认兑换
            if (!confirm('确认使用此兑换码？')) {
                e.preventDefault();
                return false;
            }
        });
        
        // 复制功能（如果需要）
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('已复制到剪贴板');
            }).catch(function() {
                // 降级方案
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('已复制到剪贴板');
            });
        }
    </script>
</body>
</html>