<?php
/**
 * 用户类
 * 复盘精灵系统
 */

class User {
    private $db;
    private $smsService;
    
    public function __construct($smsService = null) {
        // 优先使用单例模式，如果不可用则创建新实例
        if (method_exists('Database', 'getInstance')) {
            $this->db = Database::getInstance();
        } else {
            $this->db = new Database();
        }
        $this->smsService = $smsService ?: new SmsService();
    }
    
    /**
     * 用户注册
     */
    public function register($phone, $password, $smsCode) {
        // 验证短信验证码
        if (!$this->smsService->verifySmsCode($phone, $smsCode, 'register')) {
            throw new Exception('验证码错误或已过期');
        }
        
        // 检查用户是否已存在
        if ($this->getUserByPhone($phone)) {
            throw new Exception('该手机号已注册');
        }
        
        // 创建用户
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $userId = $this->db->insert(
            "INSERT INTO users (phone, password_hash, nickname) VALUES (?, ?, ?)",
            [$phone, $passwordHash, '用户' . substr($phone, -4)]
        );
        
        // 标记验证码已使用
        $this->smsService->markSmsCodeUsed($phone, $smsCode, 'register');
        
        return $userId;
    }
    
    /**
     * 用户登录
     */
    public function login($phone, $password) {
        $user = $this->getUserByPhone($phone);
        
        if (!$user) {
            throw new Exception('用户不存在');
        }
        
        if ($user['status'] != 1) {
            throw new Exception('账户已被禁用');
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            throw new Exception('密码错误');
        }
        
        // 更新最后登录时间和IP
        $this->updateLastLogin($user['id']);
        
        return $user;
    }
    
    /**
     * 短信登录
     */
    public function smsLogin($phone, $smsCode) {
        // 验证短信验证码
        if (!$this->smsService->verifySmsCode($phone, $smsCode, 'login')) {
            throw new Exception('验证码错误或已过期');
        }
        
        $user = $this->getUserByPhone($phone);
        
        if (!$user) {
            throw new Exception('用户不存在，请先注册');
        }
        
        if ($user['status'] != 1) {
            throw new Exception('账户已被禁用');
        }
        
        // 更新最后登录时间和IP
        $this->updateLastLogin($user['id']);
        
        // 标记验证码已使用
        $this->smsService->markSmsCodeUsed($phone, $smsCode, 'login');
        
        return $user;
    }
    
    /**
     * 根据手机号获取用户
     */
    public function getUserByPhone($phone) {
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE phone = ?",
            [$phone]
        );
    }
    
    /**
     * 根据ID获取用户
     */
    public function getUserById($id) {
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE id = ?",
            [$id]
        );
    }
    
    /**
     * 更新用户信息
     */
    public function updateUser($userId, $data) {
        $allowedFields = ['nickname', 'avatar'];
        $fields = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $fields[] = "{$field} = ?";
                $params[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        
        $this->db->query($sql, $params);
        return true;
    }
    
    /**
     * 检查精灵币余额
     */
    public function checkCoinBalance($userId, $amount) {
        $user = $this->getUserById($userId);
        return $user && $user['jingling_coins'] >= $amount;
    }
    
    /**
     * 消耗精灵币
     */
    public function consumeCoins($userId, $amount, $orderId, $description) {
        $this->db->beginTransaction();
        
        try {
            // 检查余额
            $user = $this->getUserById($userId);
            if (!$user || $user['jingling_coins'] < $amount) {
                throw new Exception('精灵币余额不足');
            }
            
            // 扣除精灵币
            $newBalance = $user['jingling_coins'] - $amount;
            $this->db->query(
                "UPDATE users SET jingling_coins = ? WHERE id = ?",
                [$newBalance, $userId]
            );
            
            // 记录交易
            $this->recordCoinTransaction($userId, 'consume', -$amount, $newBalance, $orderId, null, $description);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 充值精灵币
     */
    public function rechargeCoins($userId, $amount, $exchangeCodeId, $description) {
        $this->db->beginTransaction();
        
        try {
            // 获取当前余额
            $user = $this->getUserById($userId);
            $newBalance = $user['jingling_coins'] + $amount;
            
            // 增加精灵币
            $this->db->query(
                "UPDATE users SET jingling_coins = ? WHERE id = ?",
                [$newBalance, $userId]
            );
            
            // 记录交易
            $this->recordCoinTransaction($userId, 'recharge', $amount, $newBalance, null, $exchangeCodeId, $description);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 记录精灵币交易
     */
    private function recordCoinTransaction($userId, $type, $amount, $balanceAfter, $orderId = null, $exchangeCodeId = null, $description = '') {
        $this->db->insert(
            "INSERT INTO coin_transactions (user_id, type, amount, balance_after, related_order_id, exchange_code_id, description) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$userId, $type, $amount, $balanceAfter, $orderId, $exchangeCodeId, $description]
        );
    }
    
    
    /**
     * 更新最后登录信息
     */
    private function updateLastLogin($userId) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $this->db->query(
            "UPDATE users SET last_login_time = NOW(), last_login_ip = ? WHERE id = ?",
            [$ip, $userId]
        );
    }
    
    /**
     * 获取用户的交易记录
     */
    public function getCoinTransactions($userId, $page = 1, $pageSize = 20) {
        $offset = ($page - 1) * $pageSize;
        
        $transactions = $this->db->fetchAll(
            "SELECT ct.*, ao.title as order_title, ec.code as exchange_code 
             FROM coin_transactions ct 
             LEFT JOIN analysis_orders ao ON ct.related_order_id = ao.id 
             LEFT JOIN exchange_codes ec ON ct.exchange_code_id = ec.id 
             WHERE ct.user_id = ? 
             ORDER BY ct.created_at DESC 
             LIMIT ? OFFSET ?",
            [$userId, $pageSize, $offset]
        );
        
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM coin_transactions WHERE user_id = ?",
            [$userId]
        )['count'];
        
        return [
            'transactions' => $transactions,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => ceil($total / $pageSize)
        ];
    }
}