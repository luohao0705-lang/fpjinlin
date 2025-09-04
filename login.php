<?php
/**
 * 用户登录页面
 */
require_once 'config/config.php';
require_once 'config/database.php';

// 如果已登录，跳转到首页
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户登录 - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- 自定义样式 -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center" style="min-height: 100vh; align-items: center;">
            <div class="col-md-6 col-lg-5">
                <div class="form-container">
                    <!-- Logo和标题 -->
                    <div class="text-center mb-4">
                        <h2 class="form-title">
                            <i class="fas fa-magic text-primary me-2"></i>
                            用户登录
                        </h2>
                        <p class="text-muted">欢迎回到复盘精灵</p>
                    </div>
                    
                    <!-- 登录方式切换 -->
                    <ul class="nav nav-pills nav-fill mb-4" id="login-tabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="pill" href="#password-login">密码登录</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="pill" href="#sms-login">短信登录</a>
                        </li>
                    </ul>
                    
                    <div class="tab-content">
                        <!-- 密码登录 -->
                        <div class="tab-pane fade show active" id="password-login">
                            <form id="password-login-form">
                                <div class="form-group">
                                    <label for="phone-pwd" class="form-label">手机号</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-phone"></i>
                                        </span>
                                        <input type="tel" class="form-control phone-input" id="phone-pwd" name="phone" 
                                               placeholder="请输入手机号" required>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password-pwd" class="form-label">密码</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="password-pwd" name="password" 
                                               placeholder="请输入密码" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password-pwd')">
                                            <i class="fas fa-eye" id="password-pwd-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i>立即登录
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- 短信登录 -->
                        <div class="tab-pane fade" id="sms-login">
                            <form id="sms-login-form">
                                <div class="form-group">
                                    <label for="phone-sms" class="form-label">手机号</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-phone"></i>
                                        </span>
                                        <input type="tel" class="form-control phone-input" id="phone-sms" name="phone" 
                                               placeholder="请输入手机号" required>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="sms-code-login" class="form-label">短信验证码</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="sms-code-login" name="sms_code" 
                                               placeholder="请输入验证码" maxlength="6" required>
                                        <button type="button" class="btn btn-outline-primary" id="send-sms-login-btn" 
                                                onclick="sendSmsCode($('#phone-sms').val(), 'login', this)">
                                            发送验证码
                                        </button>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i>立即登录
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- 注册链接 -->
                    <div class="text-center mt-4">
                        <p class="text-muted">
                            还没有账户？ <a href="register.php" class="text-primary">立即注册</a>
                        </p>
                    </div>
                    
                    <!-- 返回首页 -->
                    <div class="text-center mt-3">
                        <a href="index.php" class="text-muted">
                            <i class="fas fa-arrow-left me-1"></i>返回首页
                        </a>
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
        // 密码登录表单提交
        $('#password-login-form').on('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                phone: $('#phone-pwd').val().trim(),
                password: $('#password-pwd').val(),
                login_type: 'password'
            };
            
            submitLogin(formData, $(this));
        });
        
        // 短信登录表单提交
        $('#sms-login-form').on('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                phone: $('#phone-sms').val().trim(),
                sms_code: $('#sms-code-login').val().trim(),
                login_type: 'sms'
            };
            
            submitLogin(formData, $(this));
        });
        
        // 提交登录请求
        function submitLogin(formData, form) {
            const $submitBtn = form.find('button[type="submit"]');
            $submitBtn.prop('disabled', true).html('<span class="loading"></span> 登录中...');
            
            $.post('api/login.php', formData, function(response) {
                if (response.success) {
                    showAlert('success', '登录成功！正在跳转...');
                    setTimeout(function() {
                        window.location.href = 'index.php';
                    }, 1500);
                } else {
                    showAlert('danger', response.message || '登录失败');
                    $submitBtn.prop('disabled', false).html('<i class="fas fa-sign-in-alt me-2"></i>立即登录');
                }
            }).fail(function() {
                showAlert('danger', '网络错误，请重试');
                $submitBtn.prop('disabled', false).html('<i class="fas fa-sign-in-alt me-2"></i>立即登录');
            });
        }
        
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
        
        // 监听手机号输入，控制发送按钮状态
        $('#phone-sms').on('input', function() {
            const phone = $(this).val();
            $('#send-sms-login-btn').prop('disabled', !phone || !/^1[3-9]\d{9}$/.test(phone));
        });
        
        // 获取URL参数中的手机号（从注册页跳转过来）
        const urlParams = new URLSearchParams(window.location.search);
        const phoneFromUrl = urlParams.get('phone');
        if (phoneFromUrl) {
            $('#phone-pwd').val(phoneFromUrl);
            $('#phone-sms').val(phoneFromUrl);
        }
    </script>
</body>
</html>