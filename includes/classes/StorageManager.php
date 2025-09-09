<?php
/**
 * 存储管理类
 * 统一管理OSS和本地存储
 */
class StorageManager {
    private $config;
    private $localBasePath;
    
    public function __construct() {
        $this->config = [
            'oss_bucket' => getSystemConfig('oss_bucket', ''),
            'oss_endpoint' => getSystemConfig('oss_endpoint', ''),
            'oss_access_key' => getSystemConfig('oss_access_key', ''),
            'oss_secret_key' => getSystemConfig('oss_secret_key', ''),
        ];
        
        // 使用系统临时目录作为存储基础路径，避免权限问题
        $this->localBasePath = sys_get_temp_dir() . '/video_analysis_storage';
        
        // 验证OSS配置
        if (!$this->isOssConfigured()) {
            error_log("StorageManager: OSS configuration is invalid. Falling back to local storage.");
        }
        
        // 确保本地存储路径存在且可写
        if (!is_dir($this->localBasePath)) {
            if (!mkdir($this->localBasePath, 0755, true)) {
                error_log("StorageManager: Failed to create local storage base path: " . $this->localBasePath);
                // 如果创建失败，尝试使用更简单的路径
                $this->localBasePath = sys_get_temp_dir();
                error_log("StorageManager: Falling back to system temp directory: " . $this->localBasePath);
            }
        }
        
        // 验证路径是否可写
        if (!is_writable($this->localBasePath)) {
            error_log("StorageManager: Local storage path is not writable: " . $this->localBasePath);
            // 如果不可写，使用系统临时目录
            $this->localBasePath = sys_get_temp_dir();
            error_log("StorageManager: Using system temp directory as fallback: " . $this->localBasePath);
        }
    }
    
    /**
     * 上传文件
     */
    public function upload($filePath, $ossKey) {
        try {
            // 检查文件是否存在
            if (!file_exists($filePath)) {
                throw new Exception('文件不存在: ' . $filePath);
            }
            
            // 检查OSS配置
            if ($this->isOssConfigured()) {
                return $this->uploadToOss($filePath, $ossKey);
            } else {
                return $this->uploadToLocal($filePath, $ossKey);
            }
            
        } catch (Exception $e) {
            error_log("❌ 存储上传失败: " . $e->getMessage());
            // 降级到本地存储
            return $this->uploadToLocal($filePath, $ossKey);
        }
    }
    
