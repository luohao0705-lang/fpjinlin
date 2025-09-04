<?php
/**
 * 复盘精灵 - 个人资料页面
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

$message = '';
$error = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nickname = trim($_POST['nickname'] ?? '');
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // 验证输入
    if (empty($nickname)) {
        $error = '昵称不能为空';
    } elseif (strlen($nickname) < 2 || strlen($nickname) > 20) {
        $error = '昵称长度应为2-20个字符';
    } else {
        $updateData = ['nickname' => $nickname];
        
        // 如果要修改密码
        if (!empty($new_password)) {
            if (empty($old_password)) {
                $error = '请输入原密码';
            } elseif (!password_verify($old_password, $user['password'])) {
                $error = '原密码错误';
            } elseif (strlen($new_password) < 6) {
                $error = '新密码长度至少6位';
            } elseif ($new_password !== $confirm_password) {
                $error = '两次输入的新密码不一致';
            } else {
                $updateData['password'] = password_hash($new_password, PASSWORD_DEFAULT);
            }
        }
        
        if (empty($error)) {
            if ($userObj->updateUser($_SESSION['user_id'], $updateData)) {
                $message = '个人资料更新成功！';
                // 重新获取用户信息
                $user = $userObj->getUserById($_SESSION['user_id']);
            } else {
                $error = '更新失败，请稍后重试';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人资料 - <?php echo APP_NAME; ?></title>
    
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
                            <li><a class="dropdown-item active" href="profile.php"><i class="fas fa-user-edit me-2"></i>个人资料</a></li>
                            <li><a class="dropdown-item" href="coin_history.php"><i class="fas fa-coins me-2"></i>精灵币记录</a></li>
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
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-user-edit me-2"></i>个人资料
                        </h4>
                    </div>
                    <div class="card-body">
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

                        <!-- 基本信息 -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="info-card">
                                    <h6 class="text-muted mb-2">注册时间</h6>
                                    <p class="fw-bold"><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-card">
                                    <h6 class="text-muted mb-2">精灵币余额</h6>
                                    <p class="fw-bold text-warning">
                                        <i class="fas fa-coins me-1"></i><?php echo $user['jingling_coins']; ?> 币
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- 编辑表单 -->
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">手机号</label>
                                        <input type="tel" class="form-control" id="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" readonly>
                                        <div class="form-text">手机号无法修改</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nickname" class="form-label">昵称 <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="nickname" name="nickname" 
                                               value="<?php echo htmlspecialchars($user['nickname']); ?>" 
                                               minlength="2" maxlength="20" required>
                                        <div class="invalid-feedback">请输入2-20个字符的昵称</div>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">
                            <h6 class="text-muted mb-3">修改密码（可选）</h6>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="old_password" class="form-label">原密码</label>
                                        <input type="password" class="form-control" id="old_password" name="old_password">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">新密码</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                                        <div class="form-text">至少6位字符</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">确认新密码</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6">
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>返回首页
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>保存修改
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 表单验证
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        
                        // 检查密码确认
                        const newPassword = document.getElementById('new_password').value;
                        const confirmPassword = document.getElementById('confirm_password').value;
                        
                        if (newPassword && newPassword !== confirmPassword) {
                            event.preventDefault();
                            event.stopPropagation();
                            document.getElementById('confirm_password').setCustomValidity('密码不一致');
                        } else {
                            document.getElementById('confirm_password').setCustomValidity('');
                        }
                        
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
        
        // 密码确认实时验证
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword && confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('密码不一致');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>