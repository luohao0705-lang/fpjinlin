<?php
/**
 * 兑换码管理类
 * 复盘精灵系统
 */

require_once __DIR__ . '/../config/config.php';

class ExchangeCode {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 批量生成兑换码
     */
    public function generateCodes($coinsValue, $quantity, $adminId, $expiresAt = null) {
        $batchId = 'BATCH_' . date('YmdHis') . '_' . mt_rand(1000, 9999);
        $codes = [];
        
        $this->db->beginTransaction();
        
        try {
            for ($i = 0; $i < $quantity; $i++) {
                // 生成唯一兑换码
                do {
                    $code = generateExchangeCode();
                    $exists = $this->db->fetchOne(
                        "SELECT id FROM exchange_codes WHERE code = ?",
                        [$code]
                    );
                } while ($exists);
                
                // 插入兑换码
                $this->db->query(
                    "INSERT INTO exchange_codes (code, coins_value, batch_id, expires_at, created_by_admin_id) VALUES (?, ?, ?, ?, ?)",
                    [$code, $coinsValue, $batchId, $expiresAt, $adminId]
                );
                
                $codes[] = [
                    'code' => $code,
                    'coins_value' => $coinsValue,
                    'batch_id' => $batchId,
                    'expires_at' => $expiresAt
                ];
            }
            
            $this->db->commit();
            
            return [
                'batch_id' => $batchId,
                'codes' => $codes,
                'total' => $quantity
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 验证兑换码
     */
    public function validateCode($code) {
        $codeInfo = $this->db->fetchOne(
            "SELECT * FROM exchange_codes WHERE code = ?",
            [$code]
        );
        
        if (!$codeInfo) {
            throw new Exception('兑换码不存在');
        }
        
        if ($codeInfo['status'] !== 'unused') {
            throw new Exception('兑换码已使用或已过期');
        }
        
        if ($codeInfo['expires_at'] && strtotime($codeInfo['expires_at']) < time()) {
            // 标记为过期
            $this->db->query(
                "UPDATE exchange_codes SET status = 'expired' WHERE id = ?",
                [$codeInfo['id']]
            );
            throw new Exception('兑换码已过期');
        }
        
        return $codeInfo;
    }
    
    /**
     * 使用兑换码
     */
    public function useCode($code, $userId) {
        $this->db->beginTransaction();
        
        try {
            // 验证兑换码
            $codeInfo = $this->validateCode($code);
            
            // 更新用户精灵币
            $this->db->query(
                "UPDATE users SET spirit_coins = spirit_coins + ? WHERE id = ?",
                [$codeInfo['coins_value'], $userId]
            );
            
            // 获取更新后的余额
            $newBalance = $this->db->fetchOne(
                "SELECT spirit_coins FROM users WHERE id = ?",
                [$userId]
            )['spirit_coins'];
            
            // 标记兑换码为已使用
            $this->db->query(
                "UPDATE exchange_codes SET status = 'used', used_by_user_id = ?, used_at = NOW() WHERE id = ?",
                [$userId, $codeInfo['id']]
            );
            
            // 记录交易
            $this->db->query(
                "INSERT INTO coin_transactions (user_id, transaction_type, amount, balance_after, exchange_code_id, description) VALUES (?, 'recharge', ?, ?, ?, ?)",
                [$userId, $codeInfo['coins_value'], $newBalance, $codeInfo['id'], "兑换码充值：{$code}"]
            );
            
            $this->db->commit();
            
            return [
                'coins_added' => $codeInfo['coins_value'],
                'new_balance' => $newBalance
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 获取兑换码列表（管理员用）
     */
    public function getCodes($page = 1, $pageSize = ADMIN_PAGE_SIZE, $filters = []) {
        $where = "1=1";
        $params = [];
        
        // 状态筛选
        if (!empty($filters['status'])) {
            $where .= " AND ec.status = ?";
            $params[] = $filters['status'];
        }
        
        // 批次筛选
        if (!empty($filters['batch_id'])) {
            $where .= " AND ec.batch_id = ?";
            $params[] = $filters['batch_id'];
        }
        
        // 面值筛选
        if (!empty($filters['coins_value'])) {
            $where .= " AND ec.coins_value = ?";
            $params[] = $filters['coins_value'];
        }
        
        // 时间范围筛选
        if (!empty($filters['start_date'])) {
            $where .= " AND ec.created_at >= ?";
            $params[] = $filters['start_date'] . ' 00:00:00';
        }
        if (!empty($filters['end_date'])) {
            $where .= " AND ec.created_at <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }
        
        $offset = ($page - 1) * $pageSize;
        
        // 获取兑换码列表
        $codes = $this->db->fetchAll(
            "SELECT ec.*, u.phone as used_by_phone, u.nickname as used_by_nickname, a.username as created_by_username
             FROM exchange_codes ec 
             LEFT JOIN users u ON ec.used_by_user_id = u.id 
             LEFT JOIN admins a ON ec.created_by_admin_id = a.id 
             WHERE {$where} 
             ORDER BY ec.created_at DESC 
             LIMIT ? OFFSET ?",
            array_merge($params, [$pageSize, $offset])
        );
        
        // 获取总数
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM exchange_codes ec WHERE {$where}",
            $params
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
     * 获取批次统计
     */
    public function getBatchStats($batchId) {
        return $this->db->fetchOne(
            "SELECT 
                batch_id,
                COUNT(*) as total_codes,
                SUM(CASE WHEN status = 'unused' THEN 1 ELSE 0 END) as unused_codes,
                SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_codes,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_codes,
                SUM(CASE WHEN status = 'used' THEN coins_value ELSE 0 END) as used_coins_value,
                MIN(created_at) as created_at,
                MAX(expires_at) as expires_at
             FROM exchange_codes 
             WHERE batch_id = ?
             GROUP BY batch_id",
            [$batchId]
        );
    }
    
    /**
     * 获取所有批次列表
     */
    public function getAllBatches() {
        return $this->db->fetchAll(
            "SELECT 
                batch_id,
                coins_value,
                COUNT(*) as total_codes,
                SUM(CASE WHEN status = 'unused' THEN 1 ELSE 0 END) as unused_codes,
                SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_codes,
                MIN(created_at) as created_at,
                MAX(expires_at) as expires_at
             FROM exchange_codes 
             GROUP BY batch_id, coins_value 
             ORDER BY created_at DESC"
        );
    }
    
    /**
     * 导出兑换码（CSV格式）
     */
    public function exportCodes($batchId) {
        $codes = $this->db->fetchAll(
            "SELECT code, coins_value, status, expires_at, created_at FROM exchange_codes WHERE batch_id = ? ORDER BY created_at",
            [$batchId]
        );
        
        $csvContent = "兑换码,面值,状态,过期时间,创建时间\n";
        foreach ($codes as $code) {
            $csvContent .= sprintf(
                "%s,%d,%s,%s,%s\n",
                $code['code'],
                $code['coins_value'],
                $code['status'],
                $code['expires_at'] ?: '永久有效',
                $code['created_at']
            );
        }
        
        return $csvContent;
    }
    
    /**
     * 删除批次（仅未使用的兑换码）
     */
    public function deleteBatch($batchId, $adminId) {
        $this->db->beginTransaction();
        
        try {
            // 检查是否有已使用的兑换码
            $usedCount = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM exchange_codes WHERE batch_id = ? AND status = 'used'",
                [$batchId]
            )['count'];
            
            if ($usedCount > 0) {
                throw new Exception('该批次包含已使用的兑换码，无法删除');
            }
            
            // 删除未使用的兑换码
            $this->db->query(
                "DELETE FROM exchange_codes WHERE batch_id = ? AND status IN ('unused', 'expired')",
                [$batchId]
            );
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
?>