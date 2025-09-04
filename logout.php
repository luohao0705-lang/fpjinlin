<?php
/**
 * 用户退出登录
 */
require_once 'config/config.php';

// 记录退出日志
if (SessionManager::isLoggedIn('user')) {
    require_once 'config/database.php';
    $operationLog = new OperationLog();
    $operationLog->log('user', SessionManager::getUserId('user'), 'logout', 'user', SessionManager::getUserId('user'), '用户退出登录');
}

// 安全退出
SessionManager::logout();

// 跳转到首页
header('Location: index.php');
exit;