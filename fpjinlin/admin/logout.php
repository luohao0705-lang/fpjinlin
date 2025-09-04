<?php
/**
 * 管理员登出页面
 * 复盘精灵系统
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Admin.php';

$admin = new Admin();
$admin->logout();

// 跳转到管理员登录页
header('Location: login.php?message=logout_success');
exit;
?>