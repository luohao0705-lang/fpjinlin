<?php
/**
 * 创建分析订单页面
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
$costCoins = getSystemConfig('analysis_cost_coins', 100);

$error = '';
$success = '';

// 处理订单创建
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_order') {
    try {
        $title = sanitizeInput($_POST['title'] ?? '');
        $ownScript = trim($_POST['own_script'] ?? '');
        $competitorScripts = [
            trim($_POST['competitor1_script'] ?? ''),
            trim($_POST['competitor2_script'] ?? ''),
            trim($_POST['competitor3_script'] ?? '')
        ];
        
        // 验证参数
        if (empty($title)) {
            throw new Exception('请输入分析标题');
        }
        
        if (empty($ownScript)) {
            throw new Exception('请输入本方话术');
        }
        
        // 检查精灵币余额
        if ($currentUser['spirit_coins'] < $costCoins) {
            throw new Exception('精灵币余额不足，请先充值');
        }
        
        // 创建订单
        $order = $orderManager->createOrder($currentUser['id'], $title, $ownScript, $competitorScripts);
        
        // 跳转到上传页面
        header("Location: upload.php?order_id=" . $order['id']);
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建分析订单 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .cost-info {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .script-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .competitor-section {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .char-counter {
            font-size: 0.875rem;
            color: #6c757d;
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
                <span class="navbar-text">
                    <i class="bi bi-coin"></i> <?php echo number_format($currentUser['spirit_coins']); ?> 精灵币
                </span>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- 返回按钮 -->
                <div class="mb-3">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> 返回首页
                    </a>
                </div>
                
                <div class="main-card">
                    <div class="card-header bg-white border-bottom">
                        <h4 class="mb-0">
                            <i class="bi bi-plus-circle"></i> 创建分析订单
                        </h4>
                    </div>
                    
                    <div class="card-body">
                        <!-- 费用说明 -->
                        <div class="cost-info text-center">
                            <h5 class="mb-2">
                                <i class="bi bi-info-circle"></i> 分析费用
                            </h5>
                            <p class="mb-1">每次分析消耗 <strong><?php echo $costCoins; ?></strong> 精灵币</p>
                            <small>您当前余额：<?php echo $currentUser['spirit_coins']; ?> 精灵币</small>
                            <?php if ($currentUser['spirit_coins'] < $costCoins): ?>
                                <div class="mt-2">
                                    <a href="recharge.php" class="btn btn-light btn-sm">
                                        <i class="bi bi-coin"></i> 立即充值
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="createOrderForm">
                            <input type="hidden" name="action" value="create_order">
                            
                            <!-- 基础信息 -->
                            <div class="mb-4">
                                <label for="title" class="form-label fw-bold">
                                    分析标题 <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       placeholder="例如：2024年1月直播带货复盘" required maxlength="100">
                                <div class="form-text">为本次分析起一个有意义的标题</div>
                            </div>
                            
                            <!-- 本方话术 -->
                            <div class="script-section">
                                <h5 class="mb-3">
                                    <i class="bi bi-mic"></i> 本方话术 <span class="text-danger">*</span>
                                </h5>
                                <div class="mb-3">
                                    <label for="own_script" class="form-label">话术内容</label>
                                    <textarea class="form-control" id="own_script" name="own_script" 
                                              rows="8" placeholder="请输入你的直播话术内容..." required></textarea>
                                    <div class="d-flex justify-content-between">
                                        <div class="form-text">详细的话术内容有助于更准确的分析</div>
                                        <div class="char-counter">
                                            <span id="ownScriptCount">0</span>/5000字
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 同行话术 -->
                            <div class="script-section">
                                <h5 class="mb-3">
                                    <i class="bi bi-people"></i> 同行话术
                                    <small class="text-muted">（可选，但建议填写以获得更好的对比分析）</small>
                                </h5>
                                
                                <div class="competitor-section">
                                    <h6 class="text-primary">同行1话术</h6>
                                    <textarea class="form-control" name="competitor1_script" 
                                              rows="4" placeholder="请输入同行1的话术内容..."></textarea>
                                    <div class="text-end">
                                        <small class="char-counter">
                                            <span class="competitor1-count">0</span>/3000字
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="competitor-section">
                                    <h6 class="text-primary">同行2话术</h6>
                                    <textarea class="form-control" name="competitor2_script" 
                                              rows="4" placeholder="请输入同行2的话术内容..."></textarea>
                                    <div class="text-end">
                                        <small class="char-counter">
                                            <span class="competitor2-count">0</span>/3000字
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="competitor-section">
                                    <h6 class="text-primary">同行3话术</h6>
                                    <textarea class="form-control" name="competitor3_script" 
                                              rows="4" placeholder="请输入同行3的话术内容..."></textarea>
                                    <div class="text-end">
                                        <small class="char-counter">
                                            <span class="competitor3-count">0</span>/3000字
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 提交按钮 -->
                            <div class="text-center">
                                <?php if ($currentUser['spirit_coins'] >= $costCoins): ?>
                                    <button type="submit" class="btn btn-gradient btn-lg">
                                        <i class="bi bi-arrow-right-circle"></i> 创建订单并继续上传文件
                                    </button>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i> 
                                        精灵币余额不足，请先 <a href="recharge.php" class="alert-link">充值</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 字符计数器
        function setupCharCounter(textareaSelector, counterSelector, maxLength) {
            const textarea = document.querySelector(textareaSelector);
            const counter = document.querySelector(counterSelector);
            
            if (textarea && counter) {
                textarea.addEventListener('input', function() {
                    const length = this.value.length;
                    counter.textContent = length;
                    
                    if (length > maxLength * 0.9) {
                        counter.style.color = '#dc3545';
                    } else if (length > maxLength * 0.7) {
                        counter.style.color = '#ffc107';
                    } else {
                        counter.style.color = '#6c757d';
                    }
                    
                    if (length > maxLength) {
                        this.value = this.value.substring(0, maxLength);
                        counter.textContent = maxLength;
                    }
                });
            }
        }
        
        // 设置字符计数器
        setupCharCounter('#own_script', '#ownScriptCount', 5000);
        setupCharCounter('textarea[name="competitor1_script"]', '.competitor1-count', 3000);
        setupCharCounter('textarea[name="competitor2_script"]', '.competitor2-count', 3000);
        setupCharCounter('textarea[name="competitor3_script"]', '.competitor3-count', 3000);
        
        // 表单验证
        document.getElementById('createOrderForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const ownScript = document.getElementById('own_script').value.trim();
            
            if (!title) {
                e.preventDefault();
                alert('请输入分析标题');
                return false;
            }
            
            if (!ownScript) {
                e.preventDefault();
                alert('请输入本方话术内容');
                return false;
            }
            
            if (ownScript.length < 50) {
                e.preventDefault();
                alert('话术内容太短，请提供更详细的内容（至少50字）');
                return false;
            }
            
            // 确认创建
            if (!confirm(`确认创建订单？将消耗 ${<?php echo $costCoins; ?>} 精灵币`)) {
                e.preventDefault();
                return false;
            }
        });
        
        // 自动保存草稿（本地存储）
        function saveDraft() {
            const formData = {
                title: document.getElementById('title').value,
                own_script: document.getElementById('own_script').value,
                competitor1_script: document.querySelector('textarea[name="competitor1_script"]').value,
                competitor2_script: document.querySelector('textarea[name="competitor2_script"]').value,
                competitor3_script: document.querySelector('textarea[name="competitor3_script"]').value,
                timestamp: new Date().getTime()
            };
            
            localStorage.setItem('fpjinlin_order_draft', JSON.stringify(formData));
        }
        
        // 加载草稿
        function loadDraft() {
            const draft = localStorage.getItem('fpjinlin_order_draft');
            if (draft) {
                const data = JSON.parse(draft);
                
                // 检查草稿是否过期（24小时）
                if (new Date().getTime() - data.timestamp < 24 * 60 * 60 * 1000) {
                    if (confirm('检测到未完成的订单草稿，是否恢复？')) {
                        document.getElementById('title').value = data.title || '';
                        document.getElementById('own_script').value = data.own_script || '';
                        document.querySelector('textarea[name="competitor1_script"]').value = data.competitor1_script || '';
                        document.querySelector('textarea[name="competitor2_script"]').value = data.competitor2_script || '';
                        document.querySelector('textarea[name="competitor3_script"]').value = data.competitor3_script || '';
                        
                        // 触发字符计数器更新
                        document.getElementById('own_script').dispatchEvent(new Event('input'));
                        document.querySelector('textarea[name="competitor1_script"]').dispatchEvent(new Event('input'));
                        document.querySelector('textarea[name="competitor2_script"]').dispatchEvent(new Event('input'));
                        document.querySelector('textarea[name="competitor3_script"]').dispatchEvent(new Event('input'));
                    }
                } else {
                    // 清除过期草稿
                    localStorage.removeItem('fpjinlin_order_draft');
                }
            }
        }
        
        // 页面加载时恢复草稿
        window.addEventListener('load', loadDraft);
        
        // 定期保存草稿
        setInterval(saveDraft, 30000); // 每30秒保存一次
        
        // 表单变化时保存草稿
        document.querySelectorAll('input, textarea').forEach(element => {
            element.addEventListener('input', function() {
                clearTimeout(this.saveTimeout);
                this.saveTimeout = setTimeout(saveDraft, 2000);
            });
        });
        
        // 提交成功后清除草稿
        document.getElementById('createOrderForm').addEventListener('submit', function() {
            localStorage.removeItem('fpjinlin_order_draft');
        });
    </script>
</body>
</html>