<?php
/**
 * 用户登出页面
 * 复盘精灵系统
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/User.php';

$user = new User();
$user->logout();

// 跳转到登录页
header('Location: login.php?message=logout_success');
exit;
?>