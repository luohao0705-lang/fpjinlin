<?php
/**
 * 管理员退出登录
 */
require_once '../config/config.php';

// 记录退出日志
if (SessionManager::isLoggedIn('admin')) {
    require_once '../config/database.php';
    $operationLog = new OperationLog();
    $operationLog->log('admin', SessionManager::getUserId('admin'), 'logout', 'admin', SessionManager::getUserId('admin'), '管理员退出登录');
}

// 安全退出
SessionManager::logout();

// 跳转到登录页
header('Location: login.php');
exit;