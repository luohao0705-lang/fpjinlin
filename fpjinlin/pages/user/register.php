<?php
/**
 * 用户注册页面
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

// 处理注册请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $smsCode = $_POST['sms_code'] ?? '';
        $nickname = sanitizeInput($_POST['nickname'] ?? '');
        
        // 验证参数
        if (empty($phone) || empty($password) || empty($smsCode)) {
            throw new Exception('请填写完整信息');
        }
        
        if ($password !== $confirmPassword) {
            throw new Exception('两次输入的密码不一致');
        }
        
        // 注册用户
        $newUser = $user->register($phone, $password, $smsCode, $nickname);
        
        $success = '注册成功！正在跳转到登录页面...';
        
        // 自动登录
        $user->login($phone, $password);
        header('refresh:2;url=index.php');
        
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
    <title>用户注册 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .register-body {
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
        .password-strength {
            height: 5px;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 5px;
        }
        .password-strength-bar {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="register-card">
                    <div class="register-header">
                        <h2><i class="bi bi-magic"></i> 注册账号</h2>
                        <p class="mb-0">加入<?php echo SITE_NAME; ?>，开启智能复盘之旅</p>
                    </div>
                    <div class="register-body">
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
                        
                        <form method="POST" id="registerForm">
                            <div class="mb-3">
                                <label for="phone" class="form-label">手机号 <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           placeholder="请输入手机号" required maxlength="11">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="sms_code" class="form-label">短信验证码 <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                                    <input type="text" class="form-control" id="sms_code" name="sms_code" 
                                           placeholder="请输入验证码" required maxlength="6">
                                    <button type="button" class="btn btn-outline-secondary" id="sendSmsBtn">
                                        获取验证码
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="nickname" class="form-label">昵称</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="nickname" name="nickname" 
                                           placeholder="请输入昵称（可选）" maxlength="20">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">密码 <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="请输入密码（至少6位）" required minlength="6">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength">
                                    <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                </div>
                                <small class="text-muted">密码强度：<span id="passwordStrengthText">请输入密码</span></small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">确认密码 <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="请再次输入密码" required minlength="6">
                                </div>
                                <div class="invalid-feedback" id="passwordMismatch"></div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="agreeTerms" required>
                                <label class="form-check-label" for="agreeTerms">
                                    我已阅读并同意 <a href="#" class="text-decoration-none">《用户协议》</a> 和 <a href="#" class="text-decoration-none">《隐私政策》</a>
                                </label>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-person-plus"></i> 立即注册
                                </button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-0">已有账号？</p>
                            <a href="login.php" class="btn btn-link">立即登录</a>
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
        
        // 密码强度检测
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrengthBar');
            const strengthText = document.getElementById('passwordStrengthText');
            
            let strength = 0;
            let strengthLabel = '弱';
            let strengthColor = '#dc3545';
            
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            if (strength >= 4) {
                strengthLabel = '强';
                strengthColor = '#28a745';
            } else if (strength >= 2) {
                strengthLabel = '中';
                strengthColor = '#ffc107';
            }
            
            strengthBar.style.width = (strength * 16.67) + '%';
            strengthBar.style.backgroundColor = strengthColor;
            strengthText.textContent = strengthLabel;
        });
        
        // 确认密码验证
        function validatePasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const mismatchDiv = document.getElementById('passwordMismatch');
            const confirmInput = document.getElementById('confirm_password');
            
            if (confirmPassword && password !== confirmPassword) {
                confirmInput.classList.add('is-invalid');
                mismatchDiv.textContent = '两次输入的密码不一致';
                return false;
            } else {
                confirmInput.classList.remove('is-invalid');
                mismatchDiv.textContent = '';
                return true;
            }
        }
        
        document.getElementById('confirm_password').addEventListener('input', validatePasswordMatch);
        document.getElementById('password').addEventListener('input', validatePasswordMatch);
        
        // 发送短信验证码
        let smsCountdown = 0;
        document.getElementById('sendSmsBtn').addEventListener('click', function() {
            const phone = document.getElementById('phone').value;
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
                    type: 'register'
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
        
        // 表单验证
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            if (!validatePasswordMatch()) {
                e.preventDefault();
                return false;
            }
            
            const phone = document.getElementById('phone').value;
            if (!phone || !/^1[3-9]\d{9}$/.test(phone)) {
                e.preventDefault();
                alert('请输入正确的手机号');
                return false;
            }
            
            const password = document.getElementById('password').value;
            if (password.length < 6) {
                e.preventDefault();
                alert('密码长度至少6位');
                return false;
            }
            
            const smsCode = document.getElementById('sms_code').value;
            if (!smsCode || smsCode.length !== 6) {
                e.preventDefault();
                alert('请输入6位验证码');
                return false;
            }
        });
        
        // 手机号输入限制
        document.getElementById('phone').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 11);
        });
        
        // 验证码输入限制
        document.getElementById('sms_code').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 6);
        });
    </script>
</body>
</html>