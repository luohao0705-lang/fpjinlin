<?php
/**
 * 精灵币交易记录API
 */
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 检查用户登录
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('请先登录');
    }
    
    // 获取参数
    $page = (int)($_GET['page'] ?? 1);
    $pageSize = min((int)($_GET['pageSize'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE);
    $userId = $_SESSION['user_id'];
    
    // 获取交易记录
    $user = new User();
    $result = $user->getCoinTransactions($userId, $page, $pageSize);
    
    jsonResponse([
        'success' => true,
        'data' => $result
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}