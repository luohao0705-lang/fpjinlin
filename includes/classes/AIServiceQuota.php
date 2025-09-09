<?php
/**
 * AI服务额度查询类
 * 复盘精灵系统 - AI服务管理
 */

class AIServiceQuota {
    private $db;
    
    public function __construct() {
        if (method_exists('Database', 'getInstance')) {
            $this->db = Database::getInstance();
        } else {
            $this->db = new Database();
        }
    }
    
    /**
     * 获取所有AI服务的剩余额度
     */
    public function getAllQuotas() {
        return [
            'deepseek' => $this->getDeepSeekQuota(),
            'qwen_omni' => $this->getQwenOmniQuota(),
            'whisper' => $this->getWhisperQuota(),
            'aliyun_sms' => $this->getAliyunSMSQuota()
        ];
    }
    
    /**
     * 获取DeepSeek API额度
     */
    public function getDeepSeekQuota() {
        try {
            $apiKey = getSystemConfig('deepseek_api_key', '');
            $apiUrl = getSystemConfig('deepseek_api_url', 'https://api.deepseek.com/v1/chat/completions');
            
            if (empty($apiKey)) {
                return [
                    'service' => 'DeepSeek',
                    'status' => 'disabled',
                    'remaining' => 0,
                    'total' => 0,
                    'used' => 0,
                    'message' => 'API密钥未配置'
                ];
            }
            
            // 调用DeepSeek API查询额度
            $quota = $this->queryDeepSeekQuota($apiKey, $apiUrl);
            
            return [
                'service' => 'DeepSeek',
                'status' => 'active',
                'remaining' => $quota['remaining'] ?? 0,
                'total' => $quota['total'] ?? 0,
                'used' => $quota['used'] ?? 0,
                'message' => '查询成功'
            ];
            
        } catch (Exception $e) {
            return [
                'service' => 'DeepSeek',
                'status' => 'error',
                'remaining' => 0,
                'total' => 0,
                'used' => 0,
                'message' => '查询失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取Qwen-Omni API额度
     */
    public function getQwenOmniQuota() {
        try {
            $apiKey = getSystemConfig('qwen_omni_api_key', '');
            $apiUrl = getSystemConfig('qwen_omni_api_url', 'https://dashscope.aliyuncs.com/api/v1/services/aigc/video-understanding/generation');
            
            if (empty($apiKey)) {
                return [
                    'service' => 'Qwen-Omni',
                    'status' => 'disabled',
                    'remaining' => 0,
                    'total' => 0,
                    'used' => 0,
                    'message' => 'API密钥未配置'
                ];
            }
            
            // 调用阿里云API查询额度
            $quota = $this->queryAliyunQuota($apiKey, $apiUrl);
            
            return [
                'service' => 'Qwen-Omni',
                'status' => 'active',
                'remaining' => $quota['remaining'] ?? 0,
                'total' => $quota['total'] ?? 0,
                'used' => $quota['used'] ?? 0,
                'message' => '查询成功'
            ];
            
        } catch (Exception $e) {
            return [
                'service' => 'Qwen-Omni',
                'status' => 'error',
                'remaining' => 0,
                'total' => 0,
                'used' => 0,
                'message' => '查询失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取Whisper额度（开源，无限制）
     */
    public function getWhisperQuota() {
        return [
            'service' => 'Whisper',
            'status' => 'unlimited',
            'remaining' => -1, // -1表示无限制
            'total' => -1,
            'used' => 0,
            'message' => '开源服务，无额度限制'
        ];
    }
    
    /**
     * 获取阿里云SMS额度
     */
    public function getAliyunSMSQuota() {
        try {
            $accessKey = getSystemConfig('sms_access_key', '');
            $accessSecret = getSystemConfig('sms_access_secret', '');
            
            if (empty($accessKey) || empty($accessSecret)) {
                return [
                    'service' => 'Aliyun SMS',
                    'status' => 'disabled',
                    'remaining' => 0,
                    'total' => 0,
                    'used' => 0,
                    'message' => 'API密钥未配置'
                ];
            }
            
            // 调用阿里云SMS API查询额度
            $quota = $this->queryAliyunSMSQuota($accessKey, $accessSecret);
            
            return [
                'service' => 'Aliyun SMS',
                'status' => 'active',
                'remaining' => $quota['remaining'] ?? 0,
                'total' => $quota['total'] ?? 0,
                'used' => $quota['used'] ?? 0,
                'message' => '查询成功'
            ];
            
        } catch (Exception $e) {
            return [
                'service' => 'Aliyun SMS',
                'status' => 'error',
                'remaining' => 0,
                'total' => 0,
                'used' => 0,
                'message' => '查询失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 查询DeepSeek API额度
     */
    private function queryDeepSeekQuota($apiKey, $apiUrl) {
        // DeepSeek API通常没有直接的额度查询接口
        // 这里返回模拟数据，实际项目中需要根据API文档实现
        return [
            'remaining' => 1000000, // 模拟剩余额度
            'total' => 1000000,
            'used' => 0
        ];
    }
    
    /**
     * 查询阿里云API额度
     */
    private function queryAliyunQuota($apiKey, $apiUrl) {
        // 阿里云API额度查询需要特定的API调用
        // 这里返回模拟数据，实际项目中需要根据阿里云API文档实现
        return [
            'remaining' => 500000, // 模拟剩余额度
            'total' => 500000,
            'used' => 0
        ];
    }
    
    /**
     * 查询阿里云SMS额度
     */
    private function queryAliyunSMSQuota($accessKey, $accessSecret) {
        // 阿里云SMS额度查询需要特定的API调用
        // 这里返回模拟数据，实际项目中需要根据阿里云SMS API文档实现
        return [
            'remaining' => 10000, // 模拟剩余额度
            'total' => 10000,
            'used' => 0
        ];
    }
    
    /**
     * 记录AI服务使用量
     */
    public function recordUsage($service, $orderId, $usage) {
        try {
            $this->db->insert(
                "INSERT INTO ai_service_usage (service_name, order_id, usage_amount, created_at) VALUES (?, ?, ?, NOW())",
                [$service, $orderId, $usage]
            );
        } catch (Exception $e) {
            error_log("记录AI服务使用量失败: " . $e->getMessage());
        }
    }
    
    /**
     * 获取订单的AI服务使用量
     */
    public function getOrderUsage($orderId) {
        try {
            $usage = $this->db->fetchAll(
                "SELECT service_name, usage_amount, created_at 
                 FROM ai_service_usage 
                 WHERE order_id = ? 
                 ORDER BY created_at ASC",
                [$orderId]
            );
            
            return $usage;
        } catch (Exception $e) {
            error_log("获取订单AI服务使用量失败: " . $e->getMessage());
            return [];
        }
    }
}
?>
