<?php
/**
 * 用户管理类
 * 复盘精灵系统
 */

require_once __DIR__ . '/../config/config.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 发送短信验证码
     */
    public function sendSMSCode($phone, $type = 'login') {
        // 验证手机号格式
        if (!isValidPhone($phone)) {
            throw new Exception('手机号格式不正确');
        }
        
        // 检查发送频率（1分钟内只能发送一次）
        $recentCode = $this->db->fetchOne(
            "SELECT id FROM sms_codes WHERE phone = ? AND type = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE) ORDER BY created_at DESC LIMIT 1",
            [$phone, $type]
        );
        
        if ($recentCode) {
            throw new Exception('验证码发送过于频繁，请稍后再试');
        }
        
        // 生成验证码
        $code = generateSMSCode();
        $expiresAt = date('Y-m-d H:i:s', time() + SMS_CODE_EXPIRE_MINUTES * 60);
        
        // 保存验证码
        $this->db->query(
            "INSERT INTO sms_codes (phone, code, type, expires_at) VALUES (?, ?, ?, ?)",
            [$phone, $code, $type, $expiresAt]
        );
        
        // 发送短信
        return $this->sendSMS($phone, $code, $type);
    }
    
    /**
     * 发送短信（调用阿里云SMS）
     */
    private function sendSMS($phone, $code, $type) {
        $accessKey = getSystemConfig('sms_access_key');
        $secretKey = getSystemConfig('sms_secret_key');
        $signName = getSystemConfig('sms_sign_name', '复盘精灵');
        
        if (empty($accessKey) || empty($secretKey)) {
            throw new Exception('短信服务未配置');
        }
        
        // 获取模板代码
        $templateMap = [
            'register' => getSystemConfig('sms_template_register'),
            'login' => getSystemConfig('sms_template_login'),
            'report_complete' => getSystemConfig('sms_template_report_complete')
        ];
        
        $templateCode = $templateMap[$type] ?? $templateMap['login'];
        
        if (empty($templateCode)) {
            throw new Exception('短信模板未配置');
        }
        
        // 构建请求参数
        $params = [
            'PhoneNumbers' => $phone,
            'SignName' => $signName,
            'TemplateCode' => $templateCode,
            'TemplateParam' => json_encode(['code' => $code])
        ];
        
        // 这里简化处理，实际应该使用阿里云SDK
        // 开发环境下直接返回成功
        if ($_SERVER['SERVER_NAME'] === 'localhost' || strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false) {
            error_log("开发环境短信验证码: {$phone} -> {$code}");
            return true;
        }
        
        // 生产环境需要集成真实的阿里云SMS SDK
        // TODO: 集成阿里云SMS SDK
        return true;
    }
    
    /**
     * 验证短信验证码
     */
    public function verifySMSCode($phone, $code, $type = 'login') {
        $record = $this->db->fetchOne(
            "SELECT id FROM sms_codes WHERE phone = ? AND code = ? AND type = ? AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1",
            [$phone, $code, $type]
        );
        
        if (!$record) {
            throw new Exception('验证码无效或已过期');
        }
        
        // 标记验证码为已使用
        $this->db->query(
            "UPDATE sms_codes SET used = 1 WHERE id = ?",
            [$record['id']]
        );
        
        return true;
    }
    
    /**
     * 用户注册
     */
    public function register($phone, $password, $smsCode, $nickname = null) {
        // 验证短信验证码
        $this->verifySMSCode($phone, $smsCode, 'register');
        
        // 检查用户是否已存在
        $existingUser = $this->db->fetchOne(
            "SELECT id FROM users WHERE phone = ?",
            [$phone]
        );
        
        if ($existingUser) {
            throw new Exception('该手机号已注册');
        }
        
        // 密码验证
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            throw new Exception('密码长度至少' . PASSWORD_MIN_LENGTH . '位');
        }
        
        // 创建用户
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $nickname = $nickname ?: '用户' . substr($phone, -4);
        
        $this->db->query(
            "INSERT INTO users (phone, password, nickname, spirit_coins) VALUES (?, ?, ?, ?)",
            [$phone, $hashedPassword, $nickname, 0]
        );
        
        $userId = $this->db->lastInsertId();
        
        return $this->getUserById($userId);
    }
    
    /**
     * 用户登录
     */
    public function login($phone, $password = null, $smsCode = null) {
        // 获取用户信息
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE phone = ? AND status = 'active'",
            [$phone]
        );
        
        if (!$user) {
            throw new Exception('用户不存在或已被禁用');
        }
        
        // 验证方式：密码登录或短信登录
        if ($password) {
            // 密码登录
            if (!password_verify($password, $user['password'])) {
                throw new Exception('密码错误');
            }
        } elseif ($smsCode) {
            // 短信登录
            $this->verifySMSCode($phone, $smsCode, 'login');
        } else {
            throw new Exception('请提供密码或短信验证码');
        }
        
        // 更新最后登录时间
        $this->db->query(
            "UPDATE users SET last_login_time = NOW() WHERE id = ?",
            [$user['id']]
        );
        
        // 设置会话
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_phone'] = $user['phone'];
        $_SESSION['user_nickname'] = $user['nickname'];
        $_SESSION['login_time'] = time();
        
        return $user;
    }
    
    /**
     * 用户登出
     */
    public function logout() {
        session_destroy();
        return true;
    }
    
    /**
     * 检查用户是否已登录
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * 获取当前登录用户
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $this->getUserById($_SESSION['user_id']);
    }
    
    /**
     * 根据ID获取用户
     */
    public function getUserById($userId) {
        return $this->db->fetchOne(
            "SELECT id, phone, nickname, avatar, spirit_coins, total_reports, status, last_login_time, created_at FROM users WHERE id = ?",
            [$userId]
        );
    }
    
    /**
     * 更新用户信息
     */
    public function updateUser($userId, $data) {
        $allowedFields = ['nickname', 'avatar'];
        $updateFields = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updateFields[] = "{$field} = ?";
                $params[] = $value;
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception('没有可更新的字段');
        }
        
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * 修改密码
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        $user = $this->getUserById($userId);
        if (!$user) {
            throw new Exception('用户不存在');
        }
        
        // 验证原密码
        $currentPassword = $this->db->fetchOne(
            "SELECT password FROM users WHERE id = ?",
            [$userId]
        )['password'];
        
        if (!password_verify($oldPassword, $currentPassword)) {
            throw new Exception('原密码错误');
        }
        
        // 验证新密码
        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            throw new Exception('新密码长度至少' . PASSWORD_MIN_LENGTH . '位');
        }
        
        // 更新密码
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        return $this->db->query(
            "UPDATE users SET password = ? WHERE id = ?",
            [$hashedPassword, $userId]
        );
    }
    
    /**
     * 使用兑换码充值精灵币
     */
    public function rechargeWithCode($userId, $exchangeCode) {
        $this->db->beginTransaction();
        
        try {
            // 获取兑换码信息
            $code = $this->db->fetchOne(
                "SELECT * FROM exchange_codes WHERE code = ? AND status = 'unused' AND (expires_at IS NULL OR expires_at > NOW())",
                [$exchangeCode]
            );
            
            if (!$code) {
                throw new Exception('兑换码无效或已过期');
            }
            
            // 更新用户精灵币
            $this->db->query(
                "UPDATE users SET spirit_coins = spirit_coins + ? WHERE id = ?",
                [$code['coins_value'], $userId]
            );
            
            // 获取更新后的余额
            $newBalance = $this->db->fetchOne(
                "SELECT spirit_coins FROM users WHERE id = ?",
                [$userId]
            )['spirit_coins'];
            
            // 标记兑换码为已使用
            $this->db->query(
                "UPDATE exchange_codes SET status = 'used', used_by_user_id = ?, used_at = NOW() WHERE id = ?",
                [$userId, $code['id']]
            );
            
            // 记录交易
            $this->db->query(
                "INSERT INTO coin_transactions (user_id, transaction_type, amount, balance_after, exchange_code_id, description) VALUES (?, 'recharge', ?, ?, ?, ?)",
                [$userId, $code['coins_value'], $newBalance, $code['id'], "兑换码充值：{$exchangeCode}"]
            );
            
            $this->db->commit();
            
            return [
                'coins_added' => $code['coins_value'],
                'new_balance' => $newBalance
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 消耗精灵币
     */
    public function consumeCoins($userId, $amount, $orderId = null, $description = '') {
        $this->db->beginTransaction();
        
        try {
            // 检查用户余额
            $user = $this->getUserById($userId);
            if ($user['spirit_coins'] < $amount) {
                throw new Exception('精灵币余额不足');
            }
            
            // 扣除精灵币
            $this->db->query(
                "UPDATE users SET spirit_coins = spirit_coins - ? WHERE id = ?",
                [$amount, $userId]
            );
            
            $newBalance = $user['spirit_coins'] - $amount;
            
            // 记录交易
            $this->db->query(
                "INSERT INTO coin_transactions (user_id, transaction_type, amount, balance_after, related_order_id, description) VALUES (?, 'consume', ?, ?, ?, ?)",
                [$userId, -$amount, $newBalance, $orderId, $description]
            );
            
            $this->db->commit();
            return $newBalance;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 获取用户交易记录
     */
    public function getUserTransactions($userId, $page = 1, $pageSize = PAGE_SIZE) {
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
    
    /**
     * 获取用户分析订单列表
     */
    public function getUserOrders($userId, $page = 1, $pageSize = PAGE_SIZE) {
        $offset = ($page - 1) * $pageSize;
        
        $orders = $this->db->fetchAll(
            "SELECT id, order_no, title, status, cost_coins, completed_at, created_at 
             FROM analysis_orders 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?",
            [$userId, $pageSize, $offset]
        );
        
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM analysis_orders WHERE user_id = ?",
            [$userId]
        )['count'];
        
        return [
            'orders' => $orders,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => ceil($total / $pageSize)
        ];
    }
    
    /**
     * 验证用户权限
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                errorResponse('请先登录', 401);
            } else {
                header('Location: /pages/user/login.php');
                exit;
            }
        }
    }
    
    /**
     * 检查精灵币余额
     */
    public function checkCoinsBalance($userId, $requiredAmount) {
        $user = $this->getUserById($userId);
        return $user['spirit_coins'] >= $requiredAmount;
    }
}
?>