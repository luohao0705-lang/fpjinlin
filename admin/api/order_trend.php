<?php
/**
 * 后台订单趋势API
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
    
    $db = new Database();
    
    // 获取最近7天的订单趋势数据
    $trendData = $db->fetchAll(
        "SELECT DATE(created_at) as date, 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_orders,
                SUM(cost_coins) as total_coins
         FROM (
             SELECT created_at, status, cost_coins FROM analysis_orders
             UNION ALL
             SELECT created_at, status, cost_coins FROM video_analysis_orders
         ) as all_orders
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         GROUP BY DATE(created_at)
         ORDER BY date ASC"
    );
    
    // 填充缺失的日期
    $result = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $found = false;
        
        foreach ($trendData as $item) {
            if ($item['date'] === $date) {
                $result[] = [
                    'date' => $date,
                    'total_orders' => (int)$item['total_orders'],
                    'completed_orders' => (int)$item['completed_orders'],
                    'failed_orders' => (int)$item['failed_orders'],
                    'total_coins' => (int)$item['total_coins']
                ];
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $result[] = [
                'date' => $date,
                'total_orders' => 0,
                'completed_orders' => 0,
                'failed_orders' => 0,
                'total_coins' => 0
            ];
        }
    }
    
    jsonResponse([
        'success' => true,
        'data' => $result
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
?>
