-- 复盘精灵 数据库结构设计
-- 创建时间: 2024
-- 描述: 视频号直播复盘分析SaaS平台数据库

-- 创建数据库
CREATE DATABASE IF NOT EXISTS fupan_jingling DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
USE fupan_jingling;

-- 1. 用户表
CREATE TABLE `users` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `phone` VARCHAR(11) NOT NULL UNIQUE COMMENT '手机号',
    `nickname` VARCHAR(50) DEFAULT NULL COMMENT '昵称',
    `avatar` VARCHAR(255) DEFAULT NULL COMMENT '头像URL',
    `password_hash` VARCHAR(255) NOT NULL COMMENT '密码哈希',
    `jingling_coins` INT(11) UNSIGNED DEFAULT 0 COMMENT '精灵币余额',
    `total_orders` INT(11) UNSIGNED DEFAULT 0 COMMENT '总订单数',
    `status` TINYINT(1) DEFAULT 1 COMMENT '状态：1正常，0禁用',
    `last_login_time` TIMESTAMP NULL COMMENT '最后登录时间',
    `last_login_ip` VARCHAR(45) DEFAULT NULL COMMENT '最后登录IP',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    INDEX `idx_phone` (`phone`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB COMMENT='用户表';

-- 2. 短信验证码表
CREATE TABLE `sms_codes` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `phone` VARCHAR(11) NOT NULL COMMENT '手机号',
    `code` VARCHAR(6) NOT NULL COMMENT '验证码',
    `type` ENUM('register', 'login', 'reset_password') NOT NULL COMMENT '验证码类型',
    `is_used` TINYINT(1) DEFAULT 0 COMMENT '是否已使用',
    `expires_at` TIMESTAMP NOT NULL COMMENT '过期时间',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    INDEX `idx_phone_type` (`phone`, `type`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB COMMENT='短信验证码表';

-- 3. 分析订单表
CREATE TABLE `analysis_orders` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT '用户ID',
    `order_no` VARCHAR(32) NOT NULL UNIQUE COMMENT '订单号',
    `title` VARCHAR(100) NOT NULL COMMENT '分析标题',
    `cost_coins` INT(11) UNSIGNED NOT NULL DEFAULT 10 COMMENT '消耗精灵币数量',
    `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending' COMMENT '状态',
    `live_screenshots` JSON COMMENT '直播截图路径数组',
    `cover_image` VARCHAR(255) DEFAULT NULL COMMENT '封面图路径',
    `self_script` TEXT COMMENT '本方话术文本',
    `competitor_scripts` JSON COMMENT '同行话术文本数组',
    `ai_report` LONGTEXT COMMENT 'AI生成的分析报告',
    `report_score` INT(3) DEFAULT NULL COMMENT '综合评分(0-100)',
    `report_level` ENUM('excellent', 'good', 'average', 'poor', 'unqualified') DEFAULT NULL COMMENT '等级',
    `error_message` TEXT COMMENT '错误信息',
    `completed_at` TIMESTAMP NULL COMMENT '完成时间',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_order_no` (`order_no`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB COMMENT='分析订单表';

-- 4. 兑换码表
CREATE TABLE `exchange_codes` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(32) NOT NULL UNIQUE COMMENT '兑换码',
    `value` INT(11) UNSIGNED NOT NULL COMMENT '精灵币数量',
    `batch_no` VARCHAR(32) DEFAULT NULL COMMENT '批次号',
    `is_used` TINYINT(1) DEFAULT 0 COMMENT '是否已使用',
    `used_by` INT(11) UNSIGNED DEFAULT NULL COMMENT '使用者用户ID',
    `used_at` TIMESTAMP NULL COMMENT '使用时间',
    `expires_at` TIMESTAMP NULL COMMENT '过期时间',
    `created_by` INT(11) UNSIGNED DEFAULT NULL COMMENT '创建者管理员ID',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    FOREIGN KEY (`used_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_code` (`code`),
    INDEX `idx_batch_no` (`batch_no`),
    INDEX `idx_is_used` (`is_used`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB COMMENT='兑换码表';

-- 5. 精灵币交易记录表
CREATE TABLE `coin_transactions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT '用户ID',
    `type` ENUM('recharge', 'consume', 'refund') NOT NULL COMMENT '交易类型',
    `amount` INT(11) NOT NULL COMMENT '交易数量(正数为增加，负数为减少)',
    `balance_after` INT(11) UNSIGNED NOT NULL COMMENT '交易后余额',
    `related_order_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '关联订单ID',
    `exchange_code_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '关联兑换码ID',
    `description` VARCHAR(255) NOT NULL COMMENT '交易描述',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`related_order_id`) REFERENCES `analysis_orders`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`exchange_code_id`) REFERENCES `exchange_codes`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB COMMENT='精灵币交易记录表';

-- 6. 管理员表
CREATE TABLE `admins` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE COMMENT '管理员用户名',
    `password_hash` VARCHAR(255) NOT NULL COMMENT '密码哈希',
    `real_name` VARCHAR(50) DEFAULT NULL COMMENT '真实姓名',
    `email` VARCHAR(100) DEFAULT NULL COMMENT '邮箱',
    `role` ENUM('super_admin', 'admin', 'operator') DEFAULT 'operator' COMMENT '角色',
    `permissions` JSON COMMENT '权限配置',
    `status` TINYINT(1) DEFAULT 1 COMMENT '状态：1正常，0禁用',
    `last_login_time` TIMESTAMP NULL COMMENT '最后登录时间',
    `last_login_ip` VARCHAR(45) DEFAULT NULL COMMENT '最后登录IP',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    INDEX `idx_username` (`username`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB COMMENT='管理员表';

-- 7. 系统配置表
CREATE TABLE `system_configs` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `config_key` VARCHAR(100) NOT NULL UNIQUE COMMENT '配置键',
    `config_value` TEXT COMMENT '配置值',
    `config_type` ENUM('string', 'number', 'json', 'boolean') DEFAULT 'string' COMMENT '配置类型',
    `description` VARCHAR(255) DEFAULT NULL COMMENT '配置描述',
    `is_encrypted` TINYINT(1) DEFAULT 0 COMMENT '是否加密存储',
    `updated_by` INT(11) UNSIGNED DEFAULT NULL COMMENT '最后更新者',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    FOREIGN KEY (`updated_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL,
    INDEX `idx_config_key` (`config_key`)
) ENGINE=InnoDB COMMENT='系统配置表';

-- 8. 操作日志表
CREATE TABLE `operation_logs` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `operator_type` ENUM('user', 'admin') NOT NULL COMMENT '操作者类型',
    `operator_id` INT(11) UNSIGNED NOT NULL COMMENT '操作者ID',
    `action` VARCHAR(100) NOT NULL COMMENT '操作动作',
    `target_type` VARCHAR(50) DEFAULT NULL COMMENT '目标类型',
    `target_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '目标ID',
    `description` VARCHAR(255) NOT NULL COMMENT '操作描述',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
    `user_agent` TEXT COMMENT '用户代理',
    `extra_data` JSON COMMENT '额外数据',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    INDEX `idx_operator` (`operator_type`, `operator_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB COMMENT='操作日志表';

-- 9. 文件上传记录表
CREATE TABLE `file_uploads` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT '用户ID',
    `order_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '关联订单ID',
    `original_name` VARCHAR(255) NOT NULL COMMENT '原始文件名',
    `file_path` VARCHAR(500) NOT NULL COMMENT '文件存储路径',
    `file_size` INT(11) UNSIGNED NOT NULL COMMENT '文件大小(字节)',
    `file_type` VARCHAR(50) NOT NULL COMMENT '文件类型',
    `file_category` ENUM('screenshot', 'cover', 'script', 'report_export') NOT NULL COMMENT '文件分类',
    `upload_ip` VARCHAR(45) DEFAULT NULL COMMENT '上传IP',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`order_id`) REFERENCES `analysis_orders`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_file_category` (`file_category`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB COMMENT='文件上传记录表';

-- 插入默认系统配置
INSERT INTO `system_configs` (`config_key`, `config_value`, `config_type`, `description`) VALUES
('deepseek_api_key', '', 'string', 'DeepSeek API密钥'),
('deepseek_api_url', 'https://api.deepseek.com/v1/chat/completions', 'string', 'DeepSeek API地址'),
('aliyun_sms_access_key', '', 'string', '阿里云短信AccessKey'),
('aliyun_sms_access_secret', '', 'string', '阿里云短信AccessSecret'),
('sms_sign_name', '复盘精灵', 'string', '短信签名'),
('sms_template_code', 'SMS_123456789', 'string', '短信模板编码'),
('default_coin_cost', '10', 'number', '默认分析消耗精灵币数量'),
('max_upload_size', '10485760', 'number', '最大上传文件大小(字节)'),
('site_name', '复盘精灵', 'string', '网站名称'),
('site_logo', '/assets/images/logo.png', 'string', '网站Logo'),
('report_template_path', '/assets/templates/report_template.pdf', 'string', '报告模板PDF路径');

-- 10. 错误日志表
CREATE TABLE `error_logs` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `error_type` VARCHAR(50) NOT NULL COMMENT '错误类型',
    `error_message` TEXT NOT NULL COMMENT '错误消息',
    `file_path` VARCHAR(255) DEFAULT NULL COMMENT '错误文件路径',
    `line_number` INT(11) DEFAULT NULL COMMENT '错误行号',
    `user_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '用户ID',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
    `user_agent` TEXT COMMENT '用户代理',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    INDEX `idx_error_type` (`error_type`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB COMMENT='错误日志表';

-- 插入默认管理员账户 (用户名: admin, 密码: admin123)
INSERT INTO `admins` (`username`, `password_hash`, `real_name`, `role`, `permissions`) VALUES
('admin', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', '超级管理员', 'super_admin', 
'{"user_management": true, "order_management": true, "code_management": true, "system_config": true, "log_view": true}');