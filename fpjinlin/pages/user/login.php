<?php
/**
 * 用户登录页面
 * 复盘精灵系统
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/User.php';

$user = new User();

// 如果已登录，跳转到首页
if ($user->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $loginType = $_POST['login_type'] ?? 'password';
        
        if ($loginType === 'password') {
            $password = $_POST['password'] ?? '';
            $user->login($phone, $password);
        } else {
            $smsCode = $_POST['sms_code'] ?? '';
            $user->login($phone, null, $smsCode);
        }
        
        header('Location: index.php');
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
    <title>用户登录 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-card">
                    <div class="login-header">
                        <h2><i class="bi bi-magic"></i> <?php echo SITE_NAME; ?></h2>
                        <p class="mb-0">视频号直播复盘分析平台</p>
                    </div>
                    <div class="login-body">
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
                        
                        <!-- 登录方式切换 -->
                        <ul class="nav nav-pills nav-justified mb-4" id="loginTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="password-tab" data-bs-toggle="pill" data-bs-target="#password-login" type="button" role="tab">
                                    <i class="bi bi-key"></i> 密码登录
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="sms-tab" data-bs-toggle="pill" data-bs-target="#sms-login" type="button" role="tab">
                                    <i class="bi bi-phone"></i> 短信登录
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="loginTabsContent">
                            <!-- 密码登录 -->
                            <div class="tab-pane fade show active" id="password-login" role="tabpanel">
                                <form method="POST" id="passwordForm">
                                    <input type="hidden" name="login_type" value="password">
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">手机号</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   placeholder="请输入手机号" required maxlength="11">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">密码</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   placeholder="请输入密码" required>
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="bi bi-box-arrow-in-right"></i> 登录
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- 短信登录 -->
                            <div class="tab-pane fade" id="sms-login" role="tabpanel">
                                <form method="POST" id="smsForm">
                                    <input type="hidden" name="login_type" value="sms">
                                    
                                    <div class="mb-3">
                                        <label for="sms_phone" class="form-label">手机号</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                            <input type="tel" class="form-control" id="sms_phone" name="phone" 
                                                   placeholder="请输入手机号" required maxlength="11">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="sms_code" class="form-label">验证码</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                                            <input type="text" class="form-control" id="sms_code" name="sms_code" 
                                                   placeholder="请输入验证码" required maxlength="6">
                                            <button type="button" class="btn btn-outline-secondary" id="sendSmsBtn">
                                                获取验证码
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="bi bi-box-arrow-in-right"></i> 登录
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-0">还没有账号？</p>
                            <a href="register.php" class="btn btn-link">立即注册</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 密码显示/隐藏
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                password.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });
        
        // 发送短信验证码
        let smsCountdown = 0;
        document.getElementById('sendSmsBtn').addEventListener('click', function() {
            const phone = document.getElementById('sms_phone').value;
            const btn = this;
            
            if (!phone || !/^1[3-9]\d{9}$/.test(phone)) {
                alert('请输入正确的手机号');
                return;
            }
            
            if (smsCountdown > 0) {
                return;
            }
            
            btn.disabled = true;
            btn.textContent = '发送中...';
            
            fetch('/api/send_sms.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    phone: phone,
                    type: 'login'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.code === 200) {
                    // 开始倒计时
                    smsCountdown = 60;
                    const countdown = setInterval(() => {
                        btn.textContent = `${smsCountdown}秒后重发`;
                        smsCountdown--;
                        
                        if (smsCountdown < 0) {
                            clearInterval(countdown);
                            btn.disabled = false;
                            btn.textContent = '获取验证码';
                        }
                    }, 1000);
                    
                    alert('验证码已发送，请注意查收');
                } else {
                    btn.disabled = false;
                    btn.textContent = '获取验证码';
                    alert(data.message || '发送失败，请重试');
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.textContent = '获取验证码';
                alert('网络错误，请重试');
            });
        });
        
        // 手机号输入限制
        document.querySelectorAll('input[type="tel"]').forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').substring(0, 11);
            });
        });
        
        // 验证码输入限制
        document.getElementById('sms_code').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 6);
        });
    </script>
</body>
</html>