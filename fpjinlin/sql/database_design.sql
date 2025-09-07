-- 复盘精灵(fpjinlin)数据库设计
-- 创建时间: 2024年
-- PHP 7.4 + MySQL

CREATE DATABASE IF NOT EXISTS fpjinlin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fpjinlin;

-- 1. 用户表
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    phone VARCHAR(15) NOT NULL UNIQUE COMMENT '手机号',
    password VARCHAR(255) NOT NULL COMMENT '密码哈希',
    nickname VARCHAR(50) DEFAULT NULL COMMENT '昵称',
    avatar VARCHAR(255) DEFAULT NULL COMMENT '头像路径',
    spirit_coins INT DEFAULT 0 COMMENT '精灵币余额',
    total_reports INT DEFAULT 0 COMMENT '总分析报告数',
    status ENUM('active', 'banned') DEFAULT 'active' COMMENT '用户状态',
    last_login_time TIMESTAMP NULL COMMENT '最后登录时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    INDEX idx_status (status)
) ENGINE=InnoDB COMMENT='用户表';

-- 2. 短信验证码表
CREATE TABLE sms_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    phone VARCHAR(15) NOT NULL COMMENT '手机号',
    code VARCHAR(6) NOT NULL COMMENT '验证码',
    type ENUM('register', 'login', 'reset_password') NOT NULL COMMENT '验证码类型',
    used TINYINT(1) DEFAULT 0 COMMENT '是否已使用',
    expires_at TIMESTAMP NOT NULL COMMENT '过期时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone_type (phone, type),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB COMMENT='短信验证码表';

-- 3. 分析订单表
CREATE TABLE analysis_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL COMMENT '用户ID',
    order_no VARCHAR(32) NOT NULL UNIQUE COMMENT '订单号',
    title VARCHAR(100) NOT NULL COMMENT '分析标题',
    cover_image VARCHAR(255) DEFAULT NULL COMMENT '封面图片路径',
    own_script TEXT COMMENT '本方话术文本',
    competitor1_script TEXT COMMENT '同行1话术文本',
    competitor2_script TEXT COMMENT '同行2话术文本',
    competitor3_script TEXT COMMENT '同行3话术文本',
    cost_coins INT DEFAULT 100 COMMENT '消耗精灵币数量',
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending' COMMENT '订单状态',
    ai_report_content LONGTEXT COMMENT 'AI分析报告内容(JSON格式)',
    report_pdf_path VARCHAR(255) DEFAULT NULL COMMENT '报告PDF文件路径',
    report_image_path VARCHAR(255) DEFAULT NULL COMMENT '报告图片路径',
    processing_started_at TIMESTAMP NULL COMMENT '开始处理时间',
    completed_at TIMESTAMP NULL COMMENT '完成时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_order_no (order_no),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB COMMENT='分析订单表';

-- 4. 订单截图表
CREATE TABLE order_screenshots (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL COMMENT '订单ID',
    image_path VARCHAR(255) NOT NULL COMMENT '截图路径',
    image_type ENUM('data1', 'data2', 'data3', 'data4', 'data5') NOT NULL COMMENT '截图类型',
    file_size INT DEFAULT 0 COMMENT '文件大小(字节)',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES analysis_orders(id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id)
) ENGINE=InnoDB COMMENT='订单截图表';

-- 5. 兑换码表
CREATE TABLE exchange_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL UNIQUE COMMENT '兑换码',
    coins_value INT NOT NULL COMMENT '兑换精灵币数量',
    batch_id VARCHAR(50) DEFAULT NULL COMMENT '批次ID',
    status ENUM('unused', 'used', 'expired') DEFAULT 'unused' COMMENT '状态',
    used_by_user_id INT DEFAULT NULL COMMENT '使用者用户ID',
    used_at TIMESTAMP NULL COMMENT '使用时间',
    expires_at TIMESTAMP NULL COMMENT '过期时间',
    created_by_admin_id INT DEFAULT NULL COMMENT '创建管理员ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (used_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_code (code),
    INDEX idx_status (status),
    INDEX idx_batch_id (batch_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB COMMENT='兑换码表';

-- 6. 管理员表
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE COMMENT '管理员用户名',
    password VARCHAR(255) NOT NULL COMMENT '密码哈希',
    real_name VARCHAR(50) DEFAULT NULL COMMENT '真实姓名',
    email VARCHAR(100) DEFAULT NULL COMMENT '邮箱',
    role ENUM('super_admin', 'admin', 'operator') DEFAULT 'admin' COMMENT '角色',
    status ENUM('active', 'disabled') DEFAULT 'active' COMMENT '状态',
    last_login_time TIMESTAMP NULL COMMENT '最后登录时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_status (status)
) ENGINE=InnoDB COMMENT='管理员表';

-- 7. 系统配置表
CREATE TABLE system_configs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    config_key VARCHAR(100) NOT NULL UNIQUE COMMENT '配置键',
    config_value TEXT COMMENT '配置值',
    config_type ENUM('string', 'int', 'json', 'boolean') DEFAULT 'string' COMMENT '配置类型',
    description VARCHAR(255) DEFAULT NULL COMMENT '配置描述',
    updated_by_admin_id INT DEFAULT NULL COMMENT '更新者管理员ID',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_config_key (config_key)
) ENGINE=InnoDB COMMENT='系统配置表';

-- 8. 精灵币交易记录表
CREATE TABLE coin_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL COMMENT '用户ID',
    transaction_type ENUM('recharge', 'consume', 'refund') NOT NULL COMMENT '交易类型',
    amount INT NOT NULL COMMENT '交易金额（正数为增加，负数为减少）',
    balance_after INT NOT NULL COMMENT '交易后余额',
    related_order_id INT DEFAULT NULL COMMENT '关联订单ID',
    exchange_code_id INT DEFAULT NULL COMMENT '关联兑换码ID',
    description VARCHAR(255) DEFAULT NULL COMMENT '交易描述',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_order_id) REFERENCES analysis_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (exchange_code_id) REFERENCES exchange_codes(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB COMMENT='精灵币交易记录表';

-- 9. 操作日志表
CREATE TABLE admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL COMMENT '管理员ID',
    action VARCHAR(100) NOT NULL COMMENT '操作类型',
    target_type VARCHAR(50) DEFAULT NULL COMMENT '操作目标类型',
    target_id INT DEFAULT NULL COMMENT '操作目标ID',
    description TEXT COMMENT '操作描述',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
    user_agent TEXT COMMENT '用户代理',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB COMMENT='管理员操作日志表';

-- 插入默认系统配置
INSERT INTO system_configs (config_key, config_value, config_type, description) VALUES
('deepseek_api_key', '', 'string', 'DeepSeek API密钥'),
('deepseek_api_url', 'https://api.deepseek.com/v1/chat/completions', 'string', 'DeepSeek API地址'),
('sms_access_key', '', 'string', '阿里云SMS AccessKey'),
('sms_secret_key', '', 'string', '阿里云SMS SecretKey'),
('sms_sign_name', '复盘精灵', 'string', '短信签名'),
('sms_template_register', 'SMS_123456789', 'string', '注册验证码模板'),
('sms_template_login', 'SMS_123456790', 'string', '登录验证码模板'),
('sms_template_report_complete', 'SMS_123456791', 'string', '报告完成通知模板'),
('analysis_cost_coins', '100', 'int', '单次分析消耗精灵币数量'),
('max_upload_size', '10', 'int', '最大上传文件大小(MB)'),
('report_retention_days', '365', 'int', '报告保留天数'),
('site_name', '复盘精灵', 'string', '网站名称'),
('site_description', '视频号直播复盘分析SaaS平台', 'string', '网站描述');

-- 插入默认超级管理员 (用户名: admin, 密码: admin123)
INSERT INTO admins (username, password, real_name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '超级管理员', 'super_admin');