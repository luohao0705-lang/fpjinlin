<?php
/**
 * 复盘精灵系统入口文件
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/User.php';

$user = new User();

// 检查系统维护状态
if (SYSTEM_MAINTENANCE) {
    include __DIR__ . '/pages/maintenance.php';
    exit;
}

// 根据用户状态重定向
if ($user->isLoggedIn()) {
    // 已登录用户，跳转到用户首页
    header('Location: /pages/user/index.php');
} else {
    // 未登录用户，跳转到登录页
    header('Location: /pages/user/login.php');
}

exit;
?>