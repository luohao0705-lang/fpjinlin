<?php
/**
 * 管理员登录页面
 */
require_once '../config/config.php';
require_once '../config/database.php';

// 如果已登录，跳转到后台首页
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        try {
            $db = new Database();
            $admin = $db->fetchOne(
                "SELECT * FROM admins WHERE username = ? AND status = 1",
                [$username]
            );
            
            if ($admin && password_verify($password, $admin['password_hash'])) {
                // 登录成功
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                
                // 更新最后登录时间
                $db->query(
                    "UPDATE admins SET last_login_time = NOW(), last_login_ip = ? WHERE id = ?",
                    [$_SERVER['REMOTE_ADDR'] ?? '', $admin['id']]
                );
                
                // 记录登录日志
                $operationLog = new OperationLog();
                $operationLog->log('admin', $admin['id'], 'login', 'admin', $admin['id'], '管理员登录');
                
                header('Location: index.php');
                exit;
            } else {
                $error = '用户名或密码错误';
            }
        } catch (Exception $e) {
            $error = '登录失败：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- 自定义样式 -->
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            padding: 40px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
            <div class="col-md-5 col-lg-4">
                <div class="login-container">
                    <!-- Logo和标题 -->
                    <div class="text-center mb-4">
                        <h2 class="form-title">
                            <i class="fas fa-shield-alt text-primary me-2"></i>
                            管理后台
                        </h2>
                        <p class="text-muted"><?php echo APP_NAME; ?> 管理系统</p>
                    </div>
                    
                    <!-- 错误提示 -->
                    <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- 登录表单 -->
                    <form method="POST">
                        <div class="form-group mb-3">
                            <label for="username" class="form-label">用户名</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       placeholder="请输入管理员用户名" required 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group mb-4">
                            <label for="password" class="form-label">密码</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="请输入密码" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password')">
                                    <i class="fas fa-eye" id="password-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>登录后台
                            </button>
                        </div>
                    </form>
                    
                    <!-- 返回前台 -->
                    <div class="text-center mt-4">
                        <a href="../index.php" class="text-muted">
                            <i class="fas fa-arrow-left me-1"></i>返回前台首页
                        </a>
                    </div>
                    
                    <!-- 默认账户提示 -->
                    <div class="mt-4 p-3 bg-light rounded">
                        <h6 class="small mb-2"><i class="fas fa-info-circle me-1"></i>默认管理员账户</h6>
                        <p class="small mb-1"><strong>用户名：</strong>admin</p>
                        <p class="small mb-0"><strong>密码：</strong>admin123</p>
                        <p class="small text-danger mb-0">请登录后立即修改默认密码</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 密码显示切换
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const eye = document.getElementById(inputId + '-eye');
            
            if (input.type === 'password') {
                input.type = 'text';
                eye.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                eye.className = 'fas fa-eye';
            }
        }
        
        // 自动聚焦到用户名输入框
        document.getElementById('username').focus();
    </script>
</body>
</html>