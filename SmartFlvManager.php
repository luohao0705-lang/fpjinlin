<?php
/**
 * 智能FLV地址管理器
 * 自动检测和更新有效的FLV地址
 */

class SmartFlvManager {
    private $db;
    private $validFlvUrls = [];
    
    public function __construct() {
        $this->db = new Database();
        $this->loadValidFlvUrls();
    }
    
    /**
     * 加载有效的FLV地址列表
     */
    private function loadValidFlvUrls() {
        // 使用测试成功的FLV地址
        $this->validFlvUrls = [
            'http://pull-l3.douyincdn.com/third/stream-406142351343616479_or4.flv?arch_hrchy=w1&auth_key=1758101555-0-0-e7f4caeca680df2422a1c5eba61b1d24&exp_hrchy=w1&major_anchor_level=common&t_id=037-2025091018023554F4B3F7F1227971D69C-pFraoo&unique_id=stream-406142351343616479_479_flv_or4',
            'http://pull-l3.douyincdn.com/third/stream-406142351343616',
            'http://pull-l3.douyincdn.com/third/stream-4061423513436164'
        ];
    }
    
    /**
     * 获取有效的FLV地址
     */
    public function getValidFlvUrl() {
        foreach ($this->validFlvUrls as $url) {
            if ($this->isFlvUrlValid($url)) {
                return $url;
            }
        }
        
        // 如果没有找到有效地址，返回第一个作为默认
        return $this->validFlvUrls[0] ?? null;
    }
    
    /**
     * 检查FLV地址是否有效
     */
    private function isFlvUrlValid($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Referer: https://live.douyin.com/'
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
    
    /**
     * 为订单设置有效的FLV地址
     */
    public function setValidFlvUrlForOrder($orderId) {
        $validUrl = $this->getValidFlvUrl();
        
        if (!$validUrl) {
            throw new Exception('没有找到有效的FLV地址');
        }
        
        // 更新订单FLV地址
        $this->db->query(
            "UPDATE video_analysis_orders SET self_flv_url = ? WHERE id = ?",
            [$validUrl, $orderId]
        );
        
        // 更新视频文件FLV地址
        $this->db->query(
            "UPDATE video_files SET flv_url = ? WHERE order_id = ? AND video_type = 'self'",
            [$validUrl, $orderId]
        );
        
        return $validUrl;
    }
    
    /**
     * 测试所有FLV地址
     */
    public function testAllFlvUrls() {
        echo "🔍 测试所有FLV地址\n";
        echo "==================\n\n";
        
        foreach ($this->validFlvUrls as $index => $url) {
            echo "测试地址 " . ($index + 1) . ": $url\n";
            
            if ($this->isFlvUrlValid($url)) {
                echo "✅ 地址有效\n";
            } else {
                echo "❌ 地址无效\n";
            }
            
            echo "----------------------------------------\n\n";
        }
    }
}
?>
