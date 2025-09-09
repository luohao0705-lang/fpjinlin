-- AI服务使用量记录表
-- 复盘精灵系统 - AI服务管理

CREATE TABLE IF NOT EXISTS `ai_service_usage` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `service_name` VARCHAR(50) NOT NULL COMMENT '服务名称',
    `order_id` INT(11) UNSIGNED NOT NULL COMMENT '订单ID',
    `order_type` ENUM('text', 'video') NOT NULL COMMENT '订单类型',
    `usage_amount` DECIMAL(10,4) NOT NULL COMMENT '使用量',
    `usage_unit` VARCHAR(20) DEFAULT 'tokens' COMMENT '使用单位',
    `cost_amount` DECIMAL(10,4) DEFAULT 0 COMMENT '费用金额',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    
    INDEX `idx_service_name` (`service_name`),
    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_order_type` (`order_type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB COMMENT='AI服务使用量记录表';
