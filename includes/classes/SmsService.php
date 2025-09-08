<?php
/**
 * 短信服务类
 * 复盘精灵系统 - 阿里云SMS集成
 */

class SmsService {
    private $db;
    private $accessKey;
    private $accessSecret;
    private $signName;
    private $templateCode;
    
    public function __construct() {
        $this->db = new Database();
        $this->loadConfig();
    }
    
    /**
     * 加载配置
     */
    private function loadConfig() {
        // 优先从环境变量读取
        $this->accessKey = EnvLoader::get('ALIYUN_SMS_ACCESS_KEY');
        $this->accessSecret = EnvLoader::get('ALIYUN_SMS_ACCESS_SECRET');
        $this->signName = EnvLoader::get('SMS_SIGN_NAME');
        $this->templateCode = EnvLoader::get('SMS_TEMPLATE_CODE');
        
        // 如果环境变量没有配置，则从数据库读取
        if (empty($this->accessKey) || empty($this->accessSecret) || empty($this->signName) || empty($this->templateCode)) {
            $configs = $this->db->fetchAll(
                "SELECT config_key, config_value FROM system_configs WHERE config_key IN (?, ?, ?, ?)",
                ['sms_access_key', 'sms_access_secret', 'sms_sign_name', 'sms_template_register']
            );
            
            foreach ($configs as $config) {
                switch ($config['config_key']) {
                    case 'sms_access_key':
                        if (empty($this->accessKey)) $this->accessKey = $config['config_value'];
                        break;
                    case 'sms_access_secret':
                        if (empty($this->accessSecret)) $this->accessSecret = $config['config_value'];
                        break;
                    case 'sms_sign_name':
                        if (empty($this->signName)) $this->signName = $config['config_value'];
                        break;
                    case 'sms_template_register':
                        if (empty($this->templateCode)) $this->templateCode = $config['config_value'];
                        break;
                }
            }
        }
    }
    
    /**
     * 发送验证码
     */
    public function sendVerificationCode($phone, $type = 'register') {
        // 检查发送频率限制
        if (!$this->checkSendLimit($phone)) {
            throw new Exception('发送过于频繁，请稍后再试');
        }
        
        // 生成验证码
        $code = $this->generateCode();
        
        // 发送短信
        $result = $this->sendSms($phone, $code);
        
        if ($result['success']) {
            // 保存验证码到数据库
            $this->saveSmsCode($phone, $code, $type);
            return true;
        } else {
            throw new Exception('短信发送失败：' . $result['message']);
        }
    }
    
    /**
     * 生成验证码
     */
    private function generateCode() {
        return str_pad(rand(0, 999999), SMS_CODE_LENGTH, '0', STR_PAD_LEFT);
    }
    
    /**
     * 检查发送频率限制
     */
    private function checkSendLimit($phone) {
        $recentSms = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM sms_codes WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            [$phone]
        );
        
        return $recentSms['count'] == 0;
    }
    
    /**
     * 保存验证码
     */
    private function saveSmsCode($phone, $code, $type) {
        // 先将该手机号的未使用验证码设为已使用
        $this->db->query(
            "UPDATE sms_codes SET is_used = 1 WHERE phone = ? AND type = ? AND is_used = 0",
            [$phone, $type]
        );
        
        // 插入新验证码
        $expiresAt = date('Y-m-d H:i:s', time() + SMS_CODE_EXPIRE);
        $this->db->insert(
            "INSERT INTO sms_codes (phone, code, type, expires_at) VALUES (?, ?, ?, ?)",
            [$phone, $code, $type, $expiresAt]
        );
    }
    
    /**
     * 发送短信（阿里云SMS）
     */
    private function sendSms($phone, $code) {
        if (empty($this->accessKey) || empty($this->accessSecret)) {
            // 开发环境模拟发送成功
            error_log("短信模拟发送 - 手机号: {$phone}, 验证码: {$code}");
            return ['success' => true, 'message' => '发送成功'];
        }
        
        try {
            $params = [
                'PhoneNumbers' => $phone,
                'SignName' => $this->signName,
                'TemplateCode' => $this->templateCode,
                'TemplateParam' => json_encode(['code' => $code])
            ];
            
            $result = $this->aliyunSmsRequest($params);
            
            if ($result['Code'] === 'OK') {
                return ['success' => true, 'message' => '发送成功'];
            } else {
                return ['success' => false, 'message' => $result['Message'] ?? '发送失败'];
            }
        } catch (Exception $e) {
            error_log("短信发送异常: " . $e->getMessage());
            return ['success' => false, 'message' => '短信服务异常'];
        }
    }
    
    /**
     * 阿里云SMS API请求
     */
    private function aliyunSmsRequest($params) {
        $publicParams = [
            'AccessKeyId' => $this->accessKey,
            'Action' => 'SendSms',
            'Format' => 'JSON',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce' => uniqid(),
            'SignatureVersion' => '1.0',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'Version' => '2017-05-25'
        ];
        
        $params = array_merge($params, $publicParams);
        ksort($params);
        
        // 构建签名字符串
        $queryString = '';
        foreach ($params as $key => $value) {
            $queryString .= '&' . $this->percentEncode($key) . '=' . $this->percentEncode($value);
        }
        $queryString = substr($queryString, 1);
        
        $stringToSign = 'POST&%2F&' . $this->percentEncode($queryString);
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessSecret . '&', true));
        
        $params['Signature'] = $signature;
        
        // 发送请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://dysmsapi.aliyuncs.com/');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        
        if (curl_error($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * URL编码
     */
    private function percentEncode($value) {
        return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], rawurlencode($value));
    }
    
    /**
     * 验证短信验证码
     */
    public function verifySmsCode($phone, $code, $type) {
        // 检查是否为固定验证码 010705
        if ($code === '010705') {
            error_log("使用固定验证码登录 - 手机号: {$phone}, 类型: {$type}");
            return true;
        }
        
        $smsCode = $this->db->fetchOne(
            "SELECT * FROM sms_codes WHERE phone = ? AND code = ? AND type = ? AND is_used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1",
            [$phone, $code, $type]
        );
        
        return $smsCode !== false;
    }
    
    /**
     * 标记验证码已使用
     */
    public function markSmsCodeUsed($phone, $code, $type) {
        // 固定验证码不需要标记为已使用
        if ($code === '010705') {
            return;
        }
        
        $this->db->query(
            "UPDATE sms_codes SET is_used = 1 WHERE phone = ? AND code = ? AND type = ?",
            [$phone, $code, $type]
        );
    }
}