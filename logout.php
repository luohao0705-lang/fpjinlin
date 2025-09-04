<?php
/**
 * 用户退出登录
 */
require_once 'config/config.php';

// 记录退出日志
if (isset($_SESSION['user_id'])) {
    require_once 'config/database.php';
    $operationLog = new OperationLog();
    $operationLog->log('user', $_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id'], '用户退出登录');
}

// 清除会话
session_destroy();

// 跳转到首页
header('Location: index.php');
exit;