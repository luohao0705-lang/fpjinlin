<?php
/**
 * 用户订单列表API
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
    $status = $_GET['status'] ?? '';
    $dateFilter = $_GET['date'] ?? '';
    $titleSearch = trim($_GET['title'] ?? '');
    $limit = (int)($_GET['limit'] ?? 0); // 用于首页显示最新订单
    
    $userId = $_SESSION['user_id'];
    
    // 构建查询条件
    $whereConditions = ['user_id = ?'];
    $params = [$userId];
    
    if (!empty($status)) {
        $whereConditions[] = 'status = ?';
        $params[] = $status;
    }
    
    if (!empty($dateFilter)) {
        switch ($dateFilter) {
            case 'today':
                $whereConditions[] = 'DATE(created_at) = CURDATE()';
                break;
            case 'week':
                $whereConditions[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $whereConditions[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                break;
        }
    }
    
    if (!empty($titleSearch)) {
        $whereConditions[] = 'title LIKE ?';
        $params[] = '%' . $titleSearch . '%';
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    // 如果是限制数量（首页使用）
    if ($limit > 0) {
        $orders = (new Database())->fetchAll(
            "SELECT id, order_no, title, status, cost_coins, report_score, report_level, created_at, completed_at 
             FROM analysis_orders 
             {$whereClause}
             ORDER BY created_at DESC 
             LIMIT ?",
            array_merge($params, [$limit])
        );
        
        jsonResponse([
            'success' => true,
            'orders' => $orders
        ]);
    } else {
        // 正常分页查询
        $analysisOrder = new AnalysisOrder();
        $result = $analysisOrder->getUserOrders($userId, $page, $pageSize);
        
        // 应用额外的筛选条件
        if (!empty($status) || !empty($dateFilter) || !empty($titleSearch)) {
            $db = new Database();
            $offset = ($page - 1) * $pageSize;
            
            $orders = $db->fetchAll(
                "SELECT id, order_no, title, status, cost_coins, report_score, report_level, created_at, completed_at 
                 FROM analysis_orders 
                 {$whereClause}
                 ORDER BY created_at DESC 
                 LIMIT ? OFFSET ?",
                array_merge($params, [$pageSize, $offset])
            );
            
            $total = $db->fetchOne(
                "SELECT COUNT(*) as count FROM analysis_orders {$whereClause}",
                $params
            )['count'];
            
            $result = [
                'orders' => $orders,
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => ceil($total / $pageSize)
            ];
        }
        
        jsonResponse([
            'success' => true,
            'data' => $result
        ]);
    }
    
} catch (Exception $e) {
    // 对于业务逻辑错误，返回200状态码但success为false
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 200);
}