<?php
/**
 * 用户注册页面
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
    <title>用户注册 - <?php echo APP_NAME; ?></title>
    
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
                            注册账户
                        </h2>
                        <p class="text-muted">加入复盘精灵，开启智能分析之旅</p>
                    </div>
                    
                    <!-- 注册表单 -->
                    <form id="register-form">
                        <div class="form-group">
                            <label for="phone" class="form-label">手机号</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-phone"></i>
                                </span>
                                <input type="tel" class="form-control phone-input" id="phone" name="phone" 
                                       placeholder="请输入手机号" required>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="sms-code" class="form-label">短信验证码</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="sms-code" name="sms_code" 
                                       placeholder="请输入验证码" maxlength="6" required>
                                <button type="button" class="btn btn-outline-primary" id="send-sms-btn" 
                                        onclick="sendSmsCode($('#phone').val(), 'register', this)">
                                    发送验证码
                                </button>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password" class="form-label">设置密码</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control password-input" id="password" name="password" 
                                       placeholder="请设置密码（至少6位）" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password')">
                                    <i class="fas fa-eye" id="password-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength"></div>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm-password" class="form-label">确认密码</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="confirm-password" name="confirm_password" 
                                       placeholder="请再次输入密码" required>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="agree-terms" required>
                            <label class="form-check-label" for="agree-terms">
                                我已阅读并同意 <a href="terms.php" target="_blank" class="text-primary">用户协议</a> 和 
                                <a href="privacy.php" target="_blank" class="text-primary">隐私政策</a>
                            </label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" disabled>
                                <i class="fas fa-user-plus me-2"></i>立即注册
                            </button>
                        </div>
                        
                    </form>
                    
                    <!-- 登录链接 -->
                    <div class="text-center mt-4">
                        <p class="text-muted">
                            已有账户？ <a href="login.php" class="text-primary">立即登录</a>
                        </p>
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
        // 表单提交处理
        $('#register-form').on('submit', function(e) {
            e.preventDefault();
            
            // 验证密码一致性
            const password = $('#password').val();
            const confirmPassword = $('#confirm-password').val();
            
            if (password !== confirmPassword) {
                $('#confirm-password').addClass('is-invalid');
                $('#confirm-password').siblings('.invalid-feedback').text('两次输入的密码不一致');
                return;
            } else {
                $('#confirm-password').removeClass('is-invalid');
            }
            
            // 提交注册
            const formData = {
                phone: $('#phone').val().trim(),
                sms_code: $('#sms-code').val().trim(),
                password: password
            };
            
            const $submitBtn = $(this).find('button[type="submit"]');
            $submitBtn.prop('disabled', true).html('<span class="loading"></span> 注册中...');
            
            $.post('api/register.php', formData, function(response) {
                if (response.success) {
                    showAlert('success', '注册成功！正在跳转...');
                    setTimeout(function() {
                        window.location.href = 'index.php';
                    }, 1500);
                } else {
                    showAlert('danger', response.message || '注册失败');
                    $submitBtn.prop('disabled', false).html('<i class="fas fa-user-plus me-2"></i>立即注册');
                }
            }).fail(function() {
                showAlert('danger', '网络错误，请重试');
                $submitBtn.prop('disabled', false).html('<i class="fas fa-user-plus me-2"></i>立即注册');
            });
        });
        
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
        
        // 实时验证
        $('#phone').on('input', function() {
            const phone = $(this).val();
            $('#send-sms-btn').prop('disabled', !phone || !/^1[3-9]\d{9}$/.test(phone));
            updateRegisterButton();
        });
        
        // 监听所有输入变化
        $('#register-form input, #agree-terms').on('input change keyup paste', function() {
            updateRegisterButton();
        });
        
        // 页面加载完成后初始化
        setTimeout(function() {
            updateRegisterButton();
        }, 100);
        
        function updateRegisterButton() {
            const phone = $('#phone').val().trim();
            const smsCode = $('#sms-code').val().trim();
            const password = $('#password').val().trim();
            const confirmPassword = $('#confirm-password').val().trim();
            const agreeTerms = $('#agree-terms').prop('checked');
            
            // 验证各个字段并显示提醒
            const phoneValid = validateField('phone', phone, /^1[3-9]\d{9}$/.test(phone), '请输入正确的11位手机号');
            const smsCodeValid = validateField('sms-code', smsCode, smsCode.length === 6, '请输入6位验证码（可使用010705）');
            const passwordValid = validateField('password', password, password.length >= 6, '密码至少需要6位字符');
            const passwordMatch = validateConfirmPassword(password, confirmPassword);
            const termsValid = validateTerms(agreeTerms);
            
            // 所有字段都有效时启用按钮
            const allValid = phoneValid && smsCodeValid && passwordValid && passwordMatch && termsValid;
            $('#register-form button[type="submit"]').prop('disabled', !allValid);
        }
        
        // 验证单个字段
        function validateField(fieldId, value, isValid, errorMsg) {
            const $field = $('#' + fieldId);
            const $feedback = $field.siblings('.invalid-feedback');
            
            if (value && !isValid) {
                $field.addClass('is-invalid').removeClass('is-valid');
                $feedback.text(errorMsg);
                return false;
            } else if (value && isValid) {
                $field.addClass('is-valid').removeClass('is-invalid');
                $feedback.text('');
                return true;
            } else {
                $field.removeClass('is-invalid is-valid');
                $feedback.text('');
                return false;
            }
        }
        
        // 验证确认密码
        function validateConfirmPassword(password, confirmPassword) {
            const $field = $('#confirm-password');
            const $feedback = $field.siblings('.invalid-feedback');
            
            if (confirmPassword) {
                if (password === confirmPassword) {
                    $field.addClass('is-valid').removeClass('is-invalid');
                    $feedback.text('');
                    return true;
                } else {
                    $field.addClass('is-invalid').removeClass('is-valid');
                    $feedback.text('两次输入的密码不一致');
                    return false;
                }
            } else {
                $field.removeClass('is-invalid is-valid');
                $feedback.text('');
                return false;
            }
        }
        
        // 验证同意条款
        function validateTerms(agreeTerms) {
            const $checkbox = $('#agree-terms');
            if (!agreeTerms) {
                $checkbox.addClass('is-invalid');
                return false;
            } else {
                $checkbox.removeClass('is-invalid');
                return true;
            }
        }
    </script>
</body>
</html>