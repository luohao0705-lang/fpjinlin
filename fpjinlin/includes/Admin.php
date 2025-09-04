<?php
/**
 * 管理员管理类
 * 复盘精灵系统
 */

require_once __DIR__ . '/../config/config.php';

class Admin {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 管理员登录
     */
    public function login($username, $password) {
        $admin = $this->db->fetchOne(
            "SELECT * FROM admins WHERE username = ? AND status = 'active'",
            [$username]
        );
        
        if (!$admin) {
            throw new Exception('管理员不存在或已被禁用');
        }
        
        if (!password_verify($password, $admin['password'])) {
            throw new Exception('密码错误');
        }
        
        // 更新最后登录时间
        $this->db->query(
            "UPDATE admins SET last_login_time = NOW() WHERE id = ?",
            [$admin['id']]
        );
        
        // 设置管理员会话
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['admin_login_time'] = time();
        
        // 记录登录日志
        $this->logAction($admin['id'], 'login', null, null, '管理员登录');
        
        return $admin;
    }
    
    /**
     * 管理员登出
     */
    public function logout() {
        if (isset($_SESSION['admin_id'])) {
            $this->logAction($_SESSION['admin_id'], 'logout', null, null, '管理员登出');
        }
        
        // 清除管理员会话
        unset($_SESSION['admin_id']);
        unset($_SESSION['admin_username']);
        unset($_SESSION['admin_role']);
        unset($_SESSION['admin_login_time']);
        
        return true;
    }
    
    /**
     * 检查管理员是否已登录
     */
    public function isLoggedIn() {
        return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
    }
    
    /**
     * 获取当前登录管理员
     */
    public function getCurrentAdmin() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $this->getAdminById($_SESSION['admin_id']);
    }
    
    /**
     * 根据ID获取管理员
     */
    public function getAdminById($adminId) {
        return $this->db->fetchOne(
            "SELECT id, username, real_name, email, role, status, last_login_time, created_at FROM admins WHERE id = ?",
            [$adminId]
        );
    }
    
    /**
     * 要求管理员登录
     */
    public function requireLogin($requiredRole = null) {
        if (!$this->isLoggedIn()) {
            header('Location: /admin/login.php');
            exit;
        }
        
        if ($requiredRole) {
            $currentAdmin = $this->getCurrentAdmin();
            if (!$this->hasRole($currentAdmin['role'], $requiredRole)) {
                throw new Exception('权限不足');
            }
        }
    }
    
    /**
     * 检查角色权限
     */
    public function hasRole($userRole, $requiredRole) {
        $roleHierarchy = [
            'operator' => 1,
            'admin' => 2,
            'super_admin' => 3
        ];
        
        $userLevel = $roleHierarchy[$userRole] ?? 0;
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 999;
        
        return $userLevel >= $requiredLevel;
    }
    
    /**
     * 获取系统统计数据
     */
    public function getSystemStats() {
        // 用户统计
        $userStats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_users,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
                COUNT(CASE WHEN created_at >= CURDATE() THEN 1 END) as today_new_users,
                COUNT(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_new_users
             FROM users"
        );
        
        // 订单统计
        $orderStats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_orders,
                COUNT(CASE WHEN created_at >= CURDATE() THEN 1 END) as today_orders,
                SUM(CASE WHEN status = 'completed' THEN cost_coins ELSE 0 END) as total_consumed_coins
             FROM analysis_orders"
        );
        
        // 兑换码统计
        $codeStats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_codes,
                COUNT(CASE WHEN status = 'used' THEN 1 END) as used_codes,
                SUM(CASE WHEN status = 'used' THEN coins_value ELSE 0 END) as total_recharged_coins
             FROM exchange_codes"
        );
        
        // 精灵币统计
        $coinStats = $this->db->fetchOne(
            "SELECT 
                SUM(spirit_coins) as total_user_coins,
                AVG(spirit_coins) as avg_user_coins
             FROM users WHERE status = 'active'"
        );
        
        return [
            'users' => $userStats,
            'orders' => $orderStats,
            'codes' => $codeStats,
            'coins' => $coinStats
        ];
    }
    
    /**
     * 获取用户列表
     */
    public function getUsers($page = 1, $pageSize = ADMIN_PAGE_SIZE, $filters = []) {
        $where = "1=1";
        $params = [];
        
        // 状态筛选
        if (!empty($filters['status'])) {
            $where .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        // 手机号搜索
        if (!empty($filters['phone'])) {
            $where .= " AND phone LIKE ?";
            $params[] = '%' . $filters['phone'] . '%';
        }
        
        // 昵称搜索
        if (!empty($filters['nickname'])) {
            $where .= " AND nickname LIKE ?";
            $params[] = '%' . $filters['nickname'] . '%';
        }
        
        // 时间范围筛选
        if (!empty($filters['start_date'])) {
            $where .= " AND created_at >= ?";
            $params[] = $filters['start_date'] . ' 00:00:00';
        }
        if (!empty($filters['end_date'])) {
            $where .= " AND created_at <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }
        
        $offset = ($page - 1) * $pageSize;
        
        // 获取用户列表
        $users = $this->db->fetchAll(
            "SELECT id, phone, nickname, avatar, spirit_coins, total_reports, status, last_login_time, created_at 
             FROM users 
             WHERE {$where} 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?",
            array_merge($params, [$pageSize, $offset])
        );
        
        // 获取总数
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users WHERE {$where}",
            $params
        )['count'];
        
        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => ceil($total / $pageSize)
        ];
    }
    
    /**
     * 更新用户状态
     */
    public function updateUserStatus($userId, $status, $adminId) {
        $allowedStatuses = ['active', 'banned'];
        if (!in_array($status, $allowedStatuses)) {
            throw new Exception('无效的用户状态');
        }
        
        $user = $this->db->fetchOne("SELECT phone, nickname FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            throw new Exception('用户不存在');
        }
        
        $this->db->query(
            "UPDATE users SET status = ? WHERE id = ?",
            [$status, $userId]
        );
        
        // 记录操作日志
        $this->logAction($adminId, 'update_user_status', 'user', $userId, 
            "更新用户状态：{$user['phone']}({$user['nickname']}) -> {$status}");
        
        return true;
    }
    
    /**
     * 调整用户精灵币
     */
    public function adjustUserCoins($userId, $amount, $description, $adminId) {
        if ($amount == 0) {
            throw new Exception('调整金额不能为0');
        }
        
        $this->db->beginTransaction();
        
        try {
            $user = $this->db->fetchOne("SELECT phone, nickname, spirit_coins FROM users WHERE id = ?", [$userId]);
            if (!$user) {
                throw new Exception('用户不存在');
            }
            
            // 检查余额（如果是扣除）
            if ($amount < 0 && $user['spirit_coins'] + $amount < 0) {
                throw new Exception('用户余额不足');
            }
            
            // 更新用户精灵币
            $this->db->query(
                "UPDATE users SET spirit_coins = spirit_coins + ? WHERE id = ?",
                [$amount, $userId]
            );
            
            $newBalance = $user['spirit_coins'] + $amount;
            
            // 记录交易
            $transactionType = $amount > 0 ? 'recharge' : 'consume';
            $this->db->query(
                "INSERT INTO coin_transactions (user_id, transaction_type, amount, balance_after, description) VALUES (?, ?, ?, ?, ?)",
                [$userId, $transactionType, $amount, $newBalance, $description]
            );
            
            // 记录操作日志
            $this->logAction($adminId, 'adjust_user_coins', 'user', $userId, 
                "调整精灵币：{$user['phone']}({$user['nickname']}) {$amount} -> {$newBalance}，原因：{$description}");
            
            $this->db->commit();
            return $newBalance;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 更新系统配置
     */
    public function updateSystemConfig($configKey, $configValue, $adminId) {
        $config = $this->db->fetchOne(
            "SELECT * FROM system_configs WHERE config_key = ?",
            [$configKey]
        );
        
        if (!$config) {
            throw new Exception('配置项不存在');
        }
        
        // 类型转换验证
        switch ($config['config_type']) {
            case 'int':
                if (!is_numeric($configValue)) {
                    throw new Exception('配置值必须是数字');
                }
                $configValue = intval($configValue);
                break;
            case 'boolean':
                $configValue = $configValue ? '1' : '0';
                break;
            case 'json':
                $testJson = json_decode($configValue);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('配置值必须是有效的JSON格式');
                }
                break;
        }
        
        $this->db->query(
            "UPDATE system_configs SET config_value = ?, updated_by_admin_id = ? WHERE config_key = ?",
            [$configValue, $adminId, $configKey]
        );
        
        // 记录操作日志
        $this->logAction($adminId, 'update_config', 'config', null, 
            "更新系统配置：{$configKey} = {$configValue}");
        
        return true;
    }
    
    /**
     * 获取所有系统配置
     */
    public function getSystemConfigs() {
        return $this->db->fetchAll(
            "SELECT * FROM system_configs ORDER BY config_key"
        );
    }
    
    /**
     * 记录操作日志
     */
    public function logAction($adminId, $action, $targetType = null, $targetId = null, $description = '') {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $this->db->query(
            "INSERT INTO admin_logs (admin_id, action, target_type, target_id, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$adminId, $action, $targetType, $targetId, $description, $ipAddress, $userAgent]
        );
    }
    
    /**
     * 获取操作日志
     */
    public function getAdminLogs($page = 1, $pageSize = ADMIN_PAGE_SIZE, $filters = []) {
        $where = "1=1";
        $params = [];
        
        // 管理员筛选
        if (!empty($filters['admin_id'])) {
            $where .= " AND al.admin_id = ?";
            $params[] = $filters['admin_id'];
        }
        
        // 操作类型筛选
        if (!empty($filters['action'])) {
            $where .= " AND al.action = ?";
            $params[] = $filters['action'];
        }
        
        // 时间范围筛选
        if (!empty($filters['start_date'])) {
            $where .= " AND al.created_at >= ?";
            $params[] = $filters['start_date'] . ' 00:00:00';
        }
        if (!empty($filters['end_date'])) {
            $where .= " AND al.created_at <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }
        
        $offset = ($page - 1) * $pageSize;
        
        // 获取日志列表
        $logs = $this->db->fetchAll(
            "SELECT al.*, a.username, a.real_name 
             FROM admin_logs al 
             LEFT JOIN admins a ON al.admin_id = a.id 
             WHERE {$where} 
             ORDER BY al.created_at DESC 
             LIMIT ? OFFSET ?",
            array_merge($params, [$pageSize, $offset])
        );
        
        // 获取总数
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM admin_logs al WHERE {$where}",
            $params
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
     * 创建管理员
     */
    public function createAdmin($username, $password, $realName, $email, $role, $creatorId) {
        // 检查用户名是否已存在
        $existingAdmin = $this->db->fetchOne(
            "SELECT id FROM admins WHERE username = ?",
            [$username]
        );
        
        if ($existingAdmin) {
            throw new Exception('管理员用户名已存在');
        }
        
        // 验证角色
        $allowedRoles = ['operator', 'admin', 'super_admin'];
        if (!in_array($role, $allowedRoles)) {
            throw new Exception('无效的管理员角色');
        }
        
        // 密码验证
        if (strlen($password) < 6) {
            throw new Exception('密码长度至少6位');
        }
        
        // 创建管理员
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $this->db->query(
            "INSERT INTO admins (username, password, real_name, email, role) VALUES (?, ?, ?, ?, ?)",
            [$username, $hashedPassword, $realName, $email, $role]
        );
        
        $adminId = $this->db->lastInsertId();
        
        // 记录操作日志
        $this->logAction($creatorId, 'create_admin', 'admin', $adminId, 
            "创建管理员：{$username}({$realName}) - {$role}");
        
        return $this->getAdminById($adminId);
    }
    
    /**
     * 获取管理员列表
     */
    public function getAdmins($page = 1, $pageSize = ADMIN_PAGE_SIZE) {
        $offset = ($page - 1) * $pageSize;
        
        $admins = $this->db->fetchAll(
            "SELECT id, username, real_name, email, role, status, last_login_time, created_at 
             FROM admins 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?",
            [$pageSize, $offset]
        );
        
        $total = $this->db->fetchOne("SELECT COUNT(*) as count FROM admins")['count'];
        
        return [
            'admins' => $admins,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => ceil($total / $pageSize)
        ];
    }
    
    /**
     * 更新管理员状态
     */
    public function updateAdminStatus($adminId, $status, $operatorId) {
        $allowedStatuses = ['active', 'disabled'];
        if (!in_array($status, $allowedStatuses)) {
            throw new Exception('无效的管理员状态');
        }
        
        // 不能禁用自己
        if ($adminId == $operatorId) {
            throw new Exception('不能修改自己的状态');
        }
        
        $admin = $this->getAdminById($adminId);
        if (!$admin) {
            throw new Exception('管理员不存在');
        }
        
        $this->db->query(
            "UPDATE admins SET status = ? WHERE id = ?",
            [$status, $adminId]
        );
        
        // 记录操作日志
        $this->logAction($operatorId, 'update_admin_status', 'admin', $adminId, 
            "更新管理员状态：{$admin['username']}({$admin['real_name']}) -> {$status}");
        
        return true;
    }
    
    /**
     * 获取今日数据概览
     */
    public function getTodayOverview() {
        $today = date('Y-m-d');
        
        return [
            'new_users' => $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = ?", 
                [$today]
            )['count'],
            'new_orders' => $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM analysis_orders WHERE DATE(created_at) = ?", 
                [$today]
            )['count'],
            'completed_orders' => $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM analysis_orders WHERE DATE(completed_at) = ?", 
                [$today]
            )['count'],
            'consumed_coins' => $this->db->fetchOne(
                "SELECT SUM(amount) as total FROM coin_transactions WHERE transaction_type = 'consume' AND DATE(created_at) = ?", 
                [$today]
            )['total'] ?? 0,
            'recharged_coins' => $this->db->fetchOne(
                "SELECT SUM(amount) as total FROM coin_transactions WHERE transaction_type = 'recharge' AND DATE(created_at) = ?", 
                [$today]
            )['total'] ?? 0
        ];
    }
}
?>