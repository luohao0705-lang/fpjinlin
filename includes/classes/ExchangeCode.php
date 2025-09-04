<?php
/**
 * 兑换码类
 * 复盘精灵系统
 */

class ExchangeCode {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * 生成兑换码
     */
    public function generateCodes($count, $value, $expiresAt = null, $createdBy = null) {
        $batchNo = 'BATCH_' . date('YmdHis') . '_' . rand(1000, 9999);
        $codes = [];
        
        $this->db->beginTransaction();
        
        try {
            for ($i = 0; $i < $count; $i++) {
                $code = $this->generateUniqueCode();
                
                $this->db->insert(
                    "INSERT INTO exchange_codes (code, value, batch_no, expires_at, created_by) VALUES (?, ?, ?, ?, ?)",
                    [$code, $value, $batchNo, $expiresAt, $createdBy]
                );
                
                $codes[] = $code;
            }
            
            $this->db->commit();
            
            return [
                'batchNo' => $batchNo,
                'codes' => $codes,
                'count' => $count,
                'value' => $value
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 生成唯一兑换码
     */
    private function generateUniqueCode() {
        $attempts = 0;
        $maxAttempts = 10;
        
        do {
            $code = $this->generateCodeString();
            $exists = $this->db->fetchOne(
                "SELECT id FROM exchange_codes WHERE code = ?",
                [$code]
            );
            $attempts++;
        } while ($exists && $attempts < $maxAttempts);
        
        if ($exists) {
            throw new Exception('生成唯一兑换码失败，请重试');
        }
        
        return $code;
    }
    
    /**
     * 生成兑换码字符串
     */
    private function generateCodeString() {
        // 生成12位兑换码：4位字母 + 8位数字
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        
        $code = '';
        
        // 4位字母
        for ($i = 0; $i < 4; $i++) {
            $code .= $letters[rand(0, strlen($letters) - 1)];
        }
        
        // 8位数字
        for ($i = 0; $i < 8; $i++) {
            $code .= $numbers[rand(0, strlen($numbers) - 1)];
        }
        
        return $code;
    }
    
    /**
     * 使用兑换码
     */
    public function useExchangeCode($code, $userId) {
        $this->db->beginTransaction();
        
        try {
            // 查找兑换码
            $exchangeCode = $this->db->fetchOne(
                "SELECT * FROM exchange_codes WHERE code = ? AND is_used = 0",
                [$code]
            );
            
            if (!$exchangeCode) {
                throw new Exception('兑换码不存在或已使用');
            }
            
            // 检查是否过期
            if ($exchangeCode['expires_at'] && strtotime($exchangeCode['expires_at']) < time()) {
                throw new Exception('兑换码已过期');
            }
            
            // 标记兑换码已使用
            $this->db->query(
                "UPDATE exchange_codes SET is_used = 1, used_by = ?, used_at = NOW() WHERE id = ?",
                [$userId, $exchangeCode['id']]
            );
            
            // 为用户充值精灵币
            $user = new User();
            $user->rechargeCoins(
                $userId, 
                $exchangeCode['value'], 
                $exchangeCode['id'], 
                "兑换码充值：{$code}"
            );
            
            $this->db->commit();
            
            return [
                'value' => $exchangeCode['value'],
                'message' => "成功兑换 {$exchangeCode['value']} 个精灵币"
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 获取兑换码列表
     */
    public function getCodes($page = 1, $pageSize = 20, $filters = []) {
        $offset = ($page - 1) * $pageSize;
        $whereClause = '';
        $params = [];
        
        // 构建查询条件
        $conditions = [];
        if (!empty($filters['batch_no'])) {
            $conditions[] = 'batch_no = ?';
            $params[] = $filters['batch_no'];
        }
        if (isset($filters['is_used'])) {
            $conditions[] = 'is_used = ?';
            $params[] = $filters['is_used'];
        }
        if (!empty($filters['value'])) {
            $conditions[] = 'value = ?';
            $params[] = $filters['value'];
        }
        
        if (!empty($conditions)) {
            $whereClause = ' WHERE ' . implode(' AND ', $conditions);
        }
        
        $params = array_merge($params, [$pageSize, $offset]);
        
        $codes = $this->db->fetchAll(
            "SELECT ec.*, u.phone as used_by_phone, a.username as created_by_name 
             FROM exchange_codes ec 
             LEFT JOIN users u ON ec.used_by = u.id 
             LEFT JOIN admins a ON ec.created_by = a.id 
             {$whereClause}
             ORDER BY ec.created_at DESC 
             LIMIT ? OFFSET ?",
            $params
        );
        
        $countParams = array_slice($params, 0, -2);
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM exchange_codes ec {$whereClause}",
            $countParams
        )['count'];
        
        return [
            'codes' => $codes,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => ceil($total / $pageSize)
        ];
    }
    
    /**
     * 获取批次列表
     */
    public function getBatches() {
        return $this->db->fetchAll(
            "SELECT batch_no, 
                    COUNT(*) as total_count,
                    SUM(CASE WHEN is_used = 1 THEN 1 ELSE 0 END) as used_count,
                    MIN(value) as min_value,
                    MAX(value) as max_value,
                    MIN(created_at) as created_at
             FROM exchange_codes 
             WHERE batch_no IS NOT NULL
             GROUP BY batch_no 
             ORDER BY created_at DESC"
        );
    }
    
    /**
     * 删除兑换码
     */
    public function deleteCodes($codeIds) {
        if (empty($codeIds)) {
            return false;
        }
        
        $placeholders = str_repeat('?,', count($codeIds) - 1) . '?';
        
        // 只能删除未使用的兑换码
        $this->db->query(
            "DELETE FROM exchange_codes WHERE id IN ({$placeholders}) AND is_used = 0",
            $codeIds
        );
        
        return true;
    }
    
    /**
     * 导出兑换码
     */
    public function exportCodes($batchNo) {
        $codes = $this->db->fetchAll(
            "SELECT code, value, created_at, expires_at FROM exchange_codes WHERE batch_no = ? ORDER BY created_at",
            [$batchNo]
        );
        
        if (empty($codes)) {
            throw new Exception('批次不存在或无兑换码');
        }
        
        // 生成CSV内容
        $csv = "兑换码,面值,创建时间,过期时间\n";
        foreach ($codes as $code) {
            $csv .= "{$code['code']},{$code['value']},{$code['created_at']},{$code['expires_at']}\n";
        }
        
        return [
            'filename' => "兑换码_{$batchNo}.csv",
            'content' => $csv,
            'count' => count($codes)
        ];
    }
    
    /**
     * 获取兑换码统计
     */
    public function getStatistics() {
        $stats = [];
        
        // 总兑换码数
        $stats['total_codes'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM exchange_codes"
        )['count'];
        
        // 已使用兑换码数
        $stats['used_codes'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM exchange_codes WHERE is_used = 1"
        )['count'];
        
        // 未使用兑换码数
        $stats['unused_codes'] = $stats['total_codes'] - $stats['used_codes'];
        
        // 总价值
        $stats['total_value'] = $this->db->fetchOne(
            "SELECT SUM(value) as total FROM exchange_codes"
        )['total'] ?? 0;
        
        // 已使用价值
        $stats['used_value'] = $this->db->fetchOne(
            "SELECT SUM(value) as total FROM exchange_codes WHERE is_used = 1"
        )['total'] ?? 0;
        
        // 使用率
        $stats['usage_rate'] = $stats['total_codes'] > 0 
            ? round($stats['used_codes'] / $stats['total_codes'] * 100, 2) 
            : 0;
        
        return $stats;
    }
}