    /**
     * 下载文件
     */
    public function download($ossKey) {
        try {
            // 检查是否是本地存储
            if (strpos($ossKey, 'local://') === 0) {
                return $this->downloadFromLocal($ossKey);
            }
            
            // 检查OSS配置
            if ($this->isOssConfigured()) {
                return $this->downloadFromOss($ossKey);
            } else {
                throw new Exception('OSS未配置且非本地存储');
            }
            
        } catch (Exception $e) {
            error_log("❌ 存储下载失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 删除文件
     */
    public function delete($ossKey) {
        try {
            if (strpos($ossKey, 'local://') === 0) {
                return $this->deleteFromLocal($ossKey);
            } else {
                return $this->deleteFromOss($ossKey);
            }
        } catch (Exception $e) {
            error_log("❌ 存储删除失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取文件URL
     */
    public function getUrl($ossKey) {
        if (strpos($ossKey, 'local://') === 0) {
            $localPath = $this->localBasePath . '/videos/' . substr($ossKey, 8);
            return '/storage/videos/' . substr($ossKey, 8);
        } else {
            return "https://{$this->config['oss_bucket']}.{$this->config['oss_endpoint']}/{$ossKey}";
        }
    }
    
    /**
     * 检查OSS是否配置
     */
    private function isOssConfigured() {
        return !empty($this->config['oss_bucket']) && 
               !empty($this->config['oss_endpoint']) && 
               !empty($this->config['oss_access_key']) && 
               !empty($this->config['oss_secret_key']);
    }
    
    /**
     * 上传到OSS
     */
    private function uploadToOss($filePath, $ossKey) {
        $url = "https://{$this->config['oss_bucket']}.{$this->config['oss_endpoint']}/{$ossKey}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_INFILE, fopen($filePath, 'rb'));
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filePath));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: ' . $this->getContentType($filePath),
            'Authorization: OSS ' . $this->config['oss_access_key'] . ':' . $this->generateOssSignature('PUT', $ossKey)
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            error_log("✅ 文件上传到OSS成功: {$ossKey}");
            return $ossKey;
        } else {
            throw new Exception("OSS上传失败: HTTP {$httpCode}");
        }
    }
    
    /**
     * 上传到本地
     */
    private function uploadToLocal($filePath, $ossKey) {
        $localPath = $this->localBasePath . '/videos/' . $ossKey;
        $localDir = dirname($localPath);
        
        error_log("🔧 本地存储调试 - 源文件: {$filePath}");
        error_log("🔧 本地存储调试 - 目标路径: {$localPath}");
        error_log("🔧 本地存储调试 - 目标目录: {$localDir}");
        error_log("🔧 本地存储调试 - 基础路径: {$this->localBasePath}");
        
        // 检查源文件是否存在
        if (!file_exists($filePath)) {
            throw new Exception("源文件不存在: {$filePath}");
        }
        
        // 检查基础路径是否存在
        if (!is_dir($this->localBasePath)) {
            throw new Exception("本地存储基础路径不存在: {$this->localBasePath}");
        }
        
        // 检查基础路径是否可写
        if (!is_writable($this->localBasePath)) {
            throw new Exception("本地存储基础路径不可写: {$this->localBasePath}");
        }
        
        // 创建目标目录
        if (!is_dir($localDir)) {
            if (!mkdir($localDir, 0755, true)) {
                throw new Exception("无法创建目标目录: {$localDir}");
            }
        }
        
        // 检查目标目录是否可写
        if (!is_writable($localDir)) {
            throw new Exception("目标目录不可写: {$localDir}");
        }
        
        // 复制文件
        if (copy($filePath, $localPath)) {
            error_log("✅ 文件上传到本地成功: {$localPath}");
            return 'local://' . $ossKey;
        } else {
            $error = error_get_last();
            throw new Exception("文件复制失败: " . ($error['message'] ?? '未知错误'));
        }
    }
    
    /**
     * 从OSS下载
     */
    private function downloadFromOss($ossKey) {
        $url = "https://{$this->config['oss_bucket']}.{$this->config['oss_endpoint']}/{$ossKey}";
        $tempFile = sys_get_temp_dir() . '/temp_' . time() . '_' . basename($ossKey);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: OSS ' . $this->config['oss_access_key'] . ':' . $this->generateOssSignature('GET', $ossKey)
        ]);
        
        $fileContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $fileContent !== false) {
            file_put_contents($tempFile, $fileContent);
            return $tempFile;
        } else {
            throw new Exception("OSS下载失败: HTTP {$httpCode}");
        }
    }
    
    /**
     * 从本地下载
     */
    private function downloadFromLocal($ossKey) {
        $localPath = $this->localBasePath . '/videos/' . substr($ossKey, 8);
        
        if (file_exists($localPath)) {
            return $localPath;
        } else {
            throw new Exception('本地文件不存在: ' . $localPath);
        }
    }
    
    /**
     * 从OSS删除
     */
    private function deleteFromOss($ossKey) {
        $url = "https://{$this->config['oss_bucket']}.{$this->config['oss_endpoint']}/{$ossKey}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: OSS ' . $this->config['oss_access_key'] . ':' . $this->generateOssSignature('DELETE', $ossKey)
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 204;
    }
    
    /**
     * 从本地删除
     */
    private function deleteFromLocal($ossKey) {
        $localPath = $this->localBasePath . '/videos/' . substr($ossKey, 8);
        
        if (file_exists($localPath)) {
            return unlink($localPath);
        }
        
        return true;
    }
    
    /**
     * 获取文件内容类型
     */
    private function getContentType($filePath) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $types = [
            'mp4' => 'video/mp4',
            'flv' => 'video/x-flv',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
            'txt' => 'text/plain',
            'json' => 'application/json',
            'pdf' => 'application/pdf',
        ];
        
        return $types[$extension] ?? 'application/octet-stream';
    }
    
    /**
     * 生成OSS签名
     */
    private function generateOssSignature($method, $ossKey) {
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $contentType = $this->getContentType($ossKey);
        $stringToSign = "{$method}\n\n{$contentType}\n{$date}\n/{$this->config['oss_bucket']}/{$ossKey}";
        
        return base64_encode(hash_hmac('sha1', $stringToSign, $this->config['oss_secret_key'], true));
    }
}
?>
