<?php
/**
 * 后台订单列表API
 * 复盘精灵系统 - 后台管理
 */
require_once '../../config/config.php';
require_once '../../config/database.php';

// 启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

try {
    // 检查管理员登录
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('请先登录');
    }
    
    // 获取参数
    $page = max(1, intval($_GET['page'] ?? 1));
    $pageSize = min(intval($_GET['pageSize'] ?? 20), 100);
    $limit = intval($_GET['limit'] ?? 0);
    $status = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    
    $db = new Database();
    
    // 构建查询条件
    $whereConditions = ['1=1'];
    $params = [];
    
    if (!empty($status)) {
        $whereConditions[] = 'status = ?';
        $params[] = $status;
    }
    
    if (!empty($search)) {
        $whereConditions[] = '(title LIKE ? OR phone LIKE ? OR nickname LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($dateFrom)) {
        $whereConditions[] = 'DATE(created_at) >= ?';
        $params[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $whereConditions[] = 'DATE(created_at) <= ?';
        $params[] = $dateTo;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    // 如果是限制数量（首页使用）
    if ($limit > 0) {
        $orders = $db->fetchAll(
            "SELECT ao.id, ao.order_no, ao.title, ao.status, ao.cost_coins, ao.report_score, ao.report_level, 
                    ao.created_at, ao.completed_at, u.phone, u.nickname, 'text' as order_type
             FROM analysis_orders ao
             LEFT JOIN users u ON ao.user_id = u.id
             {$whereClause}
             UNION ALL
             SELECT vao.id, vao.order_no, vao.title, vao.status, vao.cost_coins, vao.report_score, vao.report_level,
                    vao.created_at, vao.completed_at, u.phone, u.nickname, 'video' as order_type
             FROM video_analysis_orders vao
             LEFT JOIN users u ON vao.user_id = u.id
             {$whereClause}
             ORDER BY created_at DESC 
             LIMIT ?",
            array_merge($params, $params, [$limit])
        );
        
        jsonResponse([
            'success' => true,
            'data' => [
                'orders' => $orders
            ]
        ]);
    } else {
        // 正常分页查询
        $offset = ($page - 1) * $pageSize;
        
        $orders = $db->fetchAll(
            "SELECT ao.id, ao.order_no, ao.title, ao.status, ao.cost_coins, ao.report_score, ao.report_level, 
                    ao.created_at, ao.completed_at, u.phone, u.nickname, 'text' as order_type
             FROM analysis_orders ao
             LEFT JOIN users u ON ao.user_id = u.id
             {$whereClause}
             UNION ALL
             SELECT vao.id, vao.order_no, vao.title, vao.status, vao.cost_coins, vao.report_score, vao.report_level,
                    vao.created_at, vao.completed_at, u.phone, u.nickname, 'video' as order_type
             FROM video_analysis_orders vao
             LEFT JOIN users u ON vao.user_id = u.id
             {$whereClause}
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?",
            array_merge($params, $params, [$pageSize, $offset])
        );
        
        $total = $db->fetchOne(
            "SELECT (SELECT COUNT(*) FROM analysis_orders ao LEFT JOIN users u ON ao.user_id = u.id {$whereClause}) + 
                    (SELECT COUNT(*) FROM video_analysis_orders vao LEFT JOIN users u ON vao.user_id = u.id {$whereClause}) as count",
            array_merge($params, $params)
        )['count'];
        
        jsonResponse([
            'success' => true,
            'data' => [
                'orders' => $orders,
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => ceil($total / $pageSize)
            ]
        ]);
    }
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
?>
