<?php
/**
 * 操作日志类
 * 复盘精灵系统
 */

class OperationLog {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * 记录操作日志
     */
    public function log($operatorType, $operatorId, $action, $targetType = null, $targetId = null, $description = '', $extraData = null) {
        $ipAddress = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $this->db->insert(
            "INSERT INTO operation_logs (operator_type, operator_id, action, target_type, target_id, description, ip_address, user_agent, extra_data) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $operatorType,
                $operatorId,
                $action,
                $targetType,
                $targetId,
                $description,
                $ipAddress,
                $userAgent,
                $extraData ? json_encode($extraData, JSON_UNESCAPED_UNICODE) : null
            ]
        );
    }
    
    /**
     * 获取操作日志
     */
    public function getLogs($page = 1, $pageSize = 20, $filters = []) {
        $offset = ($page - 1) * $pageSize;
        $whereClause = '';
        $params = [];
        
        // 构建查询条件
        $conditions = [];
        if (!empty($filters['operator_type'])) {
            $conditions[] = 'ol.operator_type = ?';
            $params[] = $filters['operator_type'];
        }
        if (!empty($filters['action'])) {
            $conditions[] = 'ol.action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = 'DATE(ol.created_at) >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'DATE(ol.created_at) <= ?';
            $params[] = $filters['date_to'];
        }
        
        if (!empty($conditions)) {
            $whereClause = ' WHERE ' . implode(' AND ', $conditions);
        }
        
        $params = array_merge($params, [$pageSize, $offset]);
        
        $logs = $this->db->fetchAll(
            "SELECT ol.*, 
                    CASE 
                        WHEN ol.operator_type = 'user' THEN u.phone 
                        WHEN ol.operator_type = 'admin' THEN a.username 
                    END as operator_name
             FROM operation_logs ol 
             LEFT JOIN users u ON ol.operator_type = 'user' AND ol.operator_id = u.id 
             LEFT JOIN admins a ON ol.operator_type = 'admin' AND ol.operator_id = a.id 
             {$whereClause}
             ORDER BY ol.created_at DESC 
             LIMIT ? OFFSET ?",
            $params
        );
        
        $countParams = array_slice($params, 0, -2);
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM operation_logs ol {$whereClause}",
            $countParams
        )['count'];
        
        return [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => ceil($total / $pageSize)
        ];
    }
    
    /**
     * 获取用户操作日志
     */
    public function getUserLogs($userId, $page = 1, $pageSize = 20) {
        $offset = ($page - 1) * $pageSize;
        
        $logs = $this->db->fetchAll(
            "SELECT * FROM operation_logs 
             WHERE operator_type = 'user' AND operator_id = ? 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?",
            [$userId, $pageSize, $offset]
        );
        
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM operation_logs WHERE operator_type = 'user' AND operator_id = ?",
            [$userId]
        )['count'];
        
        return [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => ceil($total / $pageSize)
        ];
    }
    
    /**
     * 清理旧日志
     */
    public function cleanOldLogs($days = 90) {
        $this->db->query(
            "DELETE FROM operation_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
    }
    
    /**
     * 获取客户端IP
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    /**
     * 获取操作统计
     */
    public function getStatistics($days = 7) {
        $stats = [];
        
        // 总操作数
        $stats['total_operations'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM operation_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        )['count'];
        
        // 用户操作数
        $stats['user_operations'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM operation_logs WHERE operator_type = 'user' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        )['count'];
        
        // 管理员操作数
        $stats['admin_operations'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM operation_logs WHERE operator_type = 'admin' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        )['count'];
        
        // 热门操作
        $stats['popular_actions'] = $this->db->fetchAll(
            "SELECT action, COUNT(*) as count 
             FROM operation_logs 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY action 
             ORDER BY count DESC 
             LIMIT 10",
            [$days]
        );
        
        return $stats;
    }
}