<?php
/**
 * 兑换码列表API
 */
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 检查管理员登录
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('请先登录');
    }
    
    // 获取参数
    $page = (int)($_GET['page'] ?? 1);
    $pageSize = min((int)($_GET['pageSize'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE);
    
    $filters = [];
    if (!empty($_GET['batch_no'])) {
        $filters['batch_no'] = $_GET['batch_no'];
    }
    if (isset($_GET['is_used']) && $_GET['is_used'] !== '') {
        $filters['is_used'] = (int)$_GET['is_used'];
    }
    if (!empty($_GET['value'])) {
        $filters['value'] = (int)$_GET['value'];
    }
    
    // 如果有兑换码搜索
    if (!empty($_GET['code'])) {
        $filters['code'] = $_GET['code'];
    }
    
    // 获取兑换码列表
    $exchangeCode = new ExchangeCode();
    
    if (!empty($filters['code'])) {
        // 特殊处理兑换码搜索
        $db = new Database();
        $codes = $db->fetchAll(
            "SELECT ec.*, u.phone as used_by_phone, a.username as created_by_name 
             FROM exchange_codes ec 
             LEFT JOIN users u ON ec.used_by = u.id 
             LEFT JOIN admins a ON ec.created_by = a.id 
             WHERE ec.code LIKE ?
             ORDER BY ec.created_at DESC 
             LIMIT ? OFFSET ?",
            ['%' . $filters['code'] . '%', $pageSize, ($page - 1) * $pageSize]
        );
        
        $total = $db->fetchOne(
            "SELECT COUNT(*) as count FROM exchange_codes WHERE code LIKE ?",
            ['%' . $filters['code'] . '%']
        )['count'];
        
        $result = [
            'codes' => $codes,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => ceil($total / $pageSize)
        ];
    } else {
        $result = $exchangeCode->getCodes($page, $pageSize, $filters);
    }
    
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