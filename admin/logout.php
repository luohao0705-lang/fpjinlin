<?php
/**
 * 管理员退出登录
 */
require_once '../config/config.php';

// 记录退出日志
if (isset($_SESSION['admin_id'])) {
    require_once '../config/database.php';
    $operationLog = new OperationLog();
    $operationLog->log('admin', $_SESSION['admin_id'], 'logout', 'admin', $_SESSION['admin_id'], '管理员退出登录');
}

// 清除会话
session_destroy();

// 跳转到登录页
header('Location: login.php');
exit